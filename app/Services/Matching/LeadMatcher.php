<?php

namespace App\Services\Matching;

use App\Models\Area;
use App\Models\Lead;
use App\Models\LeadListingMatch;
use App\Models\Listing;
use App\Models\Stage;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Scores active listings against a lead's requirements (and the other way round) and
 * keeps the results in lead_listing_matches.
 *
 * Score out of 100: budget 35, area 30, bedrooms 20, property type 10, ready/off-plan 5.
 * Sale vs rent, a different area, more than one bedroom off, or more than 15% over
 * budget rule a listing out. Skips the agent made for the same lead lower listings
 * with the same gap, so the next suggestions follow what the agent rejected.
 */
class LeadMatcher
{
    public const MIN_SCORE = 60;

    public const MAX_PER_LEAD = 12;

    public const CANDIDATE_LIMIT = 80;

    public const QUIET_DAYS = 90;

    /** New, Assigned, Contacted, Qualified and the follow-up stage; never Lead Pool or closed. */
    public const WORKING_STAGE_ORDERS = [1, 2, 3, 4, 7];

    /** Lead columns that change what a lead matches. */
    public const REQUIREMENT_FIELDS = [
        'area_id', 'property_type_id', 'bedrooms', 'budget', 'budget_from', 'budget_to',
        'lead_type', 'property_status', 'extra_client_requirements',
    ];

    /** Too wide to count as "nearby" when a listing sits next to the wanted community. */
    private const BROAD_AREA_TYPES = ['country', 'emirate', 'state', 'city', 'region'];

    private ?array $workingStageIds = null;

    /** @var array<int, Area|null> */
    private array $areas = [];

    /** @var array<int, string|null> */
    private array $typeNames = [];

    /**
     * The requirement the lead is qualified on: the primary fields, or the extra
     * requirement the qualification meta points at. Null when nothing is filled in.
     */
    public function requirementsFor(Lead $lead): ?array
    {
        $extras = collect(is_array($lead->extra_client_requirements) ? $lead->extra_client_requirements : [])
            ->filter(fn ($item) => is_array($item));
        $meta = $extras->first(fn ($item) => ($item['_kind'] ?? null) === 'qualification_meta');
        $source = (string) ($meta['source'] ?? 'primary');

        $raw = null;
        if ($source !== 'primary') {
            $raw = $extras->first(fn ($item) => ($item['_kind'] ?? null) !== 'qualification_meta'
                && (string) ($item['id'] ?? '') === $source);
        }
        if (! $raw) {
            $raw = [
                'area_id' => $lead->area_id,
                'property_type_id' => $lead->property_type_id,
                'bedrooms' => $lead->bedrooms,
                'budget_from' => $lead->budget_from,
                'budget_to' => $lead->budget_to,
                'lead_type' => $lead->lead_type,
                'property_status' => $lead->property_status,
            ];
            if (! $this->positive($raw['budget_to']) && ! $this->positive($raw['budget_from']) && $this->positive($lead->budget)) {
                $raw['budget_to'] = $lead->budget;
            }
        }

        $req = [
            'area_id' => $this->intOrNull($raw['area_id'] ?? null),
            'property_type_id' => $this->intOrNull($raw['property_type_id'] ?? null),
            'bedrooms' => $this->bedroomsOrNull($raw['bedrooms'] ?? null),
            'budget_from' => $this->positive($raw['budget_from'] ?? null) ? (float) $raw['budget_from'] : null,
            'budget_to' => $this->positive($raw['budget_to'] ?? null) ? (float) $raw['budget_to'] : null,
            'lead_type' => strtolower(trim((string) ($raw['lead_type'] ?? ''))) ?: null,
            'property_status' => strtolower(trim((string) ($raw['property_status'] ?? ''))) ?: null,
        ];

        $hasAny = $req['area_id'] || $req['property_type_id'] || $req['bedrooms'] !== null
            || $req['budget_from'] || $req['budget_to'];

        return $hasAny ? $req : null;
    }

    /**
     * Re-score this lead against current stock. Returns the rows that are new to the
     * agent: first-time matches and old ones that came back because the price dropped.
     *
     * @return Collection<int, LeadListingMatch>
     */
    public function refreshLead(Lead $lead): Collection
    {
        $existing = LeadListingMatch::query()
            ->where('lead_id', $lead->id)
            ->with('listing:id,area_id,property_type_id,price')
            ->get()
            ->keyBy('listing_id');

        $req = $this->requirementsFor($lead);
        if (! $req) {
            $this->deleteUnseen($existing->where('status', LeadListingMatch::STATUS_NEW)->pluck('id'));

            return collect();
        }

        $learned = $this->learnedPenalties($existing);
        $scored = [];
        foreach ($this->candidateListings($req) as $listing) {
            $result = $this->score($req, $listing, $learned);
            if ($result && $result['score'] >= self::MIN_SCORE) {
                $scored[$listing->id] = [$listing, $result];
            }
        }
        uasort($scored, fn ($a, $b) => $b[1]['score'] <=> $a[1]['score']);
        $scored = array_slice($scored, 0, self::MAX_PER_LEAD, true);

        $bucket = $this->isQuiet($lead) ? LeadListingMatch::BUCKET_REVIVE : LeadListingMatch::BUCKET_NEW;
        $surfaced = collect();

        DB::transaction(function () use ($lead, $existing, $scored, $bucket, $surfaced) {
            foreach ($scored as $listingId => [$listing, $result]) {
                $row = $existing->get($listingId);
                $row = $row
                    ? $this->rescore($row, $listing, $result)
                    : $this->createMatch($lead->id, $listing, $result, $bucket);
                if ($row) {
                    $surfaced->push($row);
                }
            }

            // Suggestions the agent never looked at and that no longer fit go away.
            $this->deleteUnseen($existing
                ->filter(fn ($row) => $row->status === LeadListingMatch::STATUS_NEW && ! isset($scored[$row->listing_id]))
                ->pluck('id'));
        });

        return $surfaced;
    }

    /**
     * A listing went live or got cheaper: find the leads it fits.
     *
     * @return Collection<int, LeadListingMatch> rows that are new to the agent
     */
    public function matchListing(Listing $listing, bool $priceDropped = false): Collection
    {
        if (! $listing->isActiveUnit()) {
            $this->forgetListing($listing->id);

            return collect();
        }

        $listing->loadMissing(['area:id,name,parent_id,type', 'project:id,title,area_id', 'propertyType:id,name']);
        $surfaced = collect();

        $this->candidateLeads($listing)->chunkById(200, function ($leads) use ($listing, $priceDropped, $surfaced) {
            $rows = LeadListingMatch::query()
                ->where('listing_id', $listing->id)
                ->whereIn('lead_id', $leads->pluck('id'))
                ->get()
                ->keyBy('lead_id');

            foreach ($leads as $lead) {
                $req = $this->requirementsFor($lead);
                $result = $req ? $this->score($req, $listing) : null;
                if (! $result || $result['score'] < self::MIN_SCORE) {
                    continue;
                }

                $row = $rows->get($lead->id);
                if ($row) {
                    $row = $this->rescore($row, $listing, $result);
                } else {
                    $bucket = $priceDropped
                        ? LeadListingMatch::BUCKET_PRICE_DROP
                        : ($this->isQuiet($lead) ? LeadListingMatch::BUCKET_REVIVE : LeadListingMatch::BUCKET_NEW);
                    $row = $this->createMatch($lead->id, $listing, $result, $bucket);
                }
                if ($row) {
                    $row->setRelation('lead', $lead);
                    $surfaced->push($row);
                }
            }
        }, 'leads.id', 'id');

        return $surfaced;
    }

    /** Listing left the active pool: drop suggestions nobody acted on yet. */
    public function forgetListing(int $listingId): void
    {
        LeadListingMatch::query()
            ->where('listing_id', $listingId)
            ->where('status', LeadListingMatch::STATUS_NEW)
            ->delete();
    }

    /**
     * @return array{score:int, fits:array<int,string>, gaps:array<int,string>, gap_kinds:array<int,string>}|null
     */
    public function score(array $req, Listing $listing, array $learned = []): ?array
    {
        $fits = [];
        $gaps = [];
        $kinds = [];
        $score = 0;

        $wantType = $req['lead_type'];
        if (in_array($wantType, ['sale', 'rent'], true) && $listing->listing_status
            && ! str_contains(strtolower((string) $listing->listing_status), $wantType)) {
            return null;
        }

        // Budget: 35
        $price = (float) ($listing->price ?? 0);
        $to = $req['budget_to'];
        $from = $req['budget_from'];
        if ($price <= 0) {
            $score += 15;
        } elseif ($to) {
            if ($price <= $to) {
                $score += 35;
                $fits[] = 'Budget';
                if ($from && $price < $from * 0.7) {
                    $score -= 7;
                    $gaps[] = 'Well under budget';
                    $kinds[] = 'under_budget';
                }
            } else {
                $over = ($price - $to) / $to;
                if ($over > 0.15) {
                    return null;
                }
                $score += $over <= 0.05 ? 26 : 14;
                $gaps[] = max(1, (int) round($over * 100)).'% over budget';
                $kinds[] = 'over_budget';
            }
        } elseif ($from) {
            if ($price >= $from * 0.7) {
                $score += 30;
                $fits[] = 'Budget';
            } else {
                $score += 18;
                $gaps[] = 'Under budget';
                $kinds[] = 'under_budget';
            }
        } else {
            $score += 20;
        }

        // Area: 30
        if ($req['area_id']) {
            $listingAreas = array_values(array_filter([(int) $listing->area_id, (int) ($listing->project?->area_id ?? 0)]));
            $wanted = Area::descendantIdsIncludingSelf($req['area_id']);
            $name = $this->area($req['area_id'])?->name ?? 'Area';
            if (array_intersect($listingAreas, $wanted)) {
                $score += 30;
                $fits[] = $name;
            } elseif (array_intersect($listingAreas, $this->nearbyAreaIds($req['area_id']))) {
                $score += 15;
                $gaps[] = 'Near '.$name;
                $kinds[] = 'nearby_area';
            } else {
                return null;
            }
        } else {
            $score += 15;
        }

        // Bedrooms: 20
        $beds = $req['bedrooms'];
        $listingBeds = $listing->number_of_bedrooms;
        if ($beds !== null) {
            if ($listingBeds === null) {
                $score += 8;
            } elseif ((int) $listingBeds === $beds) {
                $score += 20;
                $fits[] = $this->bedsLabel($beds);
            } elseif (abs((int) $listingBeds - $beds) === 1) {
                $score += 10;
                $gaps[] = $this->bedsLabel((int) $listingBeds).', wanted '.$this->bedsLabel($beds);
                $kinds[] = 'bedrooms';
            } else {
                return null;
            }
        } else {
            $score += 10;
        }

        // Property type: 10
        if ($req['property_type_id']) {
            if ((int) $listing->property_type_id === $req['property_type_id']) {
                $score += 10;
                if ($name = $this->typeName($req['property_type_id'])) {
                    $fits[] = $name;
                }
            } else {
                $have = $this->typeName((int) $listing->property_type_id);
                $want = $this->typeName($req['property_type_id']);
                $gaps[] = $have && $want ? "{$have}, wanted {$want}" : 'Different property type';
                $kinds[] = 'type';
            }
        } else {
            $score += 5;
        }

        // Ready / off-plan: 5
        $listingReady = $this->isReady($listing->completion_status);
        $wantStatus = $req['property_status'];
        if (in_array($wantStatus, ['ready', 'off_plan'], true)) {
            if ($listingReady === null) {
                $score += 3;
            } elseif (($wantStatus === 'ready') === $listingReady) {
                $score += 5;
                $fits[] = $listingReady ? 'Ready' : 'Off-plan';
            } else {
                $gaps[] = $listingReady ? 'Ready, wanted off-plan' : 'Off-plan, wanted ready';
                $kinds[] = 'completion';
            }
        } else {
            $score += 4;
        }

        foreach (array_unique($kinds) as $kind) {
            $score -= $learned['kinds'][$kind] ?? 0;
        }
        $score -= $learned['areas'][(int) $listing->area_id] ?? 0;

        return [
            'score' => max(0, min(100, (int) round($score))),
            'fits' => $fits,
            'gaps' => $gaps,
            'gap_kinds' => array_values(array_unique($kinds)),
        ];
    }

    /** What a skip taught the matcher, in the agent's words. */
    public function learnNote(LeadListingMatch $match, Lead $lead): string
    {
        $who = trim((string) ($lead->first_name ?: $lead->lead_name)) ?: 'this client';
        $kinds = $match->reasons['gap_kinds'] ?? [];
        $areaName = $this->area((int) ($this->requirementsFor($lead)['area_id'] ?? 0))?->name;

        return match (true) {
            in_array('over_budget', $kinds, true) => "Fewer homes over budget for {$who}",
            in_array('nearby_area', $kinds, true) => 'Fewer homes outside '.($areaName ?: 'the wanted area')." for {$who}",
            in_array('bedrooms', $kinds, true) => "Bedrooms now count more for {$who}",
            in_array('type', $kinds, true) => "Property type now counts more for {$who}",
            in_array('completion', $kinds, true) => "Ready vs off-plan now counts more for {$who}",
            default => "Fewer homes like this one for {$who}",
        };
    }

    /** Lead counts as quiet when nobody touched it for QUIET_DAYS. */
    public function isQuiet(Lead $lead): bool
    {
        return $this->quietDays($lead) >= self::QUIET_DAYS;
    }

    public function quietDays(Lead $lead): int
    {
        $anchor = $lead->last_engagement_at ?: $lead->last_stage_change_at ?: $lead->created_at;
        if (! $anchor) {
            return 0;
        }

        return (int) Carbon::parse($anchor)->diffInDays(now());
    }

    /** @return array<int, int> */
    public function workingStageIds(): array
    {
        return $this->workingStageIds ??= Stage::query()
            ->where('stage_type', 'lead')
            ->whereIn('order', self::WORKING_STAGE_ORDERS)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all() ?: [-1];
    }

    /** @return Collection<int, Listing> */
    private function candidateListings(array $req): Collection
    {
        $query = Listing::query()
            ->activeUnit()
            ->with(['area:id,name,parent_id,type', 'project:id,title,area_id', 'propertyType:id,name']);

        if (in_array($req['lead_type'], ['sale', 'rent'], true)) {
            $query->where('listing_status', 'like', '%'.$req['lead_type'].'%');
        }

        if ($req['area_id']) {
            $ids = array_values(array_unique(array_merge(
                Area::descendantIdsIncludingSelf($req['area_id']),
                $this->nearbyAreaIds($req['area_id'])
            )));
            $query->where(function (Builder $inner) use ($ids) {
                $inner->whereIn('area_id', $ids)
                    ->orWhereHas('project', fn (Builder $project) => $project->whereIn('area_id', $ids));
            });
        }

        if ($req['bedrooms'] !== null) {
            $query->whereBetween('number_of_bedrooms', [max(0, $req['bedrooms'] - 1), $req['bedrooms'] + 1]);
        }

        if ($req['budget_to']) {
            $query->where(fn (Builder $inner) => $inner->whereNull('price')->orWhere('price', '<=', $req['budget_to'] * 1.15));
        }
        if ($req['budget_from']) {
            $query->where(fn (Builder $inner) => $inner->whereNull('price')->orWhere('price', '>=', $req['budget_from'] * 0.5));
        }

        return $query->latest('id')->limit(self::CANDIDATE_LIMIT)->get();
    }

