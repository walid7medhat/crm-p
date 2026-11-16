<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Lead search "Campaign ID" — super_admin + admin only.
 *
 * Matches a lead when the value is either:
 *  - its Meta ad id (leads.ad_id, saved from the Meta lead webhook), or
 *  - its CRM integration / Meta lead form (leads.integration_id).
 *
 * Accepts one value or a comma list: ?integration_id=120210000000000001 or ?integration_id=12,15
 */
class LeadIntegrationFilter
{
    public static function canUse($user): bool
    {
        return $user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['super_admin', 'admin']);
    }

    /**
     * Requested values as digit strings (Meta ad ids are up to ~20 digits — keep them as text).
     *
     * @return list<string>
     */
    public static function requestedIds(Request $request): array
    {
        $raw = $request->input('integration_id');
        if ($raw === null || $raw === '') {
            return [];
        }

        $parts = is_array($raw) ? $raw : explode(',', (string) $raw);

        $ids = [];
        foreach ($parts as $part) {
            $value = ltrim(trim((string) $part), '0');
            if ($value !== '' && ctype_digit($value)) {
                $ids[$value] = $value;
            }
        }

        return array_values($ids);
    }

    public static function apply($query, Request $request, $user): void
    {
        if (! self::canUse($user)) {
            return;
        }

        $ids = self::requestedIds($request);
        if ($ids === []) {
            return;
        }

        $table = $query->getModel()->getTable();

        // integration_id is an int column: only ids that fit it.
        $integrationIds = array_values(array_filter(
            array_map('intval', array_filter($ids, fn ($id) => strlen($id) <= 10)),
            fn ($id) => $id > 0 && $id <= 4294967295
        ));

        $query->where(function ($q) use ($table, $ids, $integrationIds) {
            $q->whereIn($table.'.ad_id', $ids);
            if ($integrationIds !== []) {
                $q->orWhereIn($table.'.integration_id', $integrationIds);
            }
        });
    }
}
