<?php

namespace App\Services\Bitrix24;

use App\Helpers\LeadHistoryHelper;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadComment;
use App\Models\LeadHistory;
use App\Models\Stage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Bitrix24LeadImporter
{
    private Bitrix24Client $client;
    private int $fallbackUserId;
    private ?int $defaultStageId;

    /** Cache: bitrix user_id => local user_id|null */
    private array $userCache = [];

    /** Cache: STATUS_ID code => human name (lead pipeline statuses) */
    private ?array $statusMap = null;

    /** Cache: SOURCE_ID code => human name */
    private ?array $sourceMap = null;

    /** Cache: lowercased Bitrix24 status name => local stage_id */
    private array $stageNameCache = [];

    /** Cache: bitrix24 user_id => display name (independent of local user mapping) */
    private array $b24UserNameCache = [];

    /**
     * Set to true once crm.timeline.item.list returns "method not found" for
     * this portal so we stop hammering it for the rest of the sync. The error
     * is "Could not find description of list in Bitrix\Crm\Controller\Timeline\Item"
     * on older Bitrix24 versions that don't ship the new timeline.item endpoint.
     */
    private bool $timelineItemListUnsupported = false;

    /** Same flag for crm.timeline.bindings.list (older-portal fallback). */
    private bool $timelineBindingsUnsupported = false;

    /** Same flag for crm.timeline.logmessage.list. */
    private bool $timelineLogMessagesUnsupported = false;

    /**
     * Bitrix24 status NAME (lowercased) → keyword to LIKE-search local stages.
     * Handles the common cases where Bitrix24's pipeline label doesn't match
     * the local stage label verbatim (e.g. "Junk" → local "Unqualified").
     */
    private const STATUS_NAME_SYNONYMS = [
        // Order matters — longer/more-specific keys must come first so a
        // generic key doesn't shadow a specific one (e.g. "not qualified"
        // before "qualified" doesn't apply here, but the principle holds).
        'not qualified'    => 'unqualified',
        'non qualified'    => 'unqualified',
        'Unqualified'    => 'unqualified',
        'Unqualified Lead'    => 'unqualified',
        'disqualified'     => 'unqualified',
        'junk'             => 'unqualified',
        'spam'             => 'unqualified',
        'declined'         => 'unqualified',
        'rejected'         => 'unqualified',
        'in process'       => 'contacted',
        'in progress'      => 'contacted',
        'processing'       => 'contacted',
        'follow up'        => 'contacted',
        'follow-up'        => 'contacted',
        'Follow-up / Contacted'  => 'contacted',
        'processed'        => 'converted',
        'closed won'       => 'converted',
        'closed-won'       => 'converted',
        'won'              => 'converted',
        'success'          => 'converted',
        'completed'        => 'converted',
        'closed lost'      => 'lost',
        'closed-lost'      => 'lost',
        'failed'           => 'lost',
        'dead'             => 'lost',
        'fresh'            => 'new',
        'pool'             => 'pool',
        'shared'           => 'shared',
        'assigned'         => 'assigned',
        'Future Prospected'=>'qualified',
        'Jop Seeker'=>'unqualified',
        'Junk'=>'unqualified',
    ];

    /**
     * Bitrix24 keys already mapped to dedicated `leads` columns. Excluded from
     * the field_data envelope so LeadResource::facebook_questions_answers
     * shows only the *extra* Q&A pairs (custom UF_* fields, non-mapped fields)
     * — not redundant duplicates of the email/phone/name columns.
     */
    private const STANDARD_B24_KEYS = [
        // identity & contact
        'ID', 'TITLE', 'NAME', 'SECOND_NAME', 'LAST_NAME', 'HONORIFIC', 'BIRTHDATE',
        'EMAIL', 'PHONE', 'WEB', 'IM',
        // company
        'COMPANY_TITLE', 'POST', 'COMPANY_ID', 'CONTACT_ID',
        // source / status / opportunity
        'SOURCE_ID', 'SOURCE_DESCRIPTION', 'COMMENTS', 'STATUS_ID', 'STATUS_DESCRIPTION',
        'OPPORTUNITY', 'CURRENCY_ID', 'IS_MANUAL_OPPORTUNITY',
        // ownership / audit
        'ASSIGNED_BY_ID', 'CREATED_BY_ID', 'MODIFY_BY_ID', 'LAST_ACTIVITY_BY',
        'DATE_CREATE', 'DATE_MODIFY', 'LAST_ACTIVITY_TIME', 'MOVED_TIME', 'MOVED_BY_ID',
        // address
        'ADDRESS', 'ADDRESS_2', 'ADDRESS_CITY', 'ADDRESS_PROVINCE', 'ADDRESS_REGION',
        'ADDRESS_POSTAL_CODE', 'ADDRESS_COUNTRY', 'ADDRESS_COUNTRY_CODE', 'ADDRESS_LOC_ADDR_ID',
        // system flags / origin
        'OPENED', 'HAS_PHONE', 'HAS_EMAIL', 'HAS_IMOL', 'IS_RETURN_CUSTOMER',
        'ORIGIN_ID', 'ORIGINATOR_ID', 'STATUS_SEMANTIC_ID',
    ];

    public function __construct(Bitrix24Client $client, int $fallbackUserId)
    {
        $this->client = $client;
        $this->fallbackUserId = $fallbackUserId;
        $this->defaultStageId = Stage::where('stage_type', 'lead')
            ->orderBy('order', 'asc')
            ->value('id')
            ?? Stage::orderBy('order', 'asc')->value('id');
    }

    /**
     * Import (or reuse) a Bitrix24 lead. Returns:
     *   [
     *     'lead'        => Lead,
     *     'created'     => bool,   // true = freshly inserted, false = matched existing
     *     'bitrix24_id' => int,
     *   ]
     */
    public function importOne(array $b24Lead): array
    {
        $b24Id = (int) ($b24Lead['ID'] ?? 0);

        // Idempotency: a previously-imported Bitrix24 lead is reused, not duplicated.
        // We still re-run comments/activities/timeline to pick up new entries
        // and sync the lead's stage with Bitrix24's CURRENT STATUS_ID (otherwise
        // re-imports would leave the lead stuck in whatever stage it first
        // landed in — usually the default 'New' stage).
        if ($b24Id > 0) {
            $existing = Lead::where('bitrix24_id', $b24Id)->first();
            if ($existing) {
                 $oldStatus = $existing->status_lead;
                $newStatus = $this->statusName($b24Lead['STATUS_ID'] ?? null);
                
                if ($oldStatus !== $newStatus && $newStatus !== null) {
                    LeadHistoryHelper::log($existing->id, [
                        'action' => 'stage_changed',
                        'old_stage' => $oldStatus,
                        'new_stage' => $newStatus,
                        'old_stage_id' => $existing->stage_id,
                        'new_stage_id' => $this->resolveStageId($b24Lead['STATUS_ID'] ?? null),
                        'source' => 'bitrix24',
                        'note' => "Status changed from {$oldStatus} to {$newStatus} via Bitrix24 sync"
                    ]);
                }
                
                $this->syncStageForExistingLead($existing, $b24Lead);
                $this->importComments($existing, $b24Id);
                $this->importActivities($existing, $b24Id);
                // $this->importTimelineItems($existing, $b24Id);
                // $this->importTimelineBindings($existing, $b24Id);
                // $this->importTimelineLogMessages($existing, $b24Id);
                // $this->importSyntheticTimeline($existing, $b24Lead, $b24Id);
                 $this->importFullTimeline($existing, $b24Id);
                return [
                    'lead'        => $existing,
                    'created'     => false,
                    'bitrix24_id' => $b24Id,
                ];
            }
        }

        $attributes = $this->prepareLeadRow($b24Lead, $b24Id);
        $lead = Lead::withoutEvents(fn () => Lead::create($attributes));

        LeadHistoryHelper::log($lead->id, [
            'action'      => 'created',
            'name'        => $lead->lead_name,
            'source'      => 'bitrix24',
            'bitrix24_id' => $b24Id,
        ]);

        $this->importComments($lead, $b24Id);
        $this->importActivities($lead, $b24Id);
        $this->importTimelineItems($lead, $b24Id);
        $this->importTimelineBindings($lead, $b24Id);
        $this->importTimelineLogMessages($lead, $b24Id);
        $this->importSyntheticTimeline($lead, $b24Lead, $b24Id);

        return [
            'lead'        => $lead,
            'created'     => true,
            'bitrix24_id' => $b24Id,
        ];
    }

    /**
     * crm.timeline.logmessage.list fallback. Empty on most older portals
     * but cheap to try — gives status-change records when supported.
     */
    private function importTimelineLogMessages(Lead $lead, int $b24LeadId): void
    {
        if ($this->timelineLogMessagesUnsupported) {
            return;
        }
        try {
            $messages = $this->client->listTimelineLogMessages($b24LeadId);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Could not find description')
                || str_contains($msg, 'Method not found')
            ) {
                $this->timelineLogMessagesUnsupported = true;
                Log::info('Bitrix24 timeline.logmessage.list is not available on this portal — skipping.');
                return;
            }
            Log::warning("Bitrix24 timeline.logmessage.list for lead {$b24LeadId} failed: " . $msg);
            return;
        }

        Log::info("Bitrix24 timeline.logmessage for lead {$b24LeadId}: " . count($messages) . ' messages.');

        foreach ($messages as $message) {
            $messageId = (int) ($message['id'] ?? $message['ID'] ?? 0);
            if ($messageId <= 0) {
                continue;
            }
            $synthId = 6_000_000_000_000_000 + $messageId;
            if (LeadHistory::where('lead_id', $lead->id)
                ->where('bitrix24_id', $synthId)
                ->exists()
            ) {
                continue;
            }

            $createdAt = $this->parseDate($message['createdTime'] ?? $message['CREATED'] ?? null) ?? now();
            $authorId  = $this->mapBitrixUser($message['authorId'] ?? $message['AUTHOR_ID'] ?? null) ?? $this->fallbackUserId;
            $typeCode  = $this->normalizeCode($message['type'] ?? $message['TYPE'] ?? null);
            $title     = trim((string) ($message['title'] ?? $message['TITLE'] ?? 'Bitrix24 log message'));

            LeadHistory::create([
                'lead_id'     => $lead->id,
                'user_id'     => $authorId,
                'bitrix24_id' => $synthId,
                'changes'     => [
                    'action' => 'timeline_item',
                    'type'   => $typeCode,
                    'title'  => $title,
                    'data'   => $message,
                    'source' => 'bitrix24',
                    'origin' => 'timeline.logmessage.list',
                ],
                'created_at'  => $createdAt,
                'updated_at'  => $createdAt,
            ]);
        }
    }

    /**
     * Fallback timeline source for older Bitrix24 portals that lack
     * crm.timeline.item.list. crm.timeline.bindings.list lists associations
     * between timeline records and a CRM entity; bindings often carry enough
     * info (TYPE_ID, CREATED, AUTHOR_ID) to log a coarse history entry.
     * Status-change bindings have TYPE_ID values like "modification" or
     * specific numeric codes — we map those into a stage_changed action when
     * possible.
     */
    private function importTimelineBindings(Lead $lead, int $b24LeadId): void
    {
        if ($this->timelineBindingsUnsupported) {
            return;
        }
        try {
            $bindings = $this->client->listTimelineBindings($b24LeadId);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Could not find description')
                || str_contains($msg, 'Method not found')
            ) {
                $this->timelineBindingsUnsupported = true;
                Log::info('Bitrix24 timeline.bindings.list is not available on this portal — skipping.');
                return;
            }
            Log::warning("Bitrix24 timeline.bindings.list for lead {$b24LeadId} failed: " . $msg);
            return;
        }

        Log::info("Bitrix24 timeline.bindings for lead {$b24LeadId}: " . count($bindings) . ' bindings.');

        foreach ($bindings as $binding) {
            $timelineId = (int) ($binding['TIMELINE_ID'] ?? $binding['ID'] ?? 0);
            if ($timelineId <= 0) {
                continue;
            }

            // Dedup via a synthetic ID: bindings live in a different ID space
            // than timeline.item, so prefix to avoid collision.
            $synthId = 7_000_000_000_000_000 + $timelineId;
            if (LeadHistory::where('lead_id', $lead->id)
                ->where('bitrix24_id', $synthId)
                ->exists()
            ) {
                continue;
            }

            $createdAt = $this->parseDate($binding['CREATED'] ?? null) ?? now();
            $authorId  = $this->mapBitrixUser($binding['AUTHOR_ID'] ?? null) ?? $this->fallbackUserId;
            $typeCode  = $this->normalizeCode($binding['TYPE_ID'] ?? $binding['TYPE'] ?? null);

            LeadHistory::create([
                'lead_id'     => $lead->id,
                'user_id'     => $authorId,
                'bitrix24_id' => $synthId,
                'changes'     => [
                    'action' => 'timeline_item',
                    'type'   => $typeCode,
                    'title'  => 'Bitrix24 timeline entry',
                    'data'   => $binding,
                    'source' => 'bitrix24',
                    'origin' => 'timeline.bindings.list',
                ],
                'created_at'  => $createdAt,
                'updated_at'  => $createdAt,
            ]);
        }
    }

    /**
     * Older Bitrix24 portals don't expose crm.timeline.item.list, so we
     * synthesize timeline entries from fields already present in the
     * crm.lead.get response: MOVED_TIME (last stage move), DATE_MODIFY (last
     * edit), and LAST_ACTIVITY_TIME (last touched). Each entry uses a stable
     * synthetic bitrix24_id so re-imports dedupe cleanly.
     */
    private function importSyntheticTimeline(Lead $lead, array $b24Lead, int $b24LeadId): void
    {
        // Last stage move
        $movedTime = $this->parseDate($b24Lead['MOVED_TIME'] ?? null);
        $statusId  = $b24Lead['STATUS_ID'] ?? null;
        if ($movedTime !== null && $statusId !== null) {
            $stageId = $this->resolveStageId($statusId);
            $stageName = $this->statusName($statusId);
            $userId = $this->mapBitrixUser($b24Lead['MOVED_BY_ID'] ?? null) ?? $this->fallbackUserId;
            $this->upsertSyntheticHistory(
                lead: $lead,
                b24LeadId: $b24LeadId,
                eventKey: 'moved',
                eventTime: $movedTime,
                userId: $userId,
                changes: [
                    'action'       => 'stage_changed',
                    'new_stage'    => $stageName,
                    'new_stage_id' => $stageId,
                    'source'       => 'bitrix24',
                    'origin'       => 'lead.get.MOVED_TIME',
                ],
            );
        }

        // Last modify (skip when identical to created — that's already logged)
        $modifyTime = $this->parseDate($b24Lead['DATE_MODIFY'] ?? null);
        $createTime = $this->parseDate($b24Lead['DATE_CREATE'] ?? null);
        if ($modifyTime !== null
            && ($createTime === null || !$modifyTime->equalTo($createTime))
        ) {
            $userId = $this->mapBitrixUser($b24Lead['MODIFY_BY_ID'] ?? null) ?? $this->fallbackUserId;
            $this->upsertSyntheticHistory(
                lead: $lead,
                b24LeadId: $b24LeadId,
                eventKey: 'modified',
                eventTime: $modifyTime,
                userId: $userId,
                changes: [
                    'action' => 'updated',
                    'source' => 'bitrix24',
                    'origin' => 'lead.get.DATE_MODIFY',
                ],
            );
        }

        // Last activity (only if distinct from modify)
        $lastActivity = $this->parseDate($b24Lead['LAST_ACTIVITY_TIME'] ?? null);
        if ($lastActivity !== null
            && ($modifyTime === null || !$lastActivity->equalTo($modifyTime))
        ) {
            $userId = $this->mapBitrixUser($b24Lead['LAST_ACTIVITY_BY'] ?? null) ?? $this->fallbackUserId;
            $this->upsertSyntheticHistory(
                lead: $lead,
                b24LeadId: $b24LeadId,
                eventKey: 'activity',
                eventTime: $lastActivity,
                userId: $userId,
                changes: [
                    'action' => 'timeline_item',
                    'type'   => 'last_activity',
                    'title'  => 'Last activity recorded on Bitrix24',
                    'source' => 'bitrix24',
                    'origin' => 'lead.get.LAST_ACTIVITY_TIME',
                ],
            );
        }
    }

    private function upsertSyntheticHistory(
        Lead $lead,
        int $b24LeadId,
        string $eventKey,
        Carbon $eventTime,
        int $userId,
        array $changes,
    ): void {
        $synthId = $this->syntheticHistoryId($b24LeadId, $eventKey, $eventTime);

        $exists = LeadHistory::where('lead_id', $lead->id)
            ->where('bitrix24_id', $synthId)
            ->exists();
        if ($exists) {
            return;
        }

        LeadHistory::create([
            'lead_id'     => $lead->id,
            'user_id'     => $userId,
            'bitrix24_id' => $synthId,
            'changes'     => $changes,
            'created_at'  => $eventTime,
            'updated_at'  => $eventTime,
        ]);
    }

    /**
     * Stable synthetic bitrix24_id for a (lead, event_kind, timestamp) tuple.
     * The 8e15 prefix keeps it well clear of real Bitrix24 timeline.item IDs
     * (which are sequential and stay below 10^12 even on busy portals).
     */
    private function syntheticHistoryId(int $b24LeadId, string $eventKey, Carbon $ts): int
    {
        $hash = crc32($b24LeadId . '|' . $eventKey . '|' . $ts->format('YmdHis'));
        return 8_000_000_000_000_000 + (int) $hash;
    }

    /**
     * Fetch Bitrix24's full timeline and save each entry as a LeadHistory row.
     * STATUS_ID changes are stored as 'stage_changed' (matching the native
     * LeadController::changeStage history shape). ASSIGNED_BY_ID changes are
     * stored as 'assigned' (matching LeadController::assignResponsiblePerson).
     * Comment/activity timeline items are skipped — they already have rows via
     * importComments / importActivities and a separate history entry each.
     * Each saved row carries bitrix24_id so reruns dedupe cleanly.
     */
   private function importTimelineItems(Lead $lead, int $b24LeadId): void
    {
        if ($this->timelineItemListUnsupported) {
            return;
        }
        try {
            $items = $this->client->listTimelineItems($b24LeadId);
            Log::info("Bitrix24 timeline for lead {$b24LeadId}: " . count($items) . " items");
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            // Older Bitrix24 portals don't ship crm.timeline.item.list. Detect
            // the "method not found" signature and disable for the rest of
            // this sync instead of retrying (and log-spamming) per lead.
            if (str_contains($msg, 'Could not find description')
                || str_contains($msg, 'Method not found')
            ) {
                $this->timelineItemListUnsupported = true;
                Log::info('Bitrix24 timeline.item.list is not available on this portal — skipping for remaining leads. Stage history is still captured via syncStageForExistingLead on re-imports.');
                return;
            }
            Log::warning("Bitrix24 timeline.item.list for lead {$b24LeadId} failed: " . $msg);
            return;
        }

        foreach ($items as $item) {
            $b24ItemId = (int) ($item['id'] ?? $item['ID'] ?? 0);
            
            // للتصحيح: سجل نوع العنصر
            $typeId = $this->normalizeCode($item['type'] ?? $item['TYPE_ID'] ?? null);
            Log::info("Processing timeline item {$b24ItemId} with type: {$typeId}", ['item' => json_encode($item)]);
            
            if ($b24ItemId <= 0) {
                continue;
            }

            // لا تتخطى أي عنصر - دع التصنيف يقرر
            $typeLc = $typeId !== null ? mb_strtolower($typeId) : null;
            
            // تأكد من أننا لا نتخطى تغييرات STATUS_ID
            // فقط نتخطى التعليقات والنشاطات الواضحة
            if (in_array($typeLc, ['comment', 'activity'], true)) {
                Log::info("Skipping comment/activity item {$b24ItemId}");
                continue;
            }

            // التحقق من التكرار
            if (LeadHistory::where('lead_id', $lead->id)
                ->where('bitrix24_id', $b24ItemId)
                ->exists()
            ) {
                Log::info("Timeline item {$b24ItemId} already imported");
                continue;
            }

            $authorId = $this->mapBitrixUser($item['authorId'] ?? $item['AUTHOR_ID'] ?? null) ?? $this->fallbackUserId;
            $createdAt = $this->parseDate($item['createdTime'] ?? $item['CREATED'] ?? null) ?? now();
            $title = trim((string) ($item['title'] ?? $item['TITLE'] ?? $typeId ?? 'Bitrix24 event'));

            $changes = $this->classifyTimelineItem($item, $typeId, $title);
            
            // للتصحيح: سجل التغييرات المصنفة
            Log::info("Classified changes for item {$b24ItemId}: " . json_encode($changes));

            LeadHistory::create([
                'lead_id'     => $lead->id,
                'user_id'     => $authorId,
                'bitrix24_id' => $b24ItemId,
                'changes'     => $changes,
                'created_at'  => $createdAt,
                'updated_at'  => $createdAt,
            ]);
        }
    }

    /**
     * Map a Bitrix24 timeline item into the local LeadHistory `changes` shape.
     * Recognizes STATUS_ID changes -> 'stage_changed' (matching
     * LeadController::changeStage) and ASSIGNED_BY_ID changes -> 'assigned'
     * (matching LeadController::assignResponsiblePerson). Everything else falls
     * through as a generic 'timeline_item' carrying the raw payload.
     */
    private function classifyTimelineItem(array $item, ?string $typeId, string $title): array
    {
        [$field, $from, $to] = $this->extractFieldChange($item);

        if ($field === 'STATUS_ID' && ($from !== null || $to !== null)) {
            return [
                'action'       => 'stage_changed',
                'old_stage'    => $from !== null ? ($this->statusName($from) ?? $from) : null,
                'new_stage'    => $to !== null ? ($this->statusName($to) ?? $to) : null,
                'old_stage_id' => $from !== null ? $this->resolveStageId($from) : null,
                'new_stage_id' => $to !== null ? $this->resolveStageId($to) : null,
                'source'       => 'bitrix24',
            ];
        }

        if ($field === 'ASSIGNED_BY_ID' && ($from !== null || $to !== null)) {
            return [
                'action'        => 'assigned',
                'old_person_id' => $from !== null ? $this->mapBitrixUser($from) : null,
                'old_person'    => $from !== null ? $this->bitrixUserName($from) : null,
                'new_person'    => $to !== null ? $this->bitrixUserName($to) : null,
                'source'        => 'bitrix24',
            ];
        }

        return [
            'action' => 'timeline_item',
            'type'   => $typeId,
            'title'  => $title,
            'data'   => $item,
            'source' => 'bitrix24',
        ];
    }

    /**
     * Best-effort extraction of (field, from, to) from a Bitrix24 timeline item.
     * Bitrix24 emits these in varied shapes across API versions — check the
     * top-level keys and the additionalData/details bag.
     */
    private function extractFieldChange(array $item): array
        {
            $field = null;
            
            // البحث في المفاتيح المعروفة
            foreach (['field', 'entityField', 'fieldName', 'FIELD', 'ENTITY_FIELD'] as $k) {
                if (isset($item[$k]) && is_string($item[$k])) {
                    $field = $item[$k];
                    break;
                }
            }

            // البحث في البيانات الإضافية
            $bag = null;
            foreach (['additionalData', 'details', 'ADDITIONAL_INFO', 'SETTINGS', 'data'] as $k) {
                if (isset($item[$k]) && is_array($item[$k])) {
                    $bag = $item[$k];
                    break;
                }
            }
            
            if ($field === null && $bag !== null) {
                foreach (['field', 'entityField', 'fieldName', 'FIELD'] as $k) {
                    if (isset($bag[$k]) && is_string($bag[$k])) {
                        $field = $bag[$k];
                        break;
                    }
                }
            }

            // جمع القيم من مصادر متعددة
            $from = $this->pickFirst($item, ['from', 'oldValue', 'FROM', 'OLD_VALUE', 'old']);
            $to   = $this->pickFirst($item, ['to', 'newValue', 'TO', 'NEW_VALUE', 'new']);
            
            if ($bag !== null) {
                $from = $from ?? $this->pickFirst($bag, ['from', 'oldValue', 'FROM', 'OLD_VALUE', 'old']);
                $to   = $to ?? $this->pickFirst($bag, ['to', 'newValue', 'TO', 'NEW_VALUE', 'new']);
            }
            
            // للتصحيح: سجل ما وجدناه
            Log::info("Extracted field change - field: {$field}, from: {$from}, to: {$to}");

            return [$field, $from === null ? null : (string) $from, $to === null ? null : (string) $to];
        }
    private function pickFirst(array $haystack, array $keys)
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $haystack) && is_scalar($haystack[$k])) {
                return $haystack[$k];
            }
        }
        return null;
    }

    /** Display name for a Bitrix24 user (independent of whether they map locally). */
    /**
     * Resolve display names for every Bitrix24 user_id referenced by this lead
     * (assigned / created / modified / moved / last-activity). Cheap-ish — the
     * inner getUser() is already cached per importer instance, so repeated
     * imports for the same user only hit Bitrix24 once.
     */
    private function collectUserInfo(array $b24Lead): array
    {
        $roles = [
            'assigned'      => $b24Lead['ASSIGNED_BY_ID']  ?? null,
            'created'       => $b24Lead['CREATED_BY_ID']   ?? null,
            'modified'      => $b24Lead['MODIFY_BY_ID']    ?? null,
            'moved'         => $b24Lead['MOVED_BY_ID']     ?? null,
            'last_activity' => $b24Lead['LAST_ACTIVITY_BY'] ?? null,
        ];

        $out = [];
        foreach ($roles as $role => $id) {
            if (!$id) {
                continue;
            }
            $idInt = (int) $id;
            $out[$role] = [
                'bitrix24_id'   => $idInt,
                'name'          => $this->bitrixUserName($idInt),
                'local_user_id' => $this->mapBitrixUser($idInt),
            ];
        }
        return $out;
    }

    private function bitrixUserName($b24UserId): ?string
    {
        if (!$b24UserId) {
            return null;
        }
        $b24UserId = (int) $b24UserId;
        if (array_key_exists($b24UserId, $this->b24UserNameCache)) {
            return $this->b24UserNameCache[$b24UserId];
        }

        $remote = $this->client->getUser($b24UserId);
        $name = null;
        if ($remote) {
            $candidate = trim(($remote['NAME'] ?? '') . ' ' . ($remote['LAST_NAME'] ?? ''));
            $name = $candidate !== '' ? $candidate : ($remote['EMAIL'] ?? "Bitrix24 user #{$b24UserId}");
        }
        $this->b24UserNameCache[$b24UserId] = $name;
        return $name;
    }

    /**
     * Flatten a Bitrix24 lead payload into Meta's [{name, values: [...]}] shape.
     * Standard Bitrix24 keys (already mapped to dedicated `leads` columns) are
     * filtered out — only the *extras* (custom UF_* fields, anything not in
     * STANDARD_B24_KEYS) remain, so LeadResource::facebook_questions_answers
     * surfaces Q&A pairs the user actually wants to see.
     */
    private function buildFieldData(array $b24Lead): array
    {
        $skip = array_flip(self::STANDARD_B24_KEYS);

        $fieldData = [];
        foreach ($b24Lead as $name => $value) {
            if ($value === null) {
                continue;
            }
            if (isset($skip[$name])) {
                continue;
            }
            if (is_array($value)) {
                $values = [];
                foreach ($value as $entry) {
                    if (is_array($entry)) {
                        if (isset($entry['VALUE'])) {
                            $values[] = (string) $entry['VALUE'];
                        } else {
                            $values[] = json_encode($entry, JSON_UNESCAPED_UNICODE);
                        }
                    } elseif (is_scalar($entry)) {
                        $values[] = (string) $entry;
                    }
                }
                $values = array_values(array_filter($values, fn ($v) => $v !== ''));
                if (empty($values)) {
                    continue;
                }
                $fieldData[] = ['name' => (string) $name, 'values' => $values];
                continue;
            }
            if (is_scalar($value)) {
                $strVal = (string) $value;
                if ($strVal === '') {
                    continue;
                }
                $fieldData[] = ['name' => (string) $name, 'values' => [$strVal]];
            }
        }
        return $fieldData;
    }

    private function importComments(Lead $lead, int $b24LeadId): void
    {
        $comments = $this->client->listTimelineComments($b24LeadId);
        foreach ($comments as $c) {
            $b24CommentId = (int) ($c['ID'] ?? 0);

            // Skip already-imported comments (idempotent re-runs).
            if ($b24CommentId > 0 &&
                LeadComment::where('lead_id', $lead->id)
                    ->where('bitrix24_id', $b24CommentId)
                    ->exists()
            ) {
                continue;
            }

            $authorId = $this->mapBitrixUser($c['AUTHOR_ID'] ?? null) ?? $this->fallbackUserId;
            $body = trim(strip_tags((string) ($c['COMMENT'] ?? '')));
            if ($body === '') {
                continue;
            }
            $createdAt = $this->parseDate($c['CREATED'] ?? null) ?? now();

            $comment = LeadComment::create([
                'lead_id'     => $lead->id,
                'user_id'     => $authorId,
                'comment'     => $body,
                'bitrix24_id' => $b24CommentId ?: null,
                'created_at'  => $createdAt,
                'updated_at'  => $createdAt,
            ]);

            // Mirror LeadActivityController::storeComment so timeline shows imports.
            // LeadHistoryHelper::log($lead->id, [
            //     'action'     => 'comment_added',
            //     'comment_id' => $comment->id,
            //     'comment'    => Str::limit($comment->comment, 50),
            //     'source'     => 'bitrix24',
            //      'created_at'  => $createdAt,
            //     'updated_at'  => $createdAt,
            // ]);
        }
    }

    private function importActivities(Lead $lead, int $b24LeadId): void
    {
        $activities = $this->client->listActivities($b24LeadId);
        foreach ($activities as $a) {
            $b24ActivityId = (int) ($a['ID'] ?? 0);

            if ($b24ActivityId > 0 &&
                LeadActivity::where('lead_id', $lead->id)
                    ->where('bitrix24_id', $b24ActivityId)
                    ->exists()
            ) {
                continue;
            }

            $userId = $this->mapBitrixUser($a['RESPONSIBLE_ID'] ?? $a['AUTHOR_ID'] ?? null) ?? $this->fallbackUserId;
            $title = trim((string) ($a['SUBJECT'] ?? '')) ?: 'Bitrix24 activity';
            $deadline = $this->parseDate(
                $a['DEADLINE']
                ?? $a['START_TIME']
                ?? $a['END_TIME']
                ?? $a['CREATED']
                ?? null
            ) ?? now();
            $completed = (($a['COMPLETED'] ?? 'N') === 'Y');

            $activity = LeadActivity::create([
                'lead_id'       => $lead->id,
                'user_id'       => $userId,
                'title'         => $title,
                'reminder_date' => $deadline,
                'reminders'     => null,
                'is_completed'  => $completed,
                'bitrix24_id'   => $b24ActivityId ?: null,
                'created_at'    => $this->parseDate($a['CREATED'] ?? null) ?? now(),
                'updated_at'    => now(),
            ]);

            // Mirror LeadActivityController::storeActivity.
            // LeadHistoryHelper::log($lead->id, [
            //     'action' => 'activity_created',
            //     'id'     => $activity->id,
            //     'title'  => $activity->title,
            //     'source' => 'bitrix24',
            //     'created_at'    => $this->parseDate($a['CREATED'] ?? null) ?? now(),
            // ]);
        }
    }

    /**
     * On re-import: refresh Bitrix24's "last activity" / "last moved" mirror
     * columns on the existing lead, then move the lead to the stage that
     * matches B24's current STATUS_ID. Logs a 'stage_changed' history entry
     * (same shape as LeadController::changeStage) when the stage actually
     * changes — using B24's MOVED_TIME for the history `created_at` so the
     * timeline reflects when the move happened in Bitrix24, not when we synced.
     */
    private function syncStageForExistingLead(Lead $existing, array $b24Lead): void
    {
        $statusId       = $b24Lead['STATUS_ID'] ?? null;
        $newStageId     = $this->resolveStageId($statusId);
        $newStatusName  = $this->statusName($statusId);
        $oldStageId     = $existing->stage_id;

        $movedAt          = $this->parseDate($b24Lead['MOVED_TIME'] ?? null);
        $lastActivityAt   = $this->parseDate($b24Lead['LAST_ACTIVITY_TIME'] ?? null);
        $lastActivityById = isset($b24Lead['LAST_ACTIVITY_BY'])
            ? (int) $b24Lead['LAST_ACTIVITY_BY']
            : null;
        $movedByLocal     = isset($b24Lead['MOVED_BY_ID'])
            ? $this->mapBitrixUser((int) $b24Lead['MOVED_BY_ID'])
            : null;

        // Refresh activity-mirror columns every re-import so the "last activity"
        // display stays current even when stage hasn't changed.
        $refresh = [];
        if ($movedAt !== null)          $refresh['bitrix24_moved_at'] = $movedAt;
        if ($lastActivityAt !== null)   $refresh['bitrix24_last_activity_at'] = $lastActivityAt;
        if ($lastActivityById !== null) $refresh['bitrix24_last_activity_by_id'] = $lastActivityById;
        if (!empty($refresh)) {
            Lead::withoutEvents(fn () => $existing->update($refresh));
        }

        if ($newStageId === null) {
            return;
        }

        if ($newStageId === $oldStageId) {
            // Same stage, but the human label of STATUS_ID may have shifted.
            if ($newStatusName !== null && $existing->status_lead !== $newStatusName) {
                Lead::withoutEvents(fn () => $existing->update(['status_lead' => $newStatusName]));
            }
            return;
        }

        Lead::withoutEvents(fn () => $existing->update([
            'stage_id'             => $newStageId,
            'status_lead'          => $newStatusName,
            'last_stage_change_at' => $movedAt ?? now(),
        ]));

        $oldStageName = Stage::query()->whereKey($oldStageId)->value('name');
        $newStageName = Stage::query()->whereKey($newStageId)->value('name');

        // History row uses Bitrix24's MOVED_TIME as the timestamp — and the
        // local-mapped MOVED_BY_ID as the actor when available. Bypasses the
        // helper because it would force `created_at = now()` and `user_id =
        // auth()->id()`, neither of which reflect Bitrix24's actual state.
        \App\Models\LeadHistory::create([
            'lead_id'    => $existing->id,
            'user_id'    => $movedByLocal ?? auth()->id() ?? $this->fallbackUserId,
            'changes'    => [
                'action'       => 'stage_changed',
                'old_stage'    => $oldStageName,
                'new_stage'    => $newStageName,
                'old_stage_id' => $oldStageId,
                'new_stage_id' => $newStageId,
                'source'       => 'bitrix24',
            ],
            'created_at' => $movedAt ?? now(),
            'updated_at' => $movedAt ?? now(),
        ]);
    }

    /**
     * Resolve a local Stage by Bitrix24 STATUS_ID's human name. Tries, in
     * order:
     *   1. Exact lowercased name match.
     *   2. Local stage name LIKE %B24-name% (e.g. "New" -> "New Lead").
     *   3. B24-name LIKE %local-stage-name% reverse match (e.g.
     *      "Unqualified Lead" -> "Unqualified").
     *   4. Synonym table for well-known Bitrix24 statuses without an obvious
     *      local equivalent (Junk -> Unqualified, Processed -> Converted, ...).
     *   5. Default stage as last resort, with a log entry so unmapped statuses
     *      are visible.
     */
    private function resolveStageId($statusId): ?int
    {
        $code = $this->normalizeCode($statusId);
        if ($code === null) {
            return $this->defaultStageId;
        }

        $this->ensureFieldMaps();
        $name = $this->statusMap[$code] ?? $code;
        $key = mb_strtolower(trim((string) $name));

        if (array_key_exists($key, $this->stageNameCache)) {
            return $this->stageNameCache[$key];
        }

        $stageId = $this->findStageByName($key, $name);

        if ($stageId === null) {
            Log::info("Bitrix24 status '{$name}' (code {$code}) did not match any local stage — using default.");
            $stageId = $this->defaultStageId;
        }

        $this->stageNameCache[$key] = $stageId;
        return $stageId;
    }

    private function findStageByName(string $lcName, string $rawName): ?int
    {
        // 1. Exact lowercase match.
        $stageId = Stage::query()
            ->where('stage_type', 'lead')
            ->whereRaw('LOWER(name) = ?', [$lcName])
            ->value('id');
        if ($stageId !== null) {
            return $stageId;
        }

        // 2. Local stage name contains the B24 name (e.g. "New" -> "New Lead").
        //    Order by length DESC so longer/more-specific matches win:
        //    e.g. "Qualified" search returns "Qualified" before "Unqualified".
        //    (We also pre-filter rows that contain `qualified` here but the
        //    most-specific *matching* candidate is what we want.)
        $stageId = Stage::query()
            ->where('stage_type', 'lead')
            ->where('name', 'LIKE', '%' . $rawName . '%')
            ->orderByRaw('LENGTH(name) DESC')
            ->orderBy('order')
            ->value('id');
        if ($stageId !== null) {
            return $stageId;
        }

        // 3. Synonym table BEFORE reverse-substring — synonyms are explicit
        //    and won't mis-fire on substring collisions
        //    ("Disqualified" -> "unqualified" keyword instead of accidentally
        //    matching "Qualified" via reverse-substring).
        foreach (self::STATUS_NAME_SYNONYMS as $syn => $localKeyword) {
            if (mb_strpos($lcName, $syn) !== false) {
                $stageId = Stage::query()
                    ->where('stage_type', 'lead')
                    ->where('name', 'LIKE', '%' . $localKeyword . '%')
                    ->orderByRaw('LENGTH(name) DESC')
                    ->orderBy('order')
                    ->value('id');
                if ($stageId !== null) {
                    return $stageId;
                }
            }
        }

        // 4. Reverse — B24 name contains a local stage name. Sort by name
        //    length DESC so "Unqualified" (11 chars) wins over "Qualified"
        //    (9 chars) when both are substrings of the same B24 string.
        $candidates = Stage::query()
            ->where('stage_type', 'lead')
            ->get(['id', 'name'])
            ->sortByDesc(fn ($s) => mb_strlen((string) $s->name))
            ->values();
        foreach ($candidates as $candidate) {
            $candidateLc = mb_strtolower((string) $candidate->name);
            if ($candidateLc !== '' && mb_strpos($lcName, $candidateLc) !== false) {
                return $candidate->id;
            }
        }

        return null;
    }

    /** Combine Bitrix24 address parts into one comma-separated address string. */
    private function composeAddress(array $b24): ?string
    {
        $parts = [
            $b24['ADDRESS'] ?? null,
            $b24['ADDRESS_2'] ?? null,
            $b24['ADDRESS_CITY'] ?? null,
            $b24['ADDRESS_PROVINCE'] ?? null,
            $b24['ADDRESS_REGION'] ?? null,
            $b24['ADDRESS_POSTAL_CODE'] ?? null,
            $b24['ADDRESS_COUNTRY'] ?? null,
        ];
        $parts = array_filter(
            array_map(fn ($p) => trim((string) ($p ?? '')), $parts),
            fn ($p) => $p !== ''
        );
        return $parts ? implode(', ', $parts) : null;
    }

        private function cleanRichText($value): ?string
        {
            if (empty($value)) {
                return null;
            }

            $value = (string) $value;

            // ✅ حل مشكلة url
            $value = preg_replace('/\[url=(.*?)\](.*?)\[\/url\]/i', '$2', $value);
            $value = preg_replace('/\[url\](.*?)\[\/url\]/i', '$1', $value);

            // line breaks
            $value = preg_replace('/<br\s*\/?>/i', "\n", $value);

            // remove HTML
            $value = strip_tags($value);

            // ✅ أهم سطر (بيحل مشكلة الكومة ,)
            $value = trim($value, " \t\n\r\0\x0B,;");

            return $value !== '' ? $value : null;
        }
    /** Translate Bitrix24 STATUS_ID (e.g. "NEW") -> human label (e.g. "New"). */
    private function statusName($statusId): ?string
    {
        $key = $this->normalizeCode($statusId);
        if ($key === null) {
            return null;
        }
        $this->ensureFieldMaps();
        return $this->statusMap[$key] ?? $key;
    }

    /** Translate Bitrix24 SOURCE_ID -> human label. */
    private function sourceName($sourceId): ?string
    {
        $key = $this->normalizeCode($sourceId);
        if ($key === null) {
            return null;
        }
        $this->ensureFieldMaps();
        return $this->sourceMap[$key] ?? $key;
    }

    /**
     * Cached Bitrix24 lead-source map (STATUS_ID => NAME). Cache key persists
     * across job chunks and re-runs — first chunk hits Bitrix24 once, all
     * subsequent chunks read from cache for 1 hour. Distinct from the per-
     * instance $sourceMap so 30k-lead sync chains don't re-fetch every page.
     */
    public function getLeadSources(): array
    {
        return Cache::remember('bitrix_lead_sources', 3600, function () {
            try {
                $r = $this->client->call('crm.status.list', [
                    'filter' => ['ENTITY_ID' => 'SOURCE'],
                ]);
            } catch (\Throwable $e) {
                Log::warning('Bitrix24 getLeadSources failed: ' . $e->getMessage());
                return [];
            }

            $mapped = [];
            foreach ($r['result'] ?? [] as $item) {
                $code = $item['STATUS_ID'] ?? null;
                if ($code === null || $code === '') {
                    continue;
                }
                $name = $item['NAME'] ?? $item['NAME_INIT'] ?? null;
                $mapped[(string) $code] = (is_string($name) && trim($name) !== '')
                    ? trim($name)
                    : (string) $code;
            }
            return $mapped;
        });
    }

    /**
     * Resolve a Bitrix24 SOURCE_ID to its human label using the cached map.
     * Falls back to the raw code when not found (so we never store an empty
     * lead_source for a source that exists in Bitrix24 but isn't in
     * crm.status.list — e.g. webform sources like "WEBFORM_15").
     */
    public function resolveLeadSource(?string $sourceId): ?string
    {
        if ($sourceId === null || $sourceId === '') {
            return null;
        }
        $sources = $this->getLeadSources();
        return $sources[$sourceId] ?? $sourceId;
    }

    /**
     * Lazily load BOTH the STATUS and SOURCE enum maps from Bitrix24.
     * Tries crm.lead.fields first (returns items inline) and falls back to
     * crm.status.list per-entity. Both calls fail silently in different
     * environments; whichever succeeds wins.
     */
    private function ensureFieldMaps(): void
    {
        if ($this->statusMap !== null && $this->sourceMap !== null) {
            return;
        }

        // Primary: one call returns both enums inline.
        try {
            $r = $this->client->call('crm.lead.fields', []);
            $fields = $r['result'] ?? [];
            if ($this->statusMap === null) {
                $items = $fields['STATUS_ID']['items'] ?? [];
                if (is_array($items) && count($items) > 0) {
                    $this->statusMap = $this->extractEnumItems($items);
                }
            }
            if ($this->sourceMap === null) {
                $items = $fields['SOURCE_ID']['items'] ?? [];
                if (is_array($items) && count($items) > 0) {
                    $this->sourceMap = $this->extractEnumItems($items);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Bitrix24 crm.lead.fields failed: ' . $e->getMessage());
        }

        // Fallback: query crm.status.list per missing entity.
        if ($this->statusMap === null) {
            $this->statusMap = $this->loadStatusMap('STATUS');
        }
        if ($this->sourceMap === null) {
            $this->sourceMap = $this->loadStatusMap('SOURCE');
        }
    }

    private function loadStatusMap(string $entityId): array
    {
        try {
            $r = $this->client->call('crm.status.list', [
                'filter' => ['ENTITY_ID' => $entityId],
                'order'  => ['SORT' => 'ASC'],
            ]);
        } catch (\Throwable $e) {
            Log::warning("Bitrix24 crm.status.list({$entityId}) failed: " . $e->getMessage());
            return [];
        }

        $map = [];
        foreach ($r['result'] ?? [] as $entry) {
            $key = $this->normalizeCode($entry['STATUS_ID'] ?? null);
            if ($key === null) {
                continue;
            }
            $name = $entry['NAME'] ?? $entry['NAME_INIT'] ?? null;
            $map[$key] = (is_string($name) && trim($name) !== '') ? trim($name) : $key;
        }

        if (empty($map)) {
            Log::info("Bitrix24 crm.status.list({$entityId}) returned no items.");
        }
        return $map;
    }

    /** crm.lead.fields enum format: [{ "ID": "WEB", "VALUE": "Website" }, ...] */
    private function extractEnumItems(array $items): array
    {
        $map = [];
        foreach ($items as $item) {
            $key = $this->normalizeCode($item['ID'] ?? $item['STATUS_ID'] ?? null);
            if ($key === null) {
                continue;
            }
            $value = $item['VALUE'] ?? $item['NAME'] ?? null;
            $map[$key] = (is_string($value) && trim($value) !== '') ? trim($value) : $key;
        }
        return $map;
    }

    /** Bitrix24 SOURCE_ID/STATUS_ID can come back as int or string; standardize on string. */
    private function normalizeCode($value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (!is_string($value)) {
            return null;
        }
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    private function mapBitrixUser($b24UserId): ?int
    {
        if (!$b24UserId) {
            return null;
        }
        $b24UserId = (int) $b24UserId;
        if (array_key_exists($b24UserId, $this->userCache)) {
            return $this->userCache[$b24UserId];
        }

        $remote = $this->client->getUser($b24UserId);
        $email = $remote['EMAIL'] ?? null;
        $localId = null;
        if ($email) {
            $localId = User::where('email', $email)->value('id');
        }
        $this->userCache[$b24UserId] = $localId;
        return $localId;
    }

    private function collectMultifield($field): array
    {
        if (!is_array($field)) {
            return [];
        }
        $out = [];
        foreach ($field as $entry) {
            if (is_array($entry) && !empty($entry['VALUE'])) {
                $out[] = $entry['VALUE'];
            } elseif (is_string($entry) && $entry !== '') {
                $out[] = $entry;
            }
        }
        return $out;
    }

    private function parseDate($v): ?Carbon
    {
        if (!$v) {
            return null;
        }
        try {
            return Carbon::parse($v);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function formatDateTime(?Carbon $dt): ?string
    {
        return $dt?->format('Y-m-d H:i:s');
    }

    private function numericOrNull($v)
    {
        if ($v === null || $v === '') {
            return null;
        }
        return is_numeric($v) ? (float) $v : null;
    }

    /**
     * Build attributes for a new local lead row (no DB write).
     */
    public function prepareLeadRow(array $b24Lead, ?int $b24Id = null): array
    {
        $b24Id = $b24Id ?? (int) ($b24Lead['ID'] ?? 0);

        $emails = $this->collectMultifield($b24Lead['EMAIL'] ?? []);
        $phones = $this->collectMultifield($b24Lead['PHONE'] ?? []);
        $webs   = $this->collectMultifield($b24Lead['WEB'] ?? []);
        $ims    = $this->collectMultifield($b24Lead['IM'] ?? []);

        $addedBy = $this->mapBitrixUser($b24Lead['CREATED_BY_ID'] ?? null);
        $responsible = $this->mapBitrixUser($b24Lead['ASSIGNED_BY_ID'] ?? null) ?? $addedBy;

        $createdAt = $this->parseDate($b24Lead['DATE_CREATE'] ?? null);
        $updatedAt = $this->parseDate($b24Lead['DATE_MODIFY'] ?? null);
        $birthDate = $this->parseDate($b24Lead['BIRTHDATE'] ?? null);

        $title = trim((string) ($b24Lead['TITLE'] ?? ''));
        $nameParts = trim(trim((string) ($b24Lead['NAME'] ?? '')) . ' ' . trim((string) ($b24Lead['LAST_NAME'] ?? '')));
        $leadName = $nameParts !== '' ? $nameParts : ($title !== '' ? $title : ('Bitrix24 Lead #' . $b24Id));

        $firstName = trim((string) ($b24Lead['NAME'] ?? ''));
        if ($firstName === '') {
            $firstName = explode(' ', $leadName)[0] ?? 'Unknown';
        }

        $statusId = $b24Lead['STATUS_ID'] ?? null;
        $suffix = substr(md5((string) $b24Id), 0, 4);
        $created = $createdAt ?? now();
        $updated = $updatedAt ?? now();

        return [
            'bitrix24_id'                   => $b24Id ?: null,
            'lead_name'                     => $title !== '' ? $title : $leadName,
            'first_name'                    => $firstName,
            'second_name'                   => $b24Lead['SECOND_NAME'] ?? null,
            'last_name'                     => $b24Lead['LAST_NAME'] ?? null,
            'salutation'                    => $b24Lead['HONORIFIC'] ?? null,
            'date_of_birth'                 => $birthDate?->toDateString(),
            'email'                         => $emails[0] ?? null,
            'secondary_email'               => $emails[1] ?? null,
            'work_phone'                    => $phones[0] ?? null,
            'work_phone_2'                  => $phones[1] ?? null,
            'website'                       => $webs[0] ?? null,
            'messenger'                     => $ims[0] ?? null,
            'address'                       => $this->composeAddress($b24Lead),
            'company_name'                  => $b24Lead['COMPANY_TITLE'] ?? null,
            'position'                      => $b24Lead['POST'] ?? null,
            'lead_source'                   => $this->resolveLeadSource($b24Lead['SOURCE_ID'] ?? null) ?? 'Bitrix24',
            'source_information'            => $b24Lead['SOURCE_DESCRIPTION'] ?? null,
            'more_information'              => $this->cleanRichText($b24Lead['COMMENTS'] ?? null),
            'status_lead'                   => $this->statusName($statusId),
            'budget'                        => $this->numericOrNull($b24Lead['OPPORTUNITY'] ?? null),
            'added_by'                      => $addedBy ?? $this->fallbackUserId,
            'responsible_person_id'         => $responsible ?? $this->fallbackUserId,
            'initial_responsible_person_id' => $responsible ?? $this->fallbackUserId,
            'stage_id'                      => $this->resolveStageId($statusId),
            'last_stage_change_at'          => $created->format('Y-m-d H:i:s'),
            'lead_number'                   => $this->generateUniqueLeadNumber($b24Id),
            'raw_meta_data'                 => json_encode(
                ['field_data' => $this->buildFieldData($b24Lead)],
                JSON_UNESCAPED_UNICODE
            ),
            'bitrix24_data'                 => json_encode(
                $b24Lead + ['_users' => $this->collectUserInfo($b24Lead)],
                JSON_UNESCAPED_UNICODE
            ),
            'bitrix24_last_activity_at'     => $this->formatDateTime($this->parseDate($b24Lead['LAST_ACTIVITY_TIME'] ?? null)),
            'bitrix24_moved_at'             => $this->formatDateTime($this->parseDate($b24Lead['MOVED_TIME'] ?? null)),
            'bitrix24_last_activity_by_id'  => isset($b24Lead['LAST_ACTIVITY_BY']) ? (int) $b24Lead['LAST_ACTIVITY_BY'] : null,
            'bitrix24_assigned_user_id'     => isset($b24Lead['ASSIGNED_BY_ID']) ? (int) $b24Lead['ASSIGNED_BY_ID'] : null,
            'created_at'                    => $created->format('Y-m-d H:i:s'),
            'updated_at'                    => $updated->format('Y-m-d H:i:s'),
        ];
    }
    private function generateUniqueLeadNumber(int $b24Id): string
{
    // استخدام جزء من الـ ID مع time() لضمان التفرد
    $suffix = substr(md5($b24Id . time() . rand(1000, 9999)), 0, 6);
    return 'B24-' . $b24Id . '-' . $suffix;
}

// ثم في prepareLeadRow:

    /**
     * High-throughput batch import for queue sync (bulk insert + batched dedup).
     *
     * @return array{new: int, existing: int, errors: int, processed: int}
     */
    /**
     * @param  callable(array{new: int, existing: int, errors: int, processed: int}): void|null  $onProgress
     */
    public function importBatch(array $b24Leads, bool $skipExisting, ?bool $enrich = null, ?callable $onProgress = null): array
    {
        $stats = ['new' => 0, 'existing' => 0, 'errors' => 0, 'processed' => 0];
        $reportEvery = (int) config('bitrix24.progress_report_every', 5);

        if (empty($b24Leads)) {
            return $stats;
        }

        $enrich = $enrich ?? (
            !$skipExisting && (bool) config('bitrix24.enrich_existing', true)
        );

        $fastSkip = $skipExisting && (bool) config('bitrix24.fast_skip_existing', true);

        $ids = array_values(array_filter(
            array_map(fn ($l) => (int) ($l['ID'] ?? 0), $b24Leads),
            fn ($v) => $v > 0
        ));

        $existingMap = $this->preloadExistingBitrixIds($ids);
        $toInsert = [];
        $toUpdate = [];

        foreach ($b24Leads as $b24) {
            $b24Id = (int) ($b24['ID'] ?? 0);
            if ($b24Id <= 0) {
                continue;
            }

            if ($skipExisting && isset($existingMap[$b24Id])) {
                $stats['existing']++;
                $stats['processed']++;
                $this->reportImportProgress($stats, $onProgress, $reportEvery);
                continue;
            }

            if (isset($existingMap[$b24Id])) {
                $toUpdate[] = ['b24' => $b24, 'local_id' => (int) $existingMap[$b24Id]];
            } else {
                $toInsert[] = $b24;
            }
        }

        if (!empty($toInsert) && $fastSkip) {
            $inserted = $this->bulkInsertLeads($toInsert);
            $stats['new'] += $inserted;
            $stats['processed'] += $inserted;
            $this->reportImportProgress($stats, $onProgress, 1);
        } elseif (!empty($toInsert)) {
            foreach ($toInsert as $b24) {
                try {
                    $r = $this->importOne($b24);
                    if ($r['created']) {
                        $stats['new']++;
                    } else {
                        $stats['existing']++;
                    }
                    $stats['processed']++;
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    $stats['processed']++;
                    Log::error('Bitrix24 importBatch insert failed', [
                        'bitrix24_id' => (int) ($b24['ID'] ?? 0),
                        'error'       => $e->getMessage(),
                    ]);
                }
                $this->reportImportProgress($stats, $onProgress, $reportEvery);
            }
        }

        foreach ($toUpdate as $item) {
            $stats['processed']++;
            try {
                if ($enrich) {
                    $this->importOne($item['b24']);
                    $stats['existing']++;
                } else {
                    $lead = Lead::find($item['local_id']);
                    if ($lead) {
                        $this->syncStageForExistingLead($lead, $item['b24']);
                        $stats['existing']++;
                    }
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                Log::error('Bitrix24 importBatch update failed', [
                    'bitrix24_id' => (int) ($item['b24']['ID'] ?? 0),
                    'error'       => $e->getMessage(),
                ]);
            }
            $this->reportImportProgress($stats, $onProgress, $reportEvery);
        }

        $this->reportImportProgress($stats, $onProgress, 1);

        return $stats;
    }

    private function reportImportProgress(array $stats, ?callable $onProgress, int $every): void
    {
        if (!$onProgress || $every < 1) {
            return;
        }
        if ($stats['processed'] % $every !== 0 && $stats['processed'] > 0) {
            return;
        }
        $onProgress($stats);
    }

    /**
     * @return array<int, int> bitrix24_id => local lead id
     */
    public function preloadExistingBitrixIds(array $bitrixIds): array
    {
        if (empty($bitrixIds)) {
            return [];
        }

        $map = [];
        foreach (array_chunk(array_unique($bitrixIds), 2000) as $chunk) {
            $rows = DB::table('leads')
                ->whereIn('bitrix24_id', $chunk)
                ->pluck('id', 'bitrix24_id');
            foreach ($rows as $b24Id => $localId) {
                $map[(int) $b24Id] = (int) $localId;
            }
        }

        return $map;
    }

    /**
     * Bulk insert new leads via query builder (skips Eloquent events/jobs).
     */
    public function bulkInsertLeads(array $b24Leads): int
    {
        if (empty($b24Leads)) {
            return 0;
        }

        $chunkSize = \App\Services\Bitrix24\Bitrix24SyncThrottler::resolveDbInsertChunk(
            (int) config('bitrix24.db_insert_chunk', 500)
        );
        $inserted = 0;
        $historyRows = [];
        $now = now();

        foreach (array_chunk($b24Leads, $chunkSize) as $chunk) {
            $rows = [];
            $b24Ids = [];

            foreach ($chunk as $b24) {
                $b24Id = (int) ($b24['ID'] ?? 0);
                if ($b24Id <= 0) {
                    continue;
                }
                $rows[] = $this->prepareLeadRow($b24, $b24Id);
                $b24Ids[] = $b24Id;
            }

            if (empty($rows)) {
                continue;
            }

            DB::table('leads')->insert($rows);
            $inserted += count($rows);

            $idMap = DB::table('leads')
                ->whereIn('bitrix24_id', $b24Ids)
                ->pluck('id', 'bitrix24_id');

            foreach ($idMap as $b24Id => $leadId) {
                $historyRows[] = [
                    'lead_id'     => (int) $leadId,
                    'user_id'     => $this->fallbackUserId,
                    'bitrix24_id' => null,
                    'changes'     => json_encode([
                        'action'      => 'created',
                        'source'      => 'bitrix24',
                        'bitrix24_id' => (int) $b24Id,
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }

            if (count($historyRows) >= $chunkSize) {
                DB::table('lead_histories')->insert($historyRows);
                $historyRows = [];
            }

            unset($rows, $b24Ids, $idMap);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        if (!empty($historyRows)) {
            DB::table('lead_histories')->insert($historyRows);
        }

        return $inserted;
    }

    // أضف هذه الدالة لفحص الـ timeline مباشرة
public function debugTimeline(int $b24LeadId): void
{
    try {
        $items = $this->client->listTimelineItems($b24LeadId);
        Log::info("Debug timeline for lead {$b24LeadId}", [
            'count' => count($items),
            'items' => $items
        ]);
        
        foreach ($items as $item) {
            // ابحث عن أي عنصر يحتوي على STATUS_ID
            $itemJson = json_encode($item);
            if (strpos($itemJson, 'STATUS_ID') !== false) {
                Log::info("Found STATUS_ID in timeline item", ['item' => $item]);
            }
        }
    } catch (\Throwable $e) {
        Log::error("Debug timeline failed: " . $e->getMessage());
    }
}
  public function importFullTimeline(Lead $lead, int $b24LeadId): void
    {
        Log::info("Starting full timeline import for lead {$b24LeadId}");
        
        // 1. جلب التايم لاين من bindings
        $this->importTimelineFromBindings($lead, $b24LeadId);
        
        // 2. جلب التايم لاين من log messages
        $this->importTimelineFromLogMessages($lead, $b24LeadId);
        
        // 3. جلب التايم لاين من synthetic data (MOVED_TIME, DATE_MODIFY, etc.)
        $this->importTimelineFromSyntheticData($lead, $b24LeadId);
        
        Log::info("Completed full timeline import for lead {$b24LeadId}");
    }

    /**
     * جلب التايم لاين من crm.timeline.bindings.list
     * هذه الـ API متوفرة في بيئتك حسب الـ logs
     */
    private function importTimelineFromBindings(Lead $lead, int $b24LeadId): void
    {
        if ($this->timelineBindingsUnsupported) {
            Log::info("Timeline bindings skipped for lead {$b24LeadId} - API not supported");
            return;
        }
        
        try {
            $bindings = $this->client->listTimelineBindings($b24LeadId);
            Log::info("Found " . count($bindings) . " timeline bindings for lead {$b24LeadId}");
            
            foreach ($bindings as $binding) {
                $this->storeBindingAsHistory($lead, $binding);
            }
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Could not find description') || str_contains($msg, 'Method not found')) {
                $this->timelineBindingsUnsupported = true;
                Log::info("Timeline bindings API not available on this portal");
            } else {
                Log::warning("Failed to fetch timeline bindings for lead {$b24LeadId}: " . $msg);
            }
        }
    }

    /**
     * تخزين binding واحد في جدول الـ history
     */
    private function storeBindingAsHistory(Lead $lead, array $binding): void
    {
        $timelineId = (int) ($binding['TIMELINE_ID'] ?? $binding['ID'] ?? 0);
        if ($timelineId <= 0) {
            return;
        }
        
        // استخدام synthetic ID لتجنب التكرار
        $synthId = 9_000_000_000_000_000 + $timelineId;
        
        // التحقق من عدم التكرار
        if (LeadHistory::where('lead_id', $lead->id)->where('bitrix24_id', $synthId)->exists()) {
            return;
        }
        
        $createdAt = $this->parseDate($binding['CREATED'] ?? null) ?? now();
        $authorId = $this->mapBitrixUser($binding['AUTHOR_ID'] ?? null) ?? $this->fallbackUserId;
        $typeCode = $this->normalizeCode($binding['TYPE_ID'] ?? $binding['TYPE'] ?? null);
        
        // تحديد نوع الحدث بناءً على TYPE_ID
        $changes = $this->classifyBindingEvent($binding, $typeCode);
        
        LeadHistory::create([
            'lead_id'     => $lead->id,
            'user_id'     => $authorId,
            'bitrix24_id' => $synthId,
            'changes'     => $changes,
            'created_at'  => $createdAt,
            'updated_at'  => $createdAt,
        ]);
        
        Log::info("Stored timeline binding as history for lead {$lead->id}, type: {$typeCode}");
    }

    /**
     * تصنيف حدث binding (تغيير مرحلة، تعيين مسؤول، الخ)
     */
    private function classifyBindingEvent(array $binding, ?string $typeCode): array
    {
        // التحقق إذا كان تغيير مرحلة
        if ($typeCode === '2' || $typeCode === 'modification' || str_contains($typeCode ?? '', 'status')) {
            // محاولة استخراج الـ status من البيانات
            $settings = $binding['SETTINGS'] ?? $binding['additionalData'] ?? [];
            
            // إذا وجدنا STATUS_ID في الإعدادات
            if (isset($settings['STATUS_ID']) || isset($settings['STATUS_FROM']) || isset($settings['STATUS_TO'])) {
                $oldStatus = $settings['STATUS_FROM'] ?? $settings['OLD_STATUS'] ?? null;
                $newStatus = $settings['STATUS_TO'] ?? $settings['STATUS_ID'] ?? null;
                
                return [
                    'action'       => 'stage_changed',
                    'old_stage'    => $oldStatus ? ($this->statusName($oldStatus) ?? $oldStatus) : null,
                    'new_stage'    => $newStatus ? ($this->statusName($newStatus) ?? $newStatus) : null,
                    'old_stage_id' => $oldStatus ? $this->resolveStageId($oldStatus) : null,
                    'new_stage_id' => $newStatus ? $this->resolveStageId($newStatus) : null,
                    'source'       => 'bitrix24',
                    'origin'       => 'timeline.bindings.list',
                ];
            }
        }
        
        // التحقق إذا كان تغيير مسؤول
        if ($typeCode === '3' || $typeCode === 'assignment') {
            return [
                'action'     => 'assigned',
                'old_person' => $binding['OLD_ASSIGNED'] ?? null,
                'new_person' => $binding['NEW_ASSIGNED'] ?? null,
                'source'     => 'bitrix24',
                'origin'     => 'timeline.bindings.list',
            ];
        }
        
        // حدث عام
        return [
            'action' => 'timeline_item',
            'type'   => $typeCode,
            'title'  => $binding['TITLE'] ?? 'Bitrix24 timeline event',
            'data'   => $binding,
            'source' => 'bitrix24',
            'origin' => 'timeline.bindings.list',
        ];
    }

    /**
     * جلب التايم لاين من crm.timeline.logmessage.list
     */
    private function importTimelineFromLogMessages(Lead $lead, int $b24LeadId): void
    {
        if ($this->timelineLogMessagesUnsupported) {
            return;
        }
        
        try {
            $messages = $this->client->listTimelineLogMessages($b24LeadId);
            Log::info("Found " . count($messages) . " timeline log messages for lead {$b24LeadId}");
            
            foreach ($messages as $message) {
                $this->storeLogMessageAsHistory($lead, $message);
            }
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Could not find description') || str_contains($msg, 'Method not found')) {
                $this->timelineLogMessagesUnsupported = true;
                Log::info("Timeline log messages API not available on this portal");
            } else {
                Log::warning("Failed to fetch timeline log messages for lead {$b24LeadId}: " . $msg);
            }
        }
    }

    /**
     * تخزين log message في جدول الـ history
     */
    private function storeLogMessageAsHistory(Lead $lead, array $message): void
    {
        $messageId = (int) ($message['id'] ?? $message['ID'] ?? 0);
        if ($messageId <= 0) {
            return;
        }
        
        $synthId = 10_000_000_000_000_000 + $messageId;
        
        if (LeadHistory::where('lead_id', $lead->id)->where('bitrix24_id', $synthId)->exists()) {
            return;
        }
        
        $createdAt = $this->parseDate($message['createdTime'] ?? $message['CREATED'] ?? null) ?? now();
        $authorId = $this->mapBitrixUser($message['authorId'] ?? $message['AUTHOR_ID'] ?? null) ?? $this->fallbackUserId;
        $title = trim((string) ($message['title'] ?? $message['TITLE'] ?? 'Bitrix24 log message'));
        $typeCode = $this->normalizeCode($message['type'] ?? $message['TYPE'] ?? null);
        
        // محاولة استخراج معلومات تغيير المرحلة من الـ log message
        $changes = $this->extractStageChangeFromLogMessage($message, $typeCode, $title);
        
        LeadHistory::create([
            'lead_id'     => $lead->id,
            'user_id'     => $authorId,
            'bitrix24_id' => $synthId,
            'changes'     => $changes,
            'created_at'  => $createdAt,
            'updated_at'  => $createdAt,
        ]);
        
        Log::info("Stored timeline log message as history for lead {$lead->id}");
    }

    /**
     * استخراج تغيير المرحلة من log message
     */
    private function extractStageChangeFromLogMessage(array $message, ?string $typeCode, string $title): array
    {
        // البحث عن STATUS في محتوى الرسالة
        $content = json_encode($message);
        
        if (strpos($content, 'STATUS_ID') !== false || strpos($content, 'STATUS') !== false) {
            // محاولة استخراج الـ status من الإعدادات
            $settings = $message['settings'] ?? $message['SETTINGS'] ?? $message['additionalData'] ?? [];
            
            $oldStatus = $settings['STATUS_FROM'] ?? $settings['OLD_STATUS'] ?? null;
            $newStatus = $settings['STATUS_TO'] ?? $settings['STATUS_ID'] ?? $settings['NEW_STATUS'] ?? null;
            
            if ($oldStatus || $newStatus) {
                return [
                    'action'       => 'stage_changed',
                    'old_stage'    => $oldStatus ? ($this->statusName($oldStatus) ?? $oldStatus) : null,
                    'new_stage'    => $newStatus ? ($this->statusName($newStatus) ?? $newStatus) : null,
                    'old_stage_id' => $oldStatus ? $this->resolveStageId($oldStatus) : null,
                    'new_stage_id' => $newStatus ? $this->resolveStageId($newStatus) : null,
                    'source'       => 'bitrix24',
                    'origin'       => 'timeline.logmessage.list',
                ];
            }
        }
        
        return [
            'action' => 'timeline_item',
            'type'   => $typeCode,
            'title'  => $title,
            'data'   => $message,
            'source' => 'bitrix24',
            'origin' => 'timeline.logmessage.list',
        ];
    }

    /**
     * جلب التايم لاين من البيانات الأساسية للـ Lead (MOVED_TIME, DATE_MODIFY)
     * هذا الحل يعمل دائماً لأنه يستخدم البيانات المتوفرة في crm.lead.get
     */
    private function importTimelineFromSyntheticData(Lead $lead, int $b24LeadId): void
    {
        try {
            // جلب بيانات الـ Lead كاملة من Bitrix24
            $response = $this->client->call('crm.lead.get', ['id' => $b24LeadId]);
            $b24Lead = $response['result'] ?? [];
            
            if (empty($b24Lead)) {
                Log::warning("Could not fetch lead data for synthetic timeline, lead_id: {$b24LeadId}");
                return;
            }
            
            // 1. تسجيل تغيير المرحلة من MOVED_TIME
            $movedTime = $this->parseDate($b24Lead['MOVED_TIME'] ?? null);
            $statusId = $b24Lead['STATUS_ID'] ?? null;
            
            if ($movedTime !== null && $statusId !== null) {
                $stageId = $this->resolveStageId($statusId);
                $stageName = $this->statusName($statusId);
                $userId = $this->mapBitrixUser($b24Lead['MOVED_BY_ID'] ?? null) ?? $this->fallbackUserId;
                
                $syntheticId = $this->generateSyntheticId($b24LeadId, 'MOVED_TIME', $movedTime);
                
                if (!LeadHistory::where('lead_id', $lead->id)->where('bitrix24_id', $syntheticId)->exists()) {
                    LeadHistory::create([
                        'lead_id'     => $lead->id,
                        'user_id'     => $userId,
                        'bitrix24_id' => $syntheticId,
                        'changes'     => [
                            'action'       => 'stage_changed',
                            'new_stage'    => $stageName,
                            'new_stage_id' => $stageId,
                            'old_stage'    => null, // لا نعرف الـ stage القديم من هذه البيانات
                            'old_stage_id' => null,
                            'source'       => 'bitrix24',
                            'origin'       => 'lead.get.MOVED_TIME',
                            'moved_at'     => $movedTime->toDateTimeString(),
                        ],
                        'created_at'  => $movedTime,
                        'updated_at'  => $movedTime,
                    ]);
                    
                    Log::info("Stored synthetic stage change from MOVED_TIME for lead {$lead->id}: {$stageName}");
                }
            }
            
            // 2. تسجيل آخر تعديل من DATE_MODIFY
            $modifyTime = $this->parseDate($b24Lead['DATE_MODIFY'] ?? null);
            $createTime = $this->parseDate($b24Lead['DATE_CREATE'] ?? null);
            
            if ($modifyTime !== null && ($createTime === null || !$modifyTime->equalTo($createTime))) {
                $userId = $this->mapBitrixUser($b24Lead['MODIFY_BY_ID'] ?? null) ?? $this->fallbackUserId;
                $syntheticId = $this->generateSyntheticId($b24LeadId, 'DATE_MODIFY', $modifyTime);
                
                if (!LeadHistory::where('lead_id', $lead->id)->where('bitrix24_id', $syntheticId)->exists()) {
                    LeadHistory::create([
                        'lead_id'     => $lead->id,
                        'user_id'     => $userId,
                        'bitrix24_id' => $syntheticId,
                        'changes'     => [
                            'action' => 'updated',
                            'source' => 'bitrix24',
                            'origin' => 'lead.get.DATE_MODIFY',
                            'modified_at' => $modifyTime->toDateTimeString(),
                        ],
                        'created_at'  => $modifyTime,
                        'updated_at'  => $modifyTime,
                    ]);
                }
            }
            
            // 3. تسجيل آخر نشاط من LAST_ACTIVITY_TIME
            $lastActivity = $this->parseDate($b24Lead['LAST_ACTIVITY_TIME'] ?? null);
            if ($lastActivity !== null && ($modifyTime === null || !$lastActivity->equalTo($modifyTime))) {
                $userId = $this->mapBitrixUser($b24Lead['LAST_ACTIVITY_BY'] ?? null) ?? $this->fallbackUserId;
                $syntheticId = $this->generateSyntheticId($b24LeadId, 'LAST_ACTIVITY_TIME', $lastActivity);
                
                if (!LeadHistory::where('lead_id', $lead->id)->where('bitrix24_id', $syntheticId)->exists()) {
                    LeadHistory::create([
                        'lead_id'     => $lead->id,
                        'user_id'     => $userId,
                        'bitrix24_id' => $syntheticId,
                        'changes'     => [
                            'action' => 'timeline_item',
                            'type'   => 'last_activity',
                            'title'  => 'Last activity recorded on Bitrix24',
                            'source' => 'bitrix24',
                            'origin' => 'lead.get.LAST_ACTIVITY_TIME',
                            'activity_at' => $lastActivity->toDateTimeString(),
                        ],
                        'created_at'  => $lastActivity,
                        'updated_at'  => $lastActivity,
                    ]);
                }
            }
            
            Log::info("Completed synthetic timeline import for lead {$lead->id}");
            
        } catch (\Throwable $e) {
            Log::error("Failed to import synthetic timeline for lead {$lead->id}: " . $e->getMessage());
        }
    }

    /**
     * توليد ID فريد للـ synthetic timeline entries
     */
    private function generateSyntheticId(int $b24LeadId, string $eventKey, Carbon $timestamp): int
    {
        $hash = crc32($b24LeadId . '|' . $eventKey . '|' . $timestamp->format('YmdHis'));
        return 8_000_000_000_000_000 + abs($hash);
    }
    public function getLeadComment($leadId)
    {
        return $this->client->call('crm.lead.get', [
            'id' => $leadId
        ])['result']['COMMENTS'] ?? null;
    }
}