    /** Leads in a working stage whose primary requirement could fit, plus leads qualified on an extra requirement. */
    private function candidateLeads(Listing $listing): Builder
    {
        $saleRent = str_contains(strtolower((string) $listing->listing_status), 'rent') ? 'rent' : 'sale';
        $beds = $listing->number_of_bedrooms;
        $price = (float) ($listing->price ?? 0);
        $areaIds = $this->areasALeadCouldWant($listing);

        return Lead::query()
            ->select('leads.*')
            ->whereNull('leads.archived_at')
            ->whereIn('leads.stage_id', $this->workingStageIds())
            ->where(fn (Builder $q) => $q->whereNull('leads.lead_type')->orWhereIn('leads.lead_type', [$saleRent, 'both']))
            ->where(function (Builder $outer) use ($beds, $price, $areaIds) {
                $outer->where(function (Builder $primary) use ($beds, $price, $areaIds) {
                    if ($beds !== null) {
                        $primary->where(fn (Builder $q) => $q->whereNull('leads.bedrooms')
                            ->orWhereBetween('leads.bedrooms', [(int) $beds - 1, (int) $beds + 1]));
                    }
                    if ($price > 0) {
                        $primary->where(fn (Builder $q) => $q->whereNull('leads.budget_to')
                            ->orWhere('leads.budget_to', '<=', 0)
                            ->orWhere('leads.budget_to', '>=', $price / 1.15));
                    }
                    if ($areaIds) {
                        $primary->where(fn (Builder $q) => $q->whereNull('leads.area_id')->orWhereIn('leads.area_id', $areaIds));
                    }
                    $primary->where(fn (Builder $q) => $q->whereNotNull('leads.area_id')
                        ->orWhereNotNull('leads.bedrooms')
                        ->orWhereNotNull('leads.property_type_id')
                        ->orWhere('leads.budget_to', '>', 0)
                        ->orWhere('leads.budget_from', '>', 0)
                        ->orWhere('leads.budget', '>', 0));
                })->orWhere(function (Builder $extra) {
                    $extra->where('leads.extra_client_requirements', 'like', '%qualification_meta%')
                        ->where('leads.extra_client_requirements', 'not like', '%"source":"primary"%');
                });
            });
    }

    /** Area ids a lead could have picked and still fit this listing: its ancestors and their direct children. */
    private function areasALeadCouldWant(Listing $listing): array
    {
        $chain = [];
        foreach (array_filter([(int) $listing->area_id, (int) ($listing->project?->area_id ?? 0)]) as $start) {
            $area = $this->area($start);
            $depth = 0;
            while ($area && $depth++ < 12) {
                $chain[] = (int) $area->id;
                $area = $area->parent_id ? $this->area((int) $area->parent_id) : null;
            }
        }
        $chain = array_values(array_unique($chain));
        if (! $chain) {
            return [];
        }

        $local = array_filter($chain, fn ($id) => ! $this->isBroad($this->area($id)));
        $siblings = $local ? Area::query()->whereIn('parent_id', $local)->pluck('id')->map(fn ($id) => (int) $id)->all() : [];

        return array_values(array_unique(array_merge($chain, $siblings)));
    }

    /** Communities next to the wanted one: everything under its parent, unless the parent is a whole city. */
    private function nearbyAreaIds(int $areaId): array
    {
        $area = $this->area($areaId);
        $parent = $area && $area->parent_id ? $this->area((int) $area->parent_id) : null;
        if (! $parent || $this->isBroad($parent)) {
            return [];
        }

        return Area::descendantIdsIncludingSelf((int) $parent->id);
    }

