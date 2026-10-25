<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Listing;
use App\Models\Project;
use App\Models\User;
use App\Support\SearchText;
use App\Support\UaeDateRange;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Read-only global search. Visibility matches the existing lead, deal, and listing rules.
 * This service never creates or updates records.
 */
class SmartSearchService
{
    public const MAX_LIMIT = 5;

    public function __construct(private VoiceSearchService $voice) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function search(User $user, array $input): array
    {
        $limit = min(self::MAX_LIMIT, max(1, (int) ($input['limit'] ?? self::MAX_LIMIT)));
        $raw = trim((string) ($input['q'] ?? ''));
        $suppress = $this->suppressSet($input['suppress'] ?? '');
        $types = $this->typesAllowedFor($user, $this->types($input['types'] ?? null));
        $intent = $this->intent($raw, $suppress);
        $filters = $this->filters($input, $intent, $suppress);
        $confirmed = filter_var($input['confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($intent['bare'] && ! $this->hasStructured($filters)) {
            $intent['needs_confirmation'] = true;

            return $this->payload($raw, $limit, $intent, $filters, [], false, 'Choose a clearer search. This word can mean more than one thing.');
        }

        if (! $this->actionable($raw, $filters, $intent)) {
            return $this->payload($raw, $limit, $intent, $filters, [], false, $this->hint($raw, $intent));
        }

        if ($types === []) {
            return $this->payload($raw, $limit, $intent, $filters, [], false, 'No searchable modules for your permissions.');
        }

        $previewOnly = ($this->isAreaOnly($intent) || $intent['blocked_action']) && ! $confirmed && ! $this->hasExplicit($input);
        if ($previewOnly) {
            $groups = [];
            if (in_array('places', $types, true)) {
                $groups[] = $this->places($user, $raw, $intent, $filters, $limit);
            }
            $intent['needs_confirmation'] = true;
            $message = $intent['blocked_action']
                ? 'Creating an offer stays on the property page. Search can only find records.'
                : 'Choose whether to search listings or matching lead requirements.';

            return $this->payload($raw, $limit, $intent, $filters, $groups, false, $message);
        }

        // Explicit module tab (types=leads|deals|…) always runs that module.
        // Multi-module (default "all") still uses wants* to cut noisy cross-hits.
        $forcedModule = count($types) === 1;

        $groups = [];
        if (in_array('leads', $types, true)) {
            $groups[] = [
                'type' => 'leads',
                'label' => 'Leads',
                'results' => ($forcedModule || $this->wantsLeads($intent, $filters))
                    ? $this->leads($user, $raw, $filters, $intent, $limit)
                    : [],
            ];
        }
        if (in_array('deals', $types, true)) {
            $groups[] = [
                'type' => 'deals',
                'label' => 'Deals',
                'results' => ($forcedModule || $this->wantsDeals($intent, $filters))
                    ? $this->deals($user, $raw, $filters, $intent, $limit)
                    : [],
            ];
        }
        if (in_array('listings', $types, true)) {
            $groups[] = [
                'type' => 'listings',
                'label' => 'Listings',
                'results' => ($forcedModule || $this->wantsListings($intent, $filters))
                    ? $this->listings($user, $raw, $filters, $intent, $limit)
                    : [],
            ];
        }
        if (in_array('places', $types, true)) {
            $groups[] = $this->places($user, $raw, $intent, $filters, $limit);
        }

        return $this->payload($raw, $limit, $intent, $filters, $groups, true, null);
    }

    /**
     * @param  array<string, mixed>  $intent
     * @param  array<string, mixed>  $filters
     * @param  list<array<string, mixed>>  $groups
     * @return array<string, mixed>
     */
    private function payload(string $raw, int $limit, array $intent, array $filters, array $groups, bool $executed, ?string $message): array
    {
        $chips = $this->chips($filters, $intent);

        return [
            // Do not echo phone-shaped input back in the payload.
            'query' => $this->safeQueryEcho($raw),
            'limit' => $limit,
            'executed' => $executed,
            'message' => $message,
            'interpretation' => [
                'summary' => $chips ? implode(' · ', array_column($chips, 'label')) : null,
                'needs_confirmation' => (bool) ($intent['needs_confirmation'] ?? false),
                'blocked_action' => $intent['blocked_action'] ?? null,
                'chips' => $chips,
            ],
            'groups' => $groups,
        ];
    }

    private function safeQueryEcho(string $raw): string
    {
        $ascii = trim(SearchText::digitsToAscii($raw));
        if ($ascii !== '' && preg_match('/^[\d\s\+\-\(\)\.]+$/u', $ascii) === 1) {
            $digits = SearchText::phoneDigits($raw);
            if (strlen($digits) >= 4) {
                return '••••'.substr($digits, -4);
            }
        }
        if (str_contains($raw, '@')) {
            return '[email]';
        }

        return $raw;
    }

    /**
     * @return array<string, mixed>
     */
    private function intent(string $raw, array $suppress): array
    {
        $normalized = SearchText::normalize($raw);
        $digits = SearchText::phoneDigits($raw);
        $ascii = trim(SearchText::digitsToAscii($raw));
        $phoneShaped = $ascii !== '' && preg_match('/^[\d\s\+\-\(\)\.]+$/u', $ascii) === 1;
        $email = str_contains($raw, '@') && mb_strlen(trim($raw)) >= LeadTextSearch::MIN_EMAIL_LENGTH;

        $blocked = null;
        foreach (config('smart_search.offer_phrases', []) as $phrase) {
            $needle = SearchText::normalize((string) $phrase);
            if ($needle !== '' && str_contains($normalized, $needle)) {
                $blocked = 'create_offer';
                break;
            }
        }

        $bareWords = array_map(
            fn ($word) => SearchText::normalize((string) $word),
            config('smart_search.bare_words', [])
        );

        $parsed = null;
        $area = null;
        if (! $phoneShaped && ! $email && mb_strlen($normalized) >= LeadTextSearch::MIN_TEXT_LENGTH) {
            $parsed = $this->voice->parse($raw);
            if (! isset($suppress['area']) && ! empty($parsed['matched_areas'][0])) {
                $area = $parsed['matched_areas'][0];
            }
        }

        return [
            'normalized' => $normalized,
            'phone_shaped' => $phoneShaped,
            'phone' => $phoneShaped && strlen($digits) >= 4,
            'email' => $email,
            'digits' => $digits,
            'blocked_action' => $blocked,
            'dates' => isset($suppress['created']) ? null : $this->dateRange($normalized),
            'parsed' => $parsed,
            'area' => $area,
            'needs_confirmation' => false,
            'bare' => $normalized !== '' && in_array($normalized, $bareWords, true),
        ];
    }

    /**
     * @return array{from: string, to: string}|null
     */
    private function dateRange(string $normalized): ?array
    {
        if ($normalized === '') {
            return null;
        }

        $todayWord = $this->containsAny($normalized, config('smart_search.today_words', []));
        $yesterdayWord = $this->containsAny($normalized, config('smart_search.yesterday_words', []));
        $leadWord = $this->containsAny($normalized, config('smart_search.lead_words', []));
        $createdWord = $this->containsAny($normalized, config('smart_search.created_words', []));
        if (! $leadWord && ! $createdWord) {
            return null;
        }

        $today = Carbon::now(UaeDateRange::TIMEZONE)->toDateString();
        $yesterday = Carbon::now(UaeDateRange::TIMEZONE)->subDay()->toDateString();

        if ($todayWord && $yesterdayWord) {
            return ['from' => $yesterday, 'to' => $today];
        }
        if ($todayWord) {
            return ['from' => $today, 'to' => $today];
        }
        if ($yesterdayWord) {
            return ['from' => $yesterday, 'to' => $yesterday];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $intent
     * @param  array<string, bool>  $suppress
     * @return array<string, mixed>
     */
    private function filters(array $input, array $intent, array $suppress): array
    {
        $filters = [];

        if (! empty($input['requirement_area_id'])) {
            $filters['requirement_area_id'] = (int) $input['requirement_area_id'];
        }
        if (! empty($input['project_id'])) {
            $filters['project_id'] = (int) $input['project_id'];
        }
        if (! empty($input['created_from'])) {
            $filters['created_from'] = (string) $input['created_from'];
            $filters['created_to'] = (string) ($input['created_to'] ?: $input['created_from']);
        } elseif (! empty($intent['dates'])) {
            $filters['created_from'] = $intent['dates']['from'];
            $filters['created_to'] = $intent['dates']['to'];
        }

        $parsed = is_array($intent['parsed']) ? $intent['parsed'] : null;
        $propertyPhrase = $parsed && $this->hasPropertySignal($intent);
        $params = [];
        if ($propertyPhrase && ! $this->isAreaOnly($intent)) {
            $params = $this->voice->toListingQueryParams(
                $parsed['filters'] ?? [],
                $parsed['matched_areas'] ?? []
            );
            unset($params['search']);
        }

        if (! isset($suppress['area'])) {
            if (! empty($input['area_id'])) {
                $filters['area_id'] = (int) $input['area_id'];
            } elseif (! empty($params['area_id'])) {
                $filters['area_id'] = (int) $params['area_id'];
            }
        }
        if (! isset($suppress['bedrooms'])) {
            if (array_key_exists('bedrooms', $input) && $input['bedrooms'] !== null && $input['bedrooms'] !== '') {
                $filters['bedrooms'] = (int) $input['bedrooms'];
            } elseif (isset($params['number_of_bedrooms'])) {
                $filters['bedrooms'] = (int) $params['number_of_bedrooms'];
            }
        }
        if (! isset($suppress['bathrooms']) && isset($params['number_of_bathrooms'])) {
            $filters['bathrooms'] = (int) $params['number_of_bathrooms'];
        }
        if (! isset($suppress['price'])) {
            if (isset($input['min_price']) && $input['min_price'] !== null && $input['min_price'] !== '') {
                $filters['min_price'] = (float) $input['min_price'];
            } elseif (isset($params['min_price'])) {
                $filters['min_price'] = (float) $params['min_price'];
            }
            if (isset($input['max_price']) && $input['max_price'] !== null && $input['max_price'] !== '') {
                $filters['max_price'] = (float) $input['max_price'];
            } elseif (isset($params['max_price'])) {
                $filters['max_price'] = (float) $params['max_price'];
            }
        }
        if (! isset($suppress['purpose'])) {
            if (! empty($input['listing_status'])) {
                $filters['listing_status'] = (string) $input['listing_status'];
            } elseif (! empty($params['listing_status'])) {
                $filters['listing_status'] = (string) $params['listing_status'];
            }
        }
        if (! isset($suppress['property_type'])) {
            if (! empty($input['property_type_id'])) {
                $filters['property_type_id'] = (int) $input['property_type_id'];
            } elseif (! empty($params['property_type_id'])) {
                $filters['property_type_id'] = (int) $params['property_type_id'];
            }
        }

        return $filters;
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    private function hasPropertySignal(array $intent): bool
    {
        $filters = $intent['parsed']['filters'] ?? [];
        if (isset($filters['number_of_bedrooms']) && $filters['number_of_bedrooms'] !== null && $filters['number_of_bedrooms'] !== '') {
            return true;
        }
        if (! empty($filters['number_of_bathrooms']) || ! empty($filters['property_type']) || ! empty($filters['listing_status'])) {
            return true;
        }
        if (! empty($filters['min_price']) || ! empty($filters['max_price'])) {
            return true;
        }

        return $this->containsAny((string) ($intent['normalized'] ?? ''), config('smart_search.listing_words', []));
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    private function isAreaOnly(array $intent): bool
    {
        if ($intent['phone'] || $intent['email'] || $intent['dates'] || $intent['bare'] || empty($intent['area'])) {
            return false;
        }
        if ($this->hasPropertySignal($intent)) {
            return false;
        }

        $norm = (string) $intent['normalized'];
        $aliases = config('voice_search.areas', []);
        if (isset($aliases[$norm])) {
            return true;
        }

        return $norm !== '' && $norm === SearchText::normalize((string) ($intent['area']['name'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function hasStructured(array $filters): bool
    {
        return $filters !== [];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function hasExplicit(array $input): bool
    {
        foreach (['area_id', 'bedrooms', 'requirement_area_id', 'project_id', 'created_from', 'property_type_id', 'listing_status'] as $key) {
            if (isset($input[$key]) && $input[$key] !== '' && $input[$key] !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $intent
     */
    private function actionable(string $raw, array $filters, array $intent): bool
    {
        if ($this->hasStructured($filters) || $this->isAreaOnly($intent) || $intent['blocked_action']) {
            return true;
        }
        if (! empty($intent['phone_shaped'])) {
            return strlen((string) ($intent['digits'] ?? '')) >= 4;
        }
        if ($intent['email']) {
            return true;
        }
        if ($intent['bare']) {
            return false;
        }

        return mb_strlen(trim($raw)) >= LeadTextSearch::MIN_TEXT_LENGTH;
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    private function hint(string $raw, array $intent): string
    {
        $digits = $intent['digits'] ?? '';
        if ($digits !== '' && strlen($digits) < 4 && preg_match('/^[\d\s\+\-\(\)\.]+$/u', trim(SearchText::digitsToAscii($raw))) === 1) {
            return 'Enter at least 4 digits to search a phone number.';
        }

        return 'Type at least 2 characters.';
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $intent
     */
    private function wantsLeads(array $intent, array $filters): bool
    {
        if (! empty($filters['requirement_area_id']) || ! empty($filters['created_from'])) {
            return true;
        }
        if ($intent['phone'] || $intent['email']) {
            return true;
        }
        if ($this->hasPropertySignal($intent)) {
            return false;
        }

        return mb_strlen($intent['normalized']) >= LeadTextSearch::MIN_TEXT_LENGTH;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $intent
     */
    private function wantsDeals(array $intent, array $filters): bool
    {
        if (! empty($filters['requirement_area_id'])) {
            return false;
        }
        if (! empty($filters['created_from'])) {
            return ! $this->containsAny((string) ($intent['normalized'] ?? ''), config('smart_search.lead_words', []));
        }
        if ($intent['phone'] || $intent['email']) {
            return true;
        }
        if ($this->hasPropertySignal($intent)) {
            return false;
        }

        return mb_strlen($intent['normalized']) >= LeadTextSearch::MIN_TEXT_LENGTH;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $intent
     */
    private function wantsListings(array $intent, array $filters): bool
    {
        if (! empty($filters['requirement_area_id'])) {
            return false;
        }
        if (! empty($filters['created_from']) && ! $this->hasPropertySignal($intent) && empty($filters['area_id'])) {
            return false;
        }
        if ($this->hasPropertySignal($intent) || ! empty($filters['area_id']) || ! empty($filters['project_id']) || isset($filters['bedrooms'])) {
            return true;
        }
        if ($intent['email']) {
            return false;
        }

        return mb_strlen($intent['normalized']) >= LeadTextSearch::MIN_TEXT_LENGTH;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $intent
     * @return list<array<string, mixed>>
     */
    private function leads(User $user, string $raw, array $filters, array $intent, int $limit): array
    {
        if (! $this->canSearchLeads($user)) {
            return [];
        }

        $query = Lead::query()->select([
            'id', 'lead_number', 'lead_name', 'first_name', 'last_name', 'stage_id', 'area_id',
            'responsible_person_id', 'lead_branch_source', 'added_by', 'status_lead', 'extra_client_requirements',
        ]);
        $this->constrainLeads($query, $user);

        $requirementName = null;
        if (! empty($filters['requirement_area_id'])) {
            $areaIds = Area::descendantIdsIncludingSelf((int) $filters['requirement_area_id']);
            $requirementName = Area::query()->whereKey($filters['requirement_area_id'])->value('name');
            $query->where(function ($q) use ($areaIds) {
                $q->whereIn('area_id', $areaIds);
                foreach (array_slice($areaIds, 0, 40) as $areaId) {
                    $q->orWhereJsonContains('extra_client_requirements', ['area_id' => (int) $areaId]);
                    $q->orWhereJsonContains('extra_client_requirements', ['area_id' => (string) $areaId]);
                }
            });
        } elseif (! empty($filters['created_from'])) {
            UaeDateRange::apply($query, 'created_at', $filters['created_from'], $filters['created_to'] ?? null);
        } elseif ($intent['phone']) {
            LeadTextSearch::apply($query, $raw);
        } elseif ($intent['email']) {
            $email = mb_strtolower(trim($raw));
            $query->where(function ($q) use ($email) {
                $q->whereRaw('LOWER(email) = ?', [$email])
                    ->orWhereRaw('LOWER(secondary_email) = ?', [$email])
                    ->orWhereRaw('LOWER(source_client_email) = ?', [$email]);
            });
        } else {
            $exact = (clone $query)->where('lead_number', $raw)->with($this->leadWith())->limit($limit)->get();
            // Full identity/contact/requirement text match (same columns as kanban admin search).
            // Rows stay limited by constrainLeads / canViewLead — never wider than permission.
            $like = '%'.addcslashes($intent['normalized'] !== '' ? $intent['normalized'] : $raw, '%_\\').'%';
            $query->where(function ($text) use ($raw, $like) {
                LeadTextSearch::apply($text, $raw, [
                    'comments' => false,
                    'relations' => true,
                    'admin' => true,
                    'lean' => false,
                ]);
                $text->orWhereHas('area', fn ($a) => $a->where('name', 'like', $like));
            });
            $rows = $query->with($this->leadWith())->orderByDesc('id')->limit($limit + count($exact))->get();
            $merged = $exact->concat($rows)->unique('id')->take($limit + 8);

            return $this->presentLeads($user, $merged, $raw, $limit, null, null);
        }

        $rows = $query->with($this->leadWith())->orderByDesc('id')->limit($limit + 8)->get();

        return $this->presentLeads($user, $rows, $raw, $limit, $requirementName, $filters['requirement_area_id'] ?? null);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Lead>  $rows
     * @return list<array<string, mixed>>
     */
    private function presentLeads(User $user, $rows, string $raw, int $limit, ?string $requirementName, ?int $requirementAreaId): array
    {
        $areaIds = $requirementAreaId ? Area::descendantIdsIncludingSelf($requirementAreaId) : [];
        $out = [];
        foreach ($rows as $lead) {
            if (! $user->canViewLead($lead)) {
                continue;
            }
            $match = null;
            if ($requirementAreaId) {
                $match = $this->requirementMatch($lead, $areaIds);
                if ($match === null) {
                    continue;
                }
            }
            $exact = $raw !== '' && strcasecmp((string) $lead->lead_number, $raw) === 0;
            $out[] = $this->leadResult($lead, $exact, $match, $requirementName);
            if (count($out) >= $limit) {
                break;
            }
        }

        usort($out, fn ($a, $b) => [$b['exact'], $b['id']] <=> [$a['exact'], $a['id']]);

        return $out;
    }

    /**
     * @param  list<int>  $areaIds
     */
    private function requirementMatch(Lead $lead, array $areaIds): ?string
    {
        $extra = $lead->extra_client_requirements;
        if (is_string($extra)) {
            $extra = json_decode($extra, true);
        }
        if (is_array($extra)) {
            foreach ($extra as $row) {
                if (! is_array($row) || (($row['_kind'] ?? '') === 'qualification_meta')) {
                    continue;
                }
                if (isset($row['area_id']) && in_array((int) $row['area_id'], $areaIds, true)) {
                    return 'extra';
                }
            }
        }
        if ($lead->area_id && in_array((int) $lead->area_id, $areaIds, true)) {
            return 'primary';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function leadResult(Lead $lead, bool $exact, ?string $match, ?string $requirementName): array
    {
        $agent = User::resolveDisplayName($lead->responsiblePerson);
        $label = $lead->lead_name
            ?: trim(((string) $lead->first_name).' '.((string) $lead->last_name))
            ?: (string) $lead->lead_number;
        $areaLabel = $match === 'extra'
            ? 'Requirement matches '.($requirementName ?: 'this area')
            : ($match === 'primary' ? 'Primary area matches '.($requirementName ?: 'this area') : $lead->area?->name);

        return [
            'type' => 'lead',
            'id' => (int) $lead->id,
            'label' => (string) $label,
            'description' => implode(' · ', array_values(array_filter([
                $lead->lead_number,
                $lead->stage?->name,
                $areaLabel,
                $agent,
            ]))),
            'action' => 'open_lead',
            'exact' => $exact,
            'meta' => [
                'lead_number' => $lead->lead_number,
                'stage' => $lead->stage?->name,
                'area' => $lead->area?->name,
                'agent' => $agent,
                'requirement_match' => $match,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function leadWith(): array
    {
        return [
            'stage:id,name',
            'area:id,name',
            'responsiblePerson:id,name,display_name',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $intent
     * @return list<array<string, mixed>>
     */
    private function deals(User $user, string $raw, array $filters, array $intent, int $limit): array
    {
        if (! $this->canSearchDeals($user)) {
            return [];
        }

        $query = Deal::query()->select([
            'id', 'deal_number', 'deal_name', 'stage_id', 'lead_id', 'responsible_person_id', 'status', 'deal_type',
        ]);
        $this->constrainDeals($query, $user);

        if (! empty($filters['created_from'])) {
            UaeDateRange::apply($query, 'created_at', $filters['created_from'], $filters['created_to'] ?? null);
        } elseif ($intent['phone']) {
            $variants = LeadTextSearch::phoneDigitVariants($intent['digits']);
            $query->where(function ($w) use ($variants, $raw, $user) {
                $w->whereHas('parties', function ($p) use ($variants) {
                    $p->where(function ($phones) use ($variants) {
                        foreach ($variants as $variant) {
                            $phones->orWhere('phone', 'like', '%'.$variant.'%');
                        }
                    });
                })->orWhereHas('lead', function ($lead) use ($raw, $user) {
                    $this->constrainLeads($lead, $user);
                    LeadTextSearch::apply($lead, $raw);
                });
            });
        } elseif ($intent['email']) {
            $email = mb_strtolower(trim($raw));
            $query->where(function ($w) use ($email) {
                $w->whereHas('parties', fn ($p) => $p->whereRaw('LOWER(email) = ?', [$email]))
                    ->orWhereHas('lead', function ($lead) use ($email) {
                        $lead->whereRaw('LOWER(email) = ?', [$email])
                            ->orWhereRaw('LOWER(secondary_email) = ?', [$email]);
                    });
            });
        } else {
            $exact = (clone $query)->where('deal_number', $raw)->with($this->dealWith())->limit($limit)->get();
            $request = Request::create('/api/smart-search', 'GET', ['search' => $raw]);
            $query->filter($request);
            $rows = $query->with($this->dealWith())->orderByDesc('id')->limit($limit + count($exact))->get();
            $merged = $exact->concat($rows)->unique('id')->take($limit + 8);

            return $this->presentDeals($user, $merged, $raw, $limit);
        }

        $rows = $query->with($this->dealWith())->orderByDesc('id')->limit($limit + 8)->get();

        return $this->presentDeals($user, $rows, $raw, $limit);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Deal>  $rows
     * @return list<array<string, mixed>>
     */
    private function presentDeals(User $user, $rows, string $raw, int $limit): array
    {
        $out = [];
        foreach ($rows as $deal) {
            if (! $user->canViewDeal($deal)) {
                continue;
            }
            $exact = $raw !== '' && strcasecmp((string) $deal->deal_number, $raw) === 0;
            $property = $deal->properties->first();
            $area = $property?->area?->name;
            $out[] = [
                'type' => 'deal',
                'id' => (int) $deal->id,
                'label' => $deal->deal_name ?: (string) $deal->deal_number,
                'description' => implode(' · ', array_values(array_filter([
                    $deal->deal_number,
                    $deal->stage?->name,
                    $deal->deal_type,
                    $area,
                    $property?->unit_no ? 'Unit '.$property->unit_no : null,
                ]))),
                'action' => 'open_deal',
                'exact' => $exact,
                'meta' => [
                    'deal_number' => $deal->deal_number,
                    'stage' => $deal->stage?->name,
                    'deal_type' => $deal->deal_type,
                    'area' => $area,
                    'unit_no' => $property?->unit_no,
                ],
            ];
            if (count($out) >= $limit) {
                break;
            }
        }
        usort($out, fn ($a, $b) => [$b['exact'], $b['id']] <=> [$a['exact'], $a['id']]);

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function dealWith(): array
    {
        return [
            'stage:id,name',
            'lead:id,lead_name,lead_number,stage_id,responsible_person_id,lead_branch_source,added_by',
            'properties' => function ($q) {
                $q->without('listing')->select('id', 'deal_id', 'unit_no', 'area_id')->with('area:id,name');
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $intent
     * @return list<array<string, mixed>>
     */
    private function listings(User $user, string $raw, array $filters, array $intent, int $limit): array
    {
        if (! $this->canSearchListings($user)) {
            return [];
        }

        $base = Listing::query()->select([
            'id', 'title', 'reference_number', 'unit_number', 'number_of_bedrooms', 'number_of_bathrooms',
            'price', 'listing_status', 'status', 'area_id', 'project_id', 'property_type_id', 'agent_id',
            'is_active', 'is_archived', 'approved',
        ]);
        // Same inventory window as All Listings for non–super-admins.
        if (! $user->hasRole('super_admin')) {
            $base->activeUnit();
        }
        $this->applyListingFilters($base, $filters);

        $structured = isset($filters['bedrooms']) || ! empty($filters['area_id']) || ! empty($filters['project_id'])
            || ! empty($filters['property_type_id']) || ! empty($filters['listing_status'])
            || isset($filters['min_price']) || isset($filters['max_price']);

        $with = ['area:id,name', 'project:id,title', 'propertyType:id,name', 'agent:id,name,display_name'];
        $exact = collect();
        if ($raw !== '') {
            $exact = (clone $base)->where(function ($q) use ($raw) {
                $q->where('reference_number', $raw)->orWhere('unit_number', $raw);
            })->with($with)->limit($limit)->get();
        }

        if (! $structured && ! $intent['phone'] && ! $intent['email'] && mb_strlen($intent['normalized']) >= LeadTextSearch::MIN_TEXT_LENGTH) {
            $like = '%'.addcslashes($intent['normalized'], '%_\\').'%';
            $rawLike = '%'.addcslashes($raw, '%_\\').'%';
            $base->where(function ($q) use ($like, $rawLike, $raw) {
                $q->where('title', 'like', $like)
                    ->orWhere('reference_number', $raw)
                    ->orWhere('reference_number', 'like', $rawLike)
                    ->orWhere('reference_number', 'like', $like)
                    ->orWhere('unit_number', $raw)
                    ->orWhere('unit_number', 'like', $like)
                    ->orWhereHas('area', fn ($a) => $a->where('name', 'like', $like))
                    ->orWhereHas('project', fn ($p) => $p->where('title', 'like', $like))
                    ->orWhereHas('propertyType', fn ($pt) => $pt->where('name', 'like', $like))
                    ->orWhereHas('agent', function ($agent) use ($like) {
                        $agent->where('name', 'like', $like)
                            ->orWhere('display_name', 'like', $like);
                    });
            });
        } elseif ($intent['phone']) {
            $base->where(function ($q) use ($raw) {
                $q->where('reference_number', $raw)->orWhere('unit_number', $raw);
            });
        } elseif (! $structured) {
            return $this->presentListings($exact, $raw, $limit);
        }

        $rows = $base->with($with)->orderByDesc('id')->limit($limit + max(1, $exact->count()))->get();

        return $this->presentListings($exact->concat($rows)->unique('id'), $raw, $limit);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyListingFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['area_id'])) {
            $ids = Area::descendantIdsIncludingSelf((int) $filters['area_id']);
            $query->where(function ($w) use ($ids) {
                $w->whereIn('area_id', $ids)
                    ->orWhereHas('project', fn ($p) => $p->whereIn('area_id', $ids));
            });
        }
        if (! empty($filters['project_id'])) {
            $query->where('project_id', (int) $filters['project_id']);
        }
        if (isset($filters['bedrooms'])) {
            $query->where('number_of_bedrooms', (int) $filters['bedrooms']);
        }
        if (isset($filters['bathrooms'])) {
            $query->where('number_of_bathrooms', (int) $filters['bathrooms']);
        }
        if (! empty($filters['property_type_id'])) {
            $query->where('property_type_id', (int) $filters['property_type_id']);
        }
        if (isset($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }
        if (isset($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }
        if (! empty($filters['listing_status'])) {
            $needle = str_contains(strtolower((string) $filters['listing_status']), 'rent') ? 'rent' : 'sale';
            $query->whereRaw('LOWER(listing_status) = ?', [$needle]);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Listing>  $rows
     * @return list<array<string, mixed>>
     */
    private function presentListings($rows, string $raw, int $limit): array
    {
        $out = [];
        foreach ($rows as $listing) {
            $beds = $listing->number_of_bedrooms;
            $bedLabel = $beds === null ? null : ((int) $beds === 0 ? 'Studio' : $beds.' bed');
            $exact = $raw !== '' && strcasecmp((string) $listing->reference_number, $raw) === 0;
            $out[] = [
                'type' => 'listing',
                'id' => (int) $listing->id,
                'label' => (string) ($listing->title ?: $listing->reference_number),
                'description' => implode(' · ', array_values(array_filter([
                    $listing->reference_number,
                    $listing->unit_number ? 'Unit '.$listing->unit_number : null,
                    $listing->area?->name,
                    $bedLabel,
                    $listing->listing_status,
                ]))),
                'action' => 'open_listing',
                'exact' => $exact,
                'meta' => [
                    'reference_number' => $listing->reference_number,
                    'unit_number' => $listing->unit_number,
                    'area' => $listing->area?->name,
                    'bedrooms' => $beds === null ? null : (int) $beds,
                    'listing_status' => $listing->listing_status,
                ],
            ];
            if (count($out) >= $limit) {
                break;
            }
        }
        usort($out, fn ($a, $b) => [$b['exact'], $b['id']] <=> [$a['exact'], $a['id']]);

        return $out;
    }

    /**
     * @param  array<string, mixed>  $intent
     * @param  array<string, mixed>  $filters
     * @return array{type: string, label: string, results: list<array<string, mixed>>}
     */
    private function places(User $user, string $raw, array $intent, array $filters, int $limit): array
    {
        $results = [];
        if ($intent['phone'] || $intent['email'] || ! empty($filters['created_from'])) {
            return ['type' => 'places', 'label' => 'Places', 'results' => []];
        }

        if (! empty($intent['area']['id'])) {
            $results[] = $this->areaPlace($intent['area'], $user);
        }

        if (mb_strlen($intent['normalized']) >= LeadTextSearch::MIN_TEXT_LENGTH && ! $this->hasPropertySignal($intent)) {
            $like = '%'.addcslashes($intent['normalized'], '%_\\').'%';
            $areas = Area::query()->select('id', 'name', 'type')->where('name', 'like', $like)->orderBy('name')->limit($limit)->get();
            foreach ($areas as $area) {
                if (collect($results)->contains(fn ($row) => $row['meta']['kind'] === 'area' && (int) $row['id'] === (int) $area->id)) {
                    continue;
                }
                $results[] = $this->areaPlace([
                    'id' => (int) $area->id,
                    'name' => (string) $area->name,
                    'type' => $area->type,
                ], $user);
                if (count($results) >= $limit) {
                    break;
                }
            }

            if (count($results) < $limit) {
                $projects = Project::query()->select('id', 'title', 'area_id')->where('title', 'like', $like)->orderBy('title')->limit($limit)->get();
                foreach ($projects as $project) {
                    $results[] = $this->projectPlace($project, $user);
                    if (count($results) >= $limit) {
                        break;
                    }
                }
            }
        }

        return ['type' => 'places', 'label' => 'Places', 'results' => array_slice($results, 0, $limit)];
    }

    /**
     * @param  array<string, mixed>  $area
     * @return array<string, mixed>
     */
    private function areaPlace(array $area, User $user): array
    {
        $id = (int) $area['id'];

        return [
            'type' => 'place',
            'id' => $id,
            'label' => (string) ($area['name'] ?? 'Area'),
            'description' => trim(((string) ($area['type'] ?? 'Area')).' · Choose a search'),
            'action' => 'choose_place',
            'exact' => false,
            'meta' => [
                'kind' => 'area',
                'choices' => [
                    [
                        'id' => 'listings',
                        'label' => 'Search listings in this area',
                        'area_id' => $id,
                    ],
                    [
                        'id' => 'lead_requirements',
                        'label' => 'Find leads whose requirements match this area',
                        'requirement_area_id' => $id,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function projectPlace(Project $project, User $user): array
    {
        try {
            $canOpen = $user->hasRole('super_admin') || $user->checkPermissionTo('projects-list');
        } catch (\Throwable $e) {
            $canOpen = $user->hasRole('super_admin');
        }

        return [
            'type' => 'place',
            'id' => (int) $project->id,
            'label' => (string) $project->title,
            'description' => 'Project',
            'action' => $canOpen ? 'open_project' : 'choose_place',
            'exact' => false,
            'meta' => [
                'kind' => 'project',
                'choices' => [
                    [
                        'id' => 'listings',
                        'label' => 'Search listings in this project',
                        'project_id' => (int) $project->id,
                    ],
                ],
            ],
        ];
    }

    /**
     * Drop modules the caller cannot open in the UI.
     *
     * @param  list<string>  $types
     * @return list<string>
     */
    private function typesAllowedFor(User $user, array $types): array
    {
        return array_values(array_filter($types, function (string $type) use ($user) {
            return match ($type) {
                'leads' => $this->canSearchLeads($user),
                'deals' => $this->canSearchDeals($user),
                'listings' => $this->canSearchListings($user),
                'places' => $this->canSearchLeads($user) || $this->canSearchListings($user),
                default => false,
            };
        }));
    }

    private function canSearchLeads(User $user): bool
    {
        // Same exceptions as lead visibility: these accounts already bypass row scope.
        if ($user->hasRole('super_admin') || $user->hasRole('admin') || (int) $user->id === 30 || (int) $user->id === 33) {
            return true;
        }

        // Nav uses show-leads; API list uses leads-list — accept either.
        return $this->hasPermission($user, 'show-leads')
            || $this->hasPermission($user, 'leads-list');
    }

    private function canSearchDeals(User $user): bool
    {
        // Deals kanban is gated by the same show-leads permission as Leads.
        return $this->canSearchLeads($user);
    }

    private function canSearchListings(User $user): bool
    {
        if ($user->hasRole('super_admin') || $user->hasRole('admin') || (int) $user->id === 30 || (int) $user->id === 33) {
            return true;
        }
        if ($user->hasRole('manager') || $user->hasRole('only show listings')) {
            return true;
        }
        if (! empty($user->is_listing_team)) {
            return true;
        }

        return $this->hasPermission($user, 'listings-list')
            || $this->hasPermission($user, 'listings-show')
            || $this->hasPermission($user, 'show-leads');
    }

    private function hasPermission(User $user, string $permission): bool
    {
        try {
            return $user->checkPermissionTo($permission);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Same branches as User::canViewLead(), so a suggestion is a record the user can open.
     */
    private function constrainLeads(Builder $query, User $user): Builder
    {
        if ($user->hasRole('super_admin') || (int) $user->id === 30 || (int) $user->id === 33) {
            return $query;
        }

        return $query->where(function ($outer) use ($user) {
            $outer->where(function ($pool) use ($user) {
                $pool->where('stage_id', 10)
                    ->where(function ($own) use ($user) {
                        $own->whereNull('responsible_person_id')
                            ->orWhere('responsible_person_id', '!=', $user->id);
                    });

                $branchAdmin = $user->admin_parent;
                $userBranch = $branchAdmin?->name;
                if (! $userBranch || ! $branchAdmin) {
                    $pool->whereRaw('1 = 0');

                    return;
                }

                $branchUserIds = $branchAdmin->getAllSubordinatesIds();
                $pool->where(function ($q) use ($userBranch, $branchUserIds) {
                    $q->where('lead_branch_source', $userBranch)
                        ->orWhere(function ($q2) use ($branchUserIds) {
                            $q2->whereNull('lead_branch_source')
                                ->whereIn('responsible_person_id', $branchUserIds);
                        })
                        ->orWhere(function ($q2) use ($branchUserIds) {
                            $q2->whereNull('lead_branch_source')
                                ->whereNull('responsible_person_id')
                                ->whereIn('added_by', $branchUserIds);
                        });
                });
            })->orWhere(function ($nonPool) use ($user) {
                $nonPool->where(function ($stage) {
                    $stage->where('stage_id', '!=', 10)->orWhereNull('stage_id');
                });

                if ($user->hasRole('sales') && ! $user->seesBranchLeads()) {
                    $nonPool->where('responsible_person_id', $user->id);

                    return;
                }

                if ($user->hasRole('admin')) {
                    $branchAdmin = $user->admin_parent;
                    if (! $branchAdmin) {
                        $nonPool->whereRaw('1 = 0');

                        return;
                    }
                    $ids = $branchAdmin->getAllSubordinatesIds();
                    $ids[] = (int) $branchAdmin->id;
                    $nonPool->whereIn('responsible_person_id', $ids);

                    return;
                }

                $user->applyLeadResponsibleScope($nonPool, array_merge($user->leadScopeUserIds(), [$user->id]));
            });
        });
    }

    private function constrainDeals(Builder $query, User $user): Builder
    {
        if ($user->hasRole('super_admin') || (int) $user->id === 30 || (int) $user->id === 33) {
            return $query;
        }

        return $query->where(function ($outer) use ($user) {
            $outer->where(function ($direct) use ($user) {
                $direct->visibleFor($user);
            })->orWhereHas('lead', function ($lead) use ($user) {
                $this->constrainLeads($lead, $user);
            });
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $intent
     * @return list<array<string, mixed>>
     */
    private function chips(array $filters, array $intent): array
    {
        $chips = [];
        if (! empty($filters['created_from'])) {
            $from = $filters['created_from'];
            $to = $filters['created_to'] ?? $from;
            $chips[] = [
                'key' => 'created',
                'label' => $from === $to
                    ? 'Created '.$from.' · Asia/Dubai'
                    : 'Created '.$from.' to '.$to.' · Asia/Dubai',
                'value' => $from.'|'.$to,
            ];
        }
        if (! empty($filters['area_id'])) {
            $name = $intent['area']['name'] ?? Area::query()->whereKey($filters['area_id'])->value('name');
            $chips[] = ['key' => 'area', 'label' => (string) ($name ?: 'Area'), 'value' => (int) $filters['area_id']];
        }
        if (! empty($filters['requirement_area_id'])) {
            $name = Area::query()->whereKey($filters['requirement_area_id'])->value('name');
            $chips[] = [
                'key' => 'requirement_area',
                'label' => 'Lead requirements · '.($name ?: 'area'),
                'value' => (int) $filters['requirement_area_id'],
            ];
        }
        if (isset($filters['bedrooms'])) {
            $beds = (int) $filters['bedrooms'];
            $chips[] = [
                'key' => 'bedrooms',
                'label' => $beds === 0 ? 'Studio' : $beds.' bedrooms',
                'value' => $beds,
            ];
        }
        if (! empty($filters['listing_status'])) {
            $chips[] = ['key' => 'purpose', 'label' => ucfirst((string) $filters['listing_status']), 'value' => $filters['listing_status']];
        }
        if (! empty($filters['property_type_id'])) {
            $chips[] = ['key' => 'property_type', 'label' => 'Property type', 'value' => (int) $filters['property_type_id']];
        }
        if (isset($filters['min_price']) || isset($filters['max_price'])) {
            $chips[] = [
                'key' => 'price',
                'label' => 'Price '.($filters['min_price'] ?? 0).'–'.($filters['max_price'] ?? 'any'),
                'value' => ($filters['min_price'] ?? '').'|'.($filters['max_price'] ?? ''),
            ];
        }
        if (! empty($filters['project_id'])) {
            $title = Project::query()->whereKey($filters['project_id'])->value('title');
            $chips[] = ['key' => 'project', 'label' => (string) ($title ?: 'Project'), 'value' => (int) $filters['project_id']];
        }

        return $chips;
    }

    /**
     * @return list<string>
     */
    private function types(?string $types): array
    {
        $allowed = ['leads', 'deals', 'listings', 'places'];
        if ($types === null || trim($types) === '' || trim($types) === 'all') {
            return $allowed;
        }
        $picked = [];
        foreach (explode(',', $types) as $part) {
            $part = trim($part);
            if ($part === 'all') {
                return $allowed;
            }
            if (in_array($part, $allowed, true)) {
                $picked[] = $part;
            }
        }

        return $picked ?: $allowed;
    }

    /**
     * @return array<string, bool>
     */
    private function suppressSet(mixed $suppress): array
    {
        $set = [];
        foreach (explode(',', (string) $suppress) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $set[$part] = true;
            }
        }

        return $set;
    }

    /**
     * @param  list<string>  $words
     */
    private function containsAny(string $normalized, array $words): bool
    {
        foreach ($words as $word) {
            $needle = SearchText::normalize((string) $word);
            if ($needle !== '' && str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }
}
