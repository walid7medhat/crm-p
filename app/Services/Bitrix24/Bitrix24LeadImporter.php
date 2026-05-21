<?php

namespace App\Services\Bitrix24;

use App\Helpers\LeadHistoryHelper;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadComment;
use App\Models\Stage;
use App\Models\User;
use Carbon\Carbon;
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

    public function __construct(Bitrix24Client $client, int $fallbackUserId)
    {
        $this->client = $client;
        $this->fallbackUserId = $fallbackUserId;
        $this->defaultStageId = Stage::where('stage_type', 'lead')
            ->orderBy('order', 'asc')
            ->value('id')
            ?? Stage::orderBy('order', 'asc')->value('id');
    }

    public function importOne(array $b24Lead): Lead
    {
        $b24Id = (int) ($b24Lead['ID'] ?? 0);

        $emails = $this->collectMultifield($b24Lead['EMAIL'] ?? []);
        $phones = $this->collectMultifield($b24Lead['PHONE'] ?? []);

        $addedBy = $this->mapBitrixUser($b24Lead['CREATED_BY_ID'] ?? null);
        $responsible = $this->mapBitrixUser($b24Lead['ASSIGNED_BY_ID'] ?? null) ?? $addedBy;

        $createdAt = $this->parseDate($b24Lead['DATE_CREATE'] ?? null);
        $updatedAt = $this->parseDate($b24Lead['DATE_MODIFY'] ?? null);

        $title = trim((string) ($b24Lead['TITLE'] ?? ''));
        $nameParts = trim(trim((string) ($b24Lead['NAME'] ?? '')) . ' ' . trim((string) ($b24Lead['LAST_NAME'] ?? '')));
        $leadName = $nameParts !== '' ? $nameParts : ($title !== '' ? $title : ('Bitrix24 Lead #' . $b24Id));

        $firstName = trim((string) ($b24Lead['NAME'] ?? ''));
        if ($firstName === '') {
            $firstName = explode(' ', $leadName)[0] ?? 'Unknown';
        }

        $attributes = [
            'lead_name'                     => $title !== '' ? $title : $leadName,
            'first_name'                    => $firstName,
            'second_name'                   => $b24Lead['SECOND_NAME'] ?? null,
            'last_name'                     => $b24Lead['LAST_NAME'] ?? null,
            'email'                         => $emails[0] ?? null,
            'secondary_email'               => $emails[1] ?? null,
            'work_phone'                    => $phones[0] ?? null,
            'work_phone_2'                  => $phones[1] ?? null,
            'company_name'                  => $b24Lead['COMPANY_TITLE'] ?? null,
            'position'                      => $b24Lead['POST'] ?? null,
            'lead_source'                   => $b24Lead['SOURCE_ID'] ??  'Bitrix24',
            'source_information'            => $b24Lead['SOURCE_DESCRIPTION'] ?? null,
            'status_lead'                   => $this->statusName($b24Lead['STATUS_ID'] ?? null),
            'budget'                        => $this->numericOrNull($b24Lead['OPPORTUNITY'] ?? null),
            'added_by'                      => $addedBy ?? $this->fallbackUserId,
            'responsible_person_id'         => $responsible ?? $this->fallbackUserId,
            'initial_responsible_person_id' => $responsible ?? $this->fallbackUserId,
            'stage_id'                      => $this->defaultStageId,
            'last_stage_change_at'          => $createdAt ?? now(),
            // suffix avoids unique-constraint collision when the same Bitrix24 lead
            // is imported more than once (per "always create new" choice).
            'lead_number'                   => 'B24-' . $b24Id . '-' . Str::lower(Str::random(4)),
            'raw_meta_data'                 => json_encode($b24Lead, JSON_UNESCAPED_UNICODE),
            'created_at'                    => $createdAt ?? now(),
            'updated_at'                    => $updatedAt ?? now(),
        ];


        $lead = Lead::withoutEvents(fn () => Lead::create($attributes));

        LeadHistoryHelper::log($lead->id, [
            'action'      => 'created',
            'name'        => $lead->lead_name,
            'source'      => 'bitrix24',
            'bitrix24_id' => $b24Id,
        ]);

        $this->importComments($lead, $b24Id);
        $this->importActivities($lead, $b24Id);

        return $lead;
    }

    private function importComments(Lead $lead, int $b24LeadId): void
    {
        $comments = $this->client->listTimelineComments($b24LeadId);
        foreach ($comments as $c) {
            $authorId = $this->mapBitrixUser($c['AUTHOR_ID'] ?? null) ?? $this->fallbackUserId;
            $body = trim(strip_tags((string) ($c['COMMENT'] ?? '')));
            if ($body === '') {
                continue;
            }
            $createdAt = $this->parseDate($c['CREATED'] ?? null) ?? now();

            $comment = LeadComment::create([
                'lead_id'    => $lead->id,
                'user_id'    => $authorId,
                'comment'    => $body,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            // Mirror LeadActivityController::storeComment so timeline shows imports.
            LeadHistoryHelper::log($lead->id, [
                'action'     => 'comment_added',
                'comment_id' => $comment->id,
                'comment'    => Str::limit($comment->comment, 50),
                'source'     => 'bitrix24',
            ]);
        }
    }

    private function importActivities(Lead $lead, int $b24LeadId): void
    {
        $activities = $this->client->listActivities($b24LeadId);
        foreach ($activities as $a) {
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
                'created_at'    => $this->parseDate($a['CREATED'] ?? null) ?? now(),
                'updated_at'    => now(),
            ]);

            // Mirror LeadActivityController::storeActivity.
            LeadHistoryHelper::log($lead->id, [
                'action' => 'activity_created',
                'id'     => $activity->id,
                'title'  => $activity->title,
                'source' => 'bitrix24',
            ]);
        }
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

    private function numericOrNull($v)
    {
        if ($v === null || $v === '') {
            return null;
        }
        return is_numeric($v) ? (float) $v : null;
    }
}
