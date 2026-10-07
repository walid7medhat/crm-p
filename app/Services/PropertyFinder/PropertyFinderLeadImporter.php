<?php

namespace App\Services\PropertyFinder;

use App\Events\LeadUpdated;
use App\Helpers\LeadHistoryHelper;
use App\Models\Lead;
use App\Models\Stage;
use App\Services\LeadAssignmentService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * Creates / refreshes CRM leads from Property Finder leads (call, email, WhatsApp).
 *
 * Accepts the PF `Lead` object from GET /v1/leads, or a webhook event normalized with
 * fromWebhookPayload(). New leads are created exactly like website leads (stage "New",
 * placeholder responsible person, Lead Assignment assigns on create); all PF details are
 * kept in more_information, field_mappings_data (raw PF JSON) and raw_meta_data (field_data).
 *
 * The emirate of the enquired property (listing / project / seller property) sets the
 * lead's `branch` — Dubai or Abu Dhabi — so Lead Assignment routes it to that branch.
 */
class PropertyFinderLeadImporter
{
    /** All PF times are shown / interpreted in UAE time. */
    public const TIMEZONE = 'Asia/Dubai';

    private const CHANNEL_LABELS = ['call' => 'Call', 'email' => 'Email', 'whatsapp' => 'WhatsApp'];

    /** PF emirate / city name → CRM branch (leads.branch, see LeadAssignmentService::resolveLeadBranchId). */
    private const EMIRATE_BRANCHES = [
        'dubai' => 'Dubai',
        'abu_dhabi' => 'Abu Dhabi',
        'abu dhabi' => 'Abu Dhabi',
        'al ain' => 'Abu Dhabi',
    ];

    public function __construct(private PropertyFinderClient $client)
    {
    }

    /** lead_source per channel — same sentences as the Bitrix portal leads (see LeadSourceFilter). */
    private const LEAD_SOURCES = [
        'whatsapp' => 'Whatsapp from Property Finder',
        'email' => 'Email from Property Finder',
        'call' => 'Call from Property Finder',
    ];

    /**
     * Webhook payload → the same shape as a GET /v1/leads item (minus call/tags,
     * which only the API returns).
     */
    public static function fromWebhookPayload(array $event): array
    {
        $payload = $event['payload'] ?? [];

        return array_filter([
            'id' => (string) Arr::get($event, 'entity.id'),
            'entityType' => $payload['entityType'] ?? null,
            'channel' => $payload['channel'] ?? null,
            'status' => $payload['status'] ?? null,
            'publicProfile' => $payload['publicProfile'] ?? null,
            'sender' => $payload['sender'] ?? null,
            'responseLink' => $payload['responseLink'] ?? null,
            'listing' => $payload['listing'] ?? null,
            'project' => $payload['project'] ?? null,
            'developer' => $payload['developer'] ?? null,
            // The event time is the lead's creation time only for lead.created; for
            // lead.updated / lead.assigned it's the update time, so leave it unknown.
            'createdAt' => ($event['type'] ?? null) === 'lead.created' ? ($event['timestamp'] ?? null) : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Leads created in PF before this moment are never imported. PF_IMPORT_FROM, or —
     * when empty — the first time the integration ran, persisted so it never moves.
     */
    public static function importFrom(): Carbon
    {
        if (filled(config('propertyfinder.import_from'))) {
            return Carbon::parse(config('propertyfinder.import_from'), self::TIMEZONE);
        }

        $file = storage_path('app/propertyfinder_import_from.txt');
        if (! is_file($file)) {
            file_put_contents($file, now()->utc()->toIso8601String());
        }

        return Carbon::parse(trim((string) file_get_contents($file)))->setTimezone(self::TIMEZONE);
    }

    /** @return array{lead: ?Lead, created: bool, skipped?: string} */
    public function import(array $pf, string $via = 'webhook'): array
    {
        $pfId = (string) ($pf['id'] ?? '');
        if ($pfId === '') {
            return ['lead' => null, 'created' => false, 'skipped' => 'no id'];
        }

        $existing = Lead::withTrashed()->where('pf_lead_id', $pfId)->first();
        if ($existing) {
            $this->refresh($existing, $pf);

            return ['lead' => $existing, 'created' => false];
        }

        // New to the CRM → only if PF created it after go-live (no old leads).
        $createdAt = ! empty($pf['createdAt']) ? Carbon::parse($pf['createdAt']) : null;
        if (! $createdAt || $createdAt->lt(self::importFrom())) {
            return ['lead' => null, 'created' => false, 'skipped' => $createdAt ? 'created before import_from' : 'unknown createdAt'];
        }

        try {
            $lead = $this->create($pf, $via);
        } catch (QueryException $e) {
            // Webhook job and scheduled sync raced on the same PF lead — the unique
            // pf_lead_id index stopped the duplicate; refresh the winner instead.
            $existing = Lead::withTrashed()->where('pf_lead_id', $pfId)->first();
            if (! $existing) {
                throw $e;
            }
            $this->refresh($existing, $pf);

            return ['lead' => $existing, 'created' => false];
        }

        return ['lead' => $lead, 'created' => true];
    }

    private function create(array $pf, string $via): Lead
    {
        $pf['property'] = $this->propertyDetails($pf);
        $pf = array_filter($pf, fn ($v) => $v !== [] && $v !== null);
        $contacts = $this->contacts($pf);
        $name = trim((string) Arr::get($pf, 'sender.name', ''));
        $channel = self::CHANNEL_LABELS[$pf['channel'] ?? ''] ?? ucfirst((string) ($pf['channel'] ?? ''));
        $systemUserId = (int) config('propertyfinder.system_user_id', 1);
        $branch = self::EMIRATE_BRANCHES[strtolower((string) Arr::get($pf, 'property.emirate'))] ?? null;
        // Abu Dhabi → 25, Dubai → 59, anything else → 1 (config propertyfinder.branch_users).
        $responsibleId = (int) (config("propertyfinder.branch_users.{$branch}") ?: $systemUserId);
        // #25 → #1690 for leads from outside (Lead::externalResponsibleId).
        $responsibleId = Lead::externalResponsibleId($responsibleId);

        $newStageId = app(LeadAssignmentService::class)->resolveNewStageId()
            ?? Stage::where('stage_type', 'lead')->orderBy('order')->value('id');

        $lead = Lead::create([
            'pf_lead_id' => (string) $pf['id'],
            'integration_id' => null,
            'meta_lead_id' => null,
            'lead_name' => $name !== '' ? $name : trim("Property Finder {$channel} Lead"),
            'first_name' => $name !== '' ? $name : null,
            'email' => $contacts['email'][0] ?? null,
            'secondary_email' => $contacts['email'][1] ?? null,
            'work_phone' => $contacts['phone'][0] ?? null,
            'work_phone_2' => $contacts['phone'][1] ?? null,
            'whatsapp_number' => $contacts['whatsappUsername'][0]
                ?? (($pf['channel'] ?? null) === 'whatsapp' ? ($contacts['phone'][0] ?? null) : null),
            'stage_id' => $newStageId,
            'lead_source' => self::LEAD_SOURCES[$pf['channel'] ?? ''] ?? 'Property Finder',
            'source_information' => $this->sourceInformation($pf),
            'more_information' => $this->moreInformation($pf),
            // Dubai / Abu Dhabi → if auto-assignment is on, it re-assigns to a sales agent
            // of that branch; otherwise the lead stays with the branch user.
            'branch' => $branch,
            'added_by' => $systemUserId,
            'responsible_person_id' => $responsibleId,
            'field_mappings_data' => json_encode($pf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'raw_meta_data' => json_encode(['field_data' => $this->fieldData($pf)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $lead->lead_branch_source = $lead->responsiblePerson?->admin_parent?->name;
        $lead->save();

        LeadHistoryHelper::log($lead->id, [
            'action' => 'created',
            'name' => $lead->lead_name,
            'lead_branch_source' => $lead->lead_branch_source,
            'source' => 'property_finder',
            'pf_lead_id' => (string) $pf['id'],
            'pf_channel' => $pf['channel'] ?? null,
        ]);
        broadcast(new LeadUpdated($lead, 'created'));

        Log::channel('propertyfinder')->info('PF lead created', [
            'lead_id' => $lead->id, 'pf_lead_id' => $pf['id'], 'channel' => $pf['channel'] ?? null,
            'emirate' => Arr::get($pf, 'property.emirate'), 'branch' => $lead->branch, 'via' => $via,
        ]);

        return $lead;
    }

    /**
     * Existing lead: refresh only the PF detail fields (status, call recording, tags…),
     * quietly — never touch stage / assignee / contact fields sales may have edited, and
     * don't count as user engagement (revert timers).
     */
    private function refresh(Lead $lead, array $pf): void
    {
        // A webhook payload carries less than the API lead; keep what we already have.
        $previous = json_decode((string) $lead->field_mappings_data, true);
        $merged = is_array($previous) ? array_replace_recursive($previous, $pf) : $pf;

        $lead->updateQuietly([
            'source_information' => $this->sourceInformation($merged),
            'more_information' => $this->moreInformation($merged),
            'field_mappings_data' => json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'raw_meta_data' => json_encode(['field_data' => $this->fieldData($merged)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * What the client enquired about + its emirate:
     *  listing → GET /v1/listings (uaeEmirate, title, price, …)
     *  project → GET /v1/projects/{id} → location tree
     *  seller  → sellerProperty.locationPath (no API call)
     * Lookups are best-effort: a PF API error never blocks creating the lead.
     */
    private function propertyDetails(array $pf): array
    {
        $details = [];

        if ($path = Arr::get($pf, 'sellerProperty.locationPath')) {
            $details['location'] = $path;
            $details['emirate'] = $this->emirateFromNames(preg_split('/\s*[,>\/]\s*/', $path));
        }

        if (! PropertyFinderClient::isConfigured()) {
            return array_filter($details);
        }

        try {
            if ($listingId = Arr::get($pf, 'listing.id')) {
                if ($listing = $this->client->getListing((string) $listingId)) {
                    $priceType = Arr::get($listing, 'price.type');
                    $details += [
                        'title' => Arr::get($listing, 'title.en') ?? Arr::get($listing, 'title.ar'),
                        'reference' => $listing['reference'] ?? null,
                        'offering' => $priceType ? ($priceType === 'sale' ? 'sale' : 'rent') : null,
                        'price' => $priceType ? Arr::get($listing, "price.amounts.{$priceType}") : null,
                        'priceType' => $priceType,
                        'category' => $listing['category'] ?? null,
                        'propertyType' => $listing['type'] ?? null,
                        'bedrooms' => $listing['bedrooms'] ?? null,
                        'bathrooms' => $listing['bathrooms'] ?? null,
                        'size' => $listing['size'] ?? null,
                        'pfAgent' => Arr::get($listing, 'assignedTo.name'),
                    ];
                    $details['emirate'] = $listing['uaeEmirate'] ?? null;
                    $locationId = Arr::get($listing, 'location.id');
                }
            } elseif ($projectId = Arr::get($pf, 'project.id')) {
                if ($project = $this->client->getProject((string) $projectId)) {
                    $details += [
                        'title' => Arr::get($project, 'title.en'),
                        'developer' => Arr::get($project, 'developer.name.en') ?? Arr::get($project, 'developer.name'),
                        'startingPrice' => $project['startingPrice'] ?? null,
                    ];
                    $locationId = Arr::get($project, 'location.id');
                }
            }

            // Location tree → readable path, and the emirate when the listing didn't give one.
            if (! empty($locationId) && ($location = $this->client->getLocation($locationId))) {
                $names = array_merge(Arr::pluck($location['tree'] ?? [], 'name'), [$location['name'] ?? null]);
                $names = array_values(array_unique(array_filter($names)));
                $details['location'] = implode(', ', $names);
                $details['emirate'] = ($details['emirate'] ?? null) ?: $this->emirateFromNames($names);
            }
        } catch (\Throwable $e) {
            Log::channel('propertyfinder')->warning('PF property lookup failed', [
                'pf_lead_id' => $pf['id'] ?? null, 'error' => $e->getMessage(),
            ]);
        }

        return array_filter($details, fn ($v) => $v !== null && $v !== '');
    }

    /** ['Dubai', 'Dubai Marina', …] → 'dubai' | 'abu_dhabi' | 'northern_emirates' | null */
    private function emirateFromNames(array $names): ?string
    {
        foreach ($names as $name) {
            $key = strtolower(trim((string) $name));
            if (isset(self::EMIRATE_BRANCHES[$key])) {
                return self::EMIRATE_BRANCHES[$key] === 'Dubai' ? 'dubai' : 'abu_dhabi';
            }
        }
        foreach ($names as $name) {
            if (preg_match('/sharjah|ajman|ras al khaimah|umm al quwain|fujairah/i', (string) $name)) {
                return 'northern_emirates';
            }
        }

        return null;
    }

    private function uaeTime(?string $iso): ?string
    {
        if (! $iso) {
            return null;
        }
        try {
            return Carbon::parse($iso)->setTimezone(self::TIMEZONE)->format('Y-m-d h:i A') . ' (UAE)';
        } catch (\Throwable) {
            return $iso;
        }
    }

    /** sender.contacts → ['phone' => [...], 'email' => [...], 'whatsappUsername' => [...]] */
    private function contacts(array $pf): array
    {
        $out = [];
        foreach ((array) Arr::get($pf, 'sender.contacts', []) as $contact) {
            $type = $contact['type'] ?? null;
            $value = trim((string) ($contact['value'] ?? ''));
            if ($type && $value !== '' && ! in_array($value, $out[$type] ?? [], true)) {
                $out[$type][] = $value;
            }
        }

        return $out;
    }

    private function sourceInformation(array $pf): string
    {
        $parts = ['Property Finder ' . (self::CHANNEL_LABELS[$pf['channel'] ?? ''] ?? ($pf['channel'] ?? 'lead'))];
        if ($branch = self::EMIRATE_BRANCHES[strtolower((string) Arr::get($pf, 'property.emirate'))] ?? null) {
            $parts[] = $branch;
        }
        if ($ref = Arr::get($pf, 'listing.reference')) {
            $parts[] = "Listing ref: {$ref}";
        }
        if ($project = Arr::get($pf, 'project.id')) {
            $parts[] = "Project: {$project}";
        }

        return implode(' | ', $parts);
    }

    /** Human-readable summary of everything PF sent — shown in the lead view. */
    private function moreInformation(array $pf): string
    {
        $lines = [
            'Property Finder lead ID' => $pf['id'] ?? null,
            'Channel' => self::CHANNEL_LABELS[$pf['channel'] ?? ''] ?? ($pf['channel'] ?? null),
            'Status' => $pf['status'] ?? null,
            'Enquiry type' => $pf['entityType'] ?? null,
            'Distribution' => $pf['distributionType'] ?? null,
            'Received at' => $this->uaeTime($pf['createdAt'] ?? null),
            'Emirate' => match (Arr::get($pf, 'property.emirate')) {
                'dubai' => 'Dubai', 'abu_dhabi' => 'Abu Dhabi', 'northern_emirates' => 'Northern Emirates', default => null,
            },
            'Location' => Arr::get($pf, 'property.location'),
            'Property' => Arr::get($pf, 'property.title'),
            'Offering' => Arr::get($pf, 'property.offering'),
            'Price (AED)' => is_numeric(Arr::get($pf, 'property.price'))
                ? number_format((float) Arr::get($pf, 'property.price')) . (Arr::get($pf, 'property.priceType') !== 'sale' ? ' / ' . Arr::get($pf, 'property.priceType') : '')
                : null,
            'Property type' => trim(Arr::get($pf, 'property.category', '') . ' ' . Arr::get($pf, 'property.propertyType', '')) ?: null,
            'Bedrooms' => Arr::get($pf, 'property.bedrooms'),
            'Bathrooms' => Arr::get($pf, 'property.bathrooms'),
            'Size' => Arr::get($pf, 'property.size'),
            'Developer' => Arr::get($pf, 'property.developer'),
            'Starting price' => Arr::get($pf, 'property.startingPrice'),
            'PF agent' => Arr::get($pf, 'property.pfAgent'),
            'Agent public profile ID' => Arr::get($pf, 'publicProfile.id'),
            'Listing reference' => Arr::get($pf, 'listing.reference'),
            'Listing ID' => Arr::get($pf, 'listing.id'),
            'Project ID' => Arr::get($pf, 'project.id'),
            'Developer ID' => Arr::get($pf, 'developer.id'),
            'Call talk time (sec)' => Arr::get($pf, 'call.talkTime'),
            'Call wait time (sec)' => Arr::get($pf, 'call.waitTime'),
            'Call recording' => Arr::get($pf, 'call.recordFile'),
            'WhatsApp response link' => $pf['responseLink'] ?? null,
            'Tags' => ! empty($pf['tags']) ? implode(', ', (array) $pf['tags']) : null,
        ];

        foreach ((array) ($pf['sellerProperty'] ?? []) as $key => $value) {
            $lines['Seller property — ' . $this->label($key)] = is_array($value) ? implode(', ', Arr::flatten($value)) : $value;
        }
        foreach ((array) ($pf['enrichment'] ?? []) as $key => $value) {
            $lines['Enrichment — ' . $this->label($key)] = $value;
        }

        $out = [];
        foreach ($lines as $label => $value) {
            if ($value !== null && $value !== '') {
                $out[] = "{$label}: {$value}";
            }
        }

        return implode("\n", $out);
    }

    /** Same field_data rows ({name, values}) as Meta / website leads. */
    private function fieldData(array $pf): array
    {
        $rows = [];
        foreach (Arr::dot($pf) as $key => $value) {
            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }
            $rows[] = ['name' => $key, 'values' => [is_bool($value) ? ($value ? 'true' : 'false') : (string) $value]];
        }

        return $rows;
    }

    private function label(string $key): string
    {
        return ucfirst(strtolower(trim(preg_replace('/(?<!^)[A-Z]/', ' $0', $key))));
    }
}