    /**
     * Per gap kind, how much to take off after the agent skipped listings with that gap
     * more often than they kept them. Areas skipped twice with nothing kept lose 8.
     */
    private function learnedPenalties(Collection $rows): array
    {
        $kinds = [];
        $areas = [];
        foreach ($rows as $row) {
            $skipped = $row->status === LeadListingMatch::STATUS_SKIPPED;
            $kept = in_array($row->status, [LeadListingMatch::STATUS_SHORTLISTED, LeadListingMatch::STATUS_SENT], true);
            if (! $skipped && ! $kept) {
                continue;
            }
            foreach ($row->reasons['gap_kinds'] ?? [] as $kind) {
                $kinds[$kind] = ($kinds[$kind] ?? 0) + ($skipped ? 1 : -1);
            }
            if ($areaId = (int) ($row->listing?->area_id ?? 0)) {
                $areas[$areaId] = ($areas[$areaId] ?? 0) + ($skipped ? 1 : -2);
            }
        }

        return [
            'kinds' => array_map(fn ($n) => 6 * min(3, $n), array_filter($kinds, fn ($n) => $n > 0)),
            'areas' => array_map(fn () => 8, array_filter($areas, fn ($n) => $n >= 2)),
        ];
    }

    private function createMatch(int $leadId, Listing $listing, array $result, string $bucket): LeadListingMatch
    {
        return LeadListingMatch::create([
            'lead_id' => $leadId,
            'listing_id' => $listing->id,
            'score' => $result['score'],
            'reasons' => $this->reasons($result),
            'status' => LeadListingMatch::STATUS_NEW,
            'bucket' => $bucket,
            'matched_price' => $listing->price,
        ]);
    }

    /** Update score and reasons. Returns the row only when a price drop brings it back to the agent. */
    private function rescore(LeadListingMatch $row, Listing $listing, array $result): ?LeadListingMatch
    {
        $row->score = $result['score'];
        $row->reasons = $this->reasons($result);

        $price = (float) ($listing->price ?? 0);
        $dropped = $price > 0 && (float) $row->matched_price > $price;
        $resurfaced = false;
        if ($dropped) {
            $row->previous_price = $row->matched_price;
            $row->matched_price = $price;
            if ($row->status !== LeadListingMatch::STATUS_SHORTLISTED) {
                $row->status = LeadListingMatch::STATUS_NEW;
                $row->bucket = LeadListingMatch::BUCKET_PRICE_DROP;
                $row->notified_at = null;
                $resurfaced = true;
            }
        }
        $row->save();

        return $resurfaced ? $row : null;
    }

    private function reasons(array $result): array
    {
        return [
            'fits' => $result['fits'],
            'gaps' => $result['gaps'],
            'gap_kinds' => $result['gap_kinds'],
        ];
    }

    private function deleteUnseen(Collection $ids): void
    {
        if ($ids->isNotEmpty()) {
            LeadListingMatch::query()->whereIn('id', $ids->all())->delete();
        }
    }

    private function area(int $id): ?Area
    {
        if ($id <= 0) {
            return null;
        }
        if (! array_key_exists($id, $this->areas)) {
            $this->areas[$id] = Area::query()->select(['id', 'name', 'parent_id', 'type'])->find($id);
        }

        return $this->areas[$id];
    }

    private function isBroad(?Area $area): bool
    {
        return ! $area || in_array(strtolower((string) $area->type), self::BROAD_AREA_TYPES, true);
    }

    private function typeName(int $id): ?string
    {
        if ($id <= 0) {
            return null;
        }
        if (! array_key_exists($id, $this->typeNames)) {
            $this->typeNames[$id] = DB::table('property_types')->where('id', $id)->value('name');
        }

        return $this->typeNames[$id];
    }

    /** Listings say "Ready", "Completed", "Off Plan", "Under construction"…; null when unknown. */
    private function isReady($completionStatus): ?bool
    {
        $c = strtolower((string) $completionStatus);
        if ($c === '') {
            return null;
        }
        if (str_contains($c, 'off') || str_contains($c, 'under')) {
            return false;
        }

        return str_contains($c, 'ready') || str_contains($c, 'complet') ? true : null;
    }

    public function bedsLabel(int $beds): string
    {
        return $beds === 0 ? 'Studio' : "{$beds} BR";
    }

    private function bedroomsOrNull($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (strtolower(trim((string) $value)) === 'studio') {
            return 0;
        }

        return is_numeric($value) ? max(0, (int) $value) : null;
    }

    private function intOrNull($value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function positive($value): bool
    {
        return is_numeric($value) && (float) $value > 0;
    }
}
