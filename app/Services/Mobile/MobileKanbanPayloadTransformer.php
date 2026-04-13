<?php

namespace App\Services\Mobile;

/**
 * Maps existing LeadResource-shaped arrays to the mobile Kanban card contract.
 */
class MobileKanbanPayloadTransformer
{
    public static function flattenLeadArray(array $l): array
    {
        $rp = $l['responsible_person'] ?? null;
        $assigned = null;
        if (is_array($rp)) {
            $assigned = [
                'id' => $rp['id'] ?? null,
                'name' => $rp['name'] ?? null,
                'email' => $rp['email'] ?? null,
                'avatar' => $rp['avatar'] ?? null,
            ];
        }

        return [
            'id' => $l['id'] ?? null,
            'title' => $l['lead_name'] ?? null,
            'lead_number' => $l['lead_number'] ?? null,
            'stage_id' => $l['stage_id'] ?? null,
            'updated_at' => isset($l['updated_at']) ? self::isoTimestamp($l['updated_at']) : null,
            'created_at' => isset($l['created_at']) ? self::isoTimestamp($l['created_at']) : null,
            'last_stage_change_at' => isset($l['last_stage_change_at']) ? self::isoTimestamp($l['last_stage_change_at']) : null,
            'assigned_user' => $assigned,
            'work_phone' => $l['work_phone'] ?? null,
            'email' => $l['email'] ?? null,
            'status_lead' => $l['status_lead'] ?? null,
            'lead_type' => $l['lead_type'] ?? null,
            'property_status' => $l['property_status'] ?? null,
            'budget' => $l['budget'] ?? null,
            'lead_source' => $l['lead_source'] ?? null,
            'score' => $l['score'] ?? null,
            'priority' => $l['priority'] ?? null,
        ];
    }

    public static function normalizeStageRow(array $stage, array $flatLeads): array
    {
        return [
            'id' => $stage['id'],
            'name' => $stage['name'],
            'order' => $stage['order'],
            'color' => $stage['color'] ?? null,
            'lead_count' => $stage['lead_count'] ?? count($flatLeads),
            'pagination' => $stage['pagination'] ?? null,
            'updated_at' => $stage['updated_at'] ?? null,
            'created_at' => $stage['created_at'] ?? null,
        ];
    }

    private static function isoTimestamp(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_array($value) && isset($value['date'])) {
            return (string) $value['date'];
        }

        return null;
    }
}
