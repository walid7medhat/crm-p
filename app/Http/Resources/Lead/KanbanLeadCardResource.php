<?php

namespace App\Http\Resources\Lead;

use App\Http\Resources\Lead\Concerns\ResolvesLeadLastActivity;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\User;
use App\Services\Bitrix24\Bitrix24FieldLabels;

/**
 * Lightweight lead payload for Kanban board cards (avoids per-lead history/duplicate queries).
 */
class KanbanLeadCardResource extends JsonResource
{
    use ResolvesLeadLastActivity;

    /** @var array<string, int> */
    protected static array $duplicateCountsByPhone = [];

    /** @var array<int, bool> */
    protected static array $serviceDuplicateByLeadId = [];

    /** @var array<int, array<int>> */
    protected static array $duplicateIdsByLeadId = [];

    /** @var bool */
    protected static bool $collectionPrimed = false;

    /**
     * lead id => last comment/activity author. Null means primed and nobody commented
     * or logged an activity, so the card must not show a person.
     *
     * @var array<int, User|null>|null
     */
    protected static ?array $engagementUsersByLeadId = null;

    /**
     * @param array<string, int> $duplicateCountsByPhone
     * @param array<int, bool> $serviceDuplicateByLeadId
     * @param array<int, array<int>> $duplicateIdsByLeadId  leadId => [duplicate lead ids]
     */
    public static function setKanbanMeta(
        array $duplicateCountsByPhone,
        array $serviceDuplicateByLeadId,
        array $duplicateIdsByLeadId = []
    ): void {
        static::$duplicateCountsByPhone = $duplicateCountsByPhone;
        static::$serviceDuplicateByLeadId = $serviceDuplicateByLeadId;
        static::$duplicateIdsByLeadId = $duplicateIdsByLeadId;
        static::$collectionPrimed = true;
    }

    /**
     * leadId => [ids of other leads with the same work_phone] for a whole page in ONE
     * query — pass as setKanbanMeta()'s 3rd argument. Same exact-phone rule as
     * Lead::getDuplicateLeadsAttribute(), which the duplicates modal lists, so the badge
     * count always matches the modal.
     *
     * @param  iterable<int, \App\Models\Lead>  $leads
     * @return array<int, array<int>>
     */
    public static function duplicateIdsByLeadId($leads): array
    {
        $leads = collect($leads);
        $digitsList = $leads->map(fn ($l) => Lead::phoneDigits($l->work_phone))
            ->filter()->unique()->values()->all();
        if ($digitsList === []) {
            return [];
        }

        $idsByDigits = Lead::query()
            ->whereIn('work_phone_digits', $digitsList)
            ->get(['id', 'work_phone_digits'])
            ->groupBy('work_phone_digits')
            ->map(fn ($rows) => $rows->pluck('id')->map(fn ($id) => (int) $id)->all());

        $map = [];
        foreach ($leads as $lead) {
            $digits = Lead::phoneDigits($lead->work_phone);
            if ($digits === '') {
                continue;
            }
            $others = array_values(array_diff($idsByDigits->get($digits, []), [(int) $lead->id]));
            if ($others) {
                $map[(int) $lead->id] = $others;
            }
        }

        return $map;
    }

    public static function clearKanbanMeta(): void
    {
        static::$duplicateCountsByPhone = [];
        static::$serviceDuplicateByLeadId = [];
        static::$duplicateIdsByLeadId = [];
        static::$collectionPrimed = false;
    }

    /**
     * @param  array<int, User|null>  $map
     */
    public static function setEngagementUsersByLeadId(array $map): void
    {
        static::$engagementUsersByLeadId = $map;
    }

    public static function clearEngagementUsers(): void
    {
        static::$engagementUsersByLeadId = null;
    }

    /**
     * Last person who commented or logged an activity on each lead.
     * Every requested lead id is present; the value is null when neither exists.
     *
     * @param  iterable<int, mixed>  $leads
     * @return array<int, User|null>
     */
    public static function engagementUsersForLeads(iterable $leads): array
    {
        $leadIds = collect($leads)
            ->map(static fn ($lead) => (int) (is_object($lead) ? ($lead->id ?? 0) : 0))
            ->filter(static fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $map = array_fill_keys($leadIds, null);
        if ($leadIds === []) {
            return $map;
        }

        $comments = DB::table('lead_comments')
            ->selectRaw('id, lead_id, user_id, COALESCE(updated_at, created_at) as acted_at')
            ->whereIn('lead_id', $leadIds)
            ->whereNull('deleted_at')
            ->whereNotNull('user_id');

        $activities = DB::table('lead_activities')
            ->selectRaw('id, lead_id, user_id, COALESCE(updated_at, created_at) as acted_at')
            ->whereIn('lead_id', $leadIds)
            ->whereNull('deleted_at')
            ->whereNotNull('user_id');

        $ranked = DB::query()
            ->fromSub($comments->unionAll($activities), 'engagement')
            ->selectRaw('lead_id, user_id, ROW_NUMBER() OVER (PARTITION BY lead_id ORDER BY acted_at DESC, id DESC) as rn');

        $rows = DB::query()
            ->fromSub($ranked, 'ranked')
            ->where('rn', 1)
            ->get(['lead_id', 'user_id']);

        if ($rows->isEmpty()) {
            return $map;
        }

        $users = User::query()
            ->whereIn('id', $rows->pluck('user_id')->map(static fn ($id) => (int) $id)->unique()->all())
            ->with([
                'parent:id,name,display_name,avatar',
                'roles:id,name',
                'employeeProfile.companyBranch:id,name',
                'employeeProfile.designation:id,name',
            ])
            ->get(['id', 'bitrix24_id', 'name', 'display_name', 'avatar', 'email', 'parent_id', 'status'])
            ->keyBy('id');

        foreach ($rows as $row) {
            $map[(int) $row->lead_id] = $users->get((int) $row->user_id);
        }

        return $map;
    }

    /**
     * Card-shaped activity person for a set of leads (Pusher replaces the whole card).
     *
     * @param  iterable<int, mixed>  $leads
     * @return array<int, array<string, mixed>|null>
     */
    public static function activityUserPayloadsForLeads(iterable $leads): array
    {
        $presenter = new static(new Lead);
        $payloads = [];
        foreach (static::engagementUsersForLeads($leads) as $leadId => $user) {
            $payloads[$leadId] = $presenter->formatActivityUser($user);
        }

        return $payloads;
    }

    /** Last comment or activity author. Null hides the avatar on the card. */
    protected function cardActivityUser(): ?User
    {
        $leadId = (int) $this->id;

        if (is_array(static::$engagementUsersByLeadId)) {
            return static::$engagementUsersByLeadId[$leadId] ?? null;
        }

        return static::engagementUsersForLeads([$this->resource])[$leadId] ?? null;
    }

    public function toArray($request): array
    {
        $phone = $this->work_phone;
        $duplicateIds = $this->resolveDuplicateIds();

        $lastActivityAt = $this->bitrix24_last_activity_at ?? $this->updated_at;
        $lastActivityUser = $this->cardActivityUser();

        return [
            'id' => $this->id,
            'added_by' => $this->added_by,
            'lead_name' => $this->lead_name,
            'lead_number' => $this->lead_number,
            'stage_id' => $this->stage_id,
            'salutation' => $this->salutation,
            'first_name' => $this->first_name,
            'second_name' => $this->second_name,
            'last_name' => $this->last_name,
            'whatsapp_number' => $this->whatsapp_number,
            'work_phone' => $this->work_phone,
            'work_phone_2' => $this->work_phone_2,
            'email' => $this->email,
            'lead_source' => $this->lead_source,
            'lead_branch_source' => $this->lead_branch_source,
            'source_information' => $this->source_information,
            'status_lead' => $this->status_lead,
            'interaction_result' => $this->interaction_result,
            'bedrooms' => $this->bedrooms === 0 || $this->bedrooms === '0' ? 'studio' : $this->bedrooms,
            'purpose_buying' => $this->purpose_buying,
            'extra_client_requirements' => $this->extra_client_requirements ?? [],
            'responsible_person_id' => $this->responsible_person_id,
            'budget' => (int) $this->budget,
            'budget_from' => (int) $this->budget_from,
            'budget_to' => (int) $this->budget_to,
            'property_status' => $this->property_status,
            'lead_type' => $this->lead_type,
            'property_type_id' => $this->property_type_id,
            'area_id' => $this->area_id,
            'property_type' => $this->propertyType?->name,
            'area' => $this->area?->name ?? $this->area?->title,
            'created_at' => $this->created_at?->setTimezone(config('app.timezone')),
            'updated_at' => $this->updated_at,
            'duplicate_no' => count($duplicateIds),
            'duplicate_ids' => $duplicateIds,
            'is_reverted' => ! is_null($this->revert),
            'added_by_user' => $this->whenLoaded('addedBy', fn () => $this->formatLeadPoolUser($this->addedBy)),
             'responsible_person' => $this->formatLeadPoolUser($this->responsiblePerson),
            'parent' => $this->whenLoaded('addedBy', fn () => $this->formatLeadPoolUser($this->addedBy)),
            'assigned_at' => $this->created_at,
            'last_activity_at' => $lastActivityAt,
            'last_activity_user' => $this->formatActivityUser($lastActivityUser),
            'bitrix24_last_activity_at' => $this->bitrix24_last_activity_at,
            'bitrix24_last_activity_by_id' => $this->bitrix24_last_activity_by_id,
            // "More Information" line on the card — first extra Facebook/Bitrix answer.
            // Pure PHP over raw_meta_data (already loaded), no extra query.
            'api_first_question' => $this->getFirstApiQuestion(),
            'has_service_duplicate' => $this->hasServiceDuplicate(),
            'score' => $this->score,
            'priority' => $this->priority,
            'intent' => $this->intent,
            'next_action' => $this->next_action,
        ];
    }

    /** @var array<int, array{admin_parent_id: int|null, admin_parent_name: string|null, office_name: string|null}> */
    protected static array $hierarchyCache = [];

    protected function resolveHierarchy($user): array
    {
        if (! array_key_exists($user->id, static::$hierarchyCache)) {
            $adminParent = $user->admin_parent;
            $office = $user->office;

            static::$hierarchyCache[$user->id] = [
                'admin_parent_id' => $adminParent?->id,
                'admin_parent_name' => \App\Models\User::resolveDisplayName($adminParent),
                'office_name' => \App\Models\User::resolveDisplayName($office),
            ];
        }

        return static::$hierarchyCache[$user->id];
    }

    protected function formatLeadPoolUser($user, bool $withHierarchy = false): ?array
    {
        if (! $user) {
            return null;
        }

        $payload = [
            'id' => $user->id,
            'name' => \App\Models\User::resolveDisplayName($user),
            'display_name' => $user->display_name,
            'email' => $user->email,
            'avatar' => $user->avatar ? asset('storage/'.$user->avatar) : null,
            'status' => $user->status,
        ];

        if ($withHierarchy) {
            $payload['parent_id'] = $user->parent_id;
            $payload['parent_name'] = \App\Models\User::resolveDisplayName($user->parent);

            $hierarchy = $this->resolveHierarchy($user);
            $payload['admin_parent_id'] = $hierarchy['admin_parent_id'];
            $payload['admin_parent_name'] = $hierarchy['admin_parent_name'];
            $payload['office_name'] = $hierarchy['office_name'];
        }

        return $payload;
    }

    /**
     * First non-basic Facebook / Bitrix form answer ("Question : Answer") — same logic as
     * LeadResource::getFirstApiQuestion(), shown as "More Information" on the card.
     */
    protected function getFirstApiQuestion(): ?string
    {
        $rawMetaData = is_string($this->raw_meta_data)
            ? json_decode($this->raw_meta_data, true)
            : $this->raw_meta_data;

        if (empty($rawMetaData['field_data']) || ! is_array($rawMetaData['field_data'])) {
            return null;
        }

        $basicFields = ['email', 'phone', 'full_name', 'name', 'work_phone', 'work_phone_number', 'phone_number', 'full name', 'first_name', 'last_name', 'Date', 'Time', 'inbox_url', 'Page_Name', 'form_name', 'form_id', 'No_Label_name', 'No_Label_email', 'No_Label_phone'];

        foreach ($rawMetaData['field_data'] as $field) {
            if (! isset($field['name']) || ! isset($field['values'][0])) {
                continue;
            }
            if (in_array($field['name'], $basicFields, true)) {
                continue;
            }
            $label = Bitrix24FieldLabels::resolveCached($field['name']) ?? $field['name'];

            return $label.' : '.$field['values'][0];
        }

        return null;
    }

    /**
     * @return array<int>
     */
    protected function resolveDuplicateIds(): array
    {
        $leadId = (int) $this->id;

        if (static::$collectionPrimed) {
            return static::$duplicateIdsByLeadId[$leadId] ?? [];
        }

        // Fallback: no bulk meta was set (e.g. resource used outside the kanban board),
        // so fall back to a per-lead query (indexed work_phone_digits column).
        $digits = Lead::phoneDigits($this->work_phone);
        if ($digits === '') {
            return [];
        }

        return Lead::query()
            ->where('id', '!=', $this->id)
            ->where('work_phone_digits', $digits)
            ->limit(200)
            ->pluck('id')
            ->all();
    }

    protected function hasServiceDuplicate(): bool
    {
        $leadId = (int) $this->id;

        if (static::$collectionPrimed) {
            return static::$serviceDuplicateByLeadId[$leadId] ?? false;
        }

        if (! $this->work_phone && ! $this->email) {
            return false;
        }

        return Lead::query()
            ->where('id', '!=', $this->id)
            ->where('status_lead', 'blacklist')
            ->where(function ($q) {
                if ($this->work_phone) {
                    $q->orWhere('work_phone', $this->work_phone);
                }
                if ($this->email) {
                    $q->orWhere('email', $this->email);
                }
            })
            ->exists();
    }
}