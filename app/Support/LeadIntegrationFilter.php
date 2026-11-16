<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Lead search "Integration ID" (Meta form / campaign integration) — super_admin + admin only.
 * Accepts one id or a comma list: ?integration_id=12 or ?integration_id=12,15
 */
class LeadIntegrationFilter
{
    public static function canUse($user): bool
    {
        return $user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['super_admin', 'admin']);
    }

    /** @return list<int> */
    public static function requestedIds(Request $request): array
    {
        $raw = $request->input('integration_id');
        if ($raw === null || $raw === '') {
            return [];
        }

        $parts = is_array($raw) ? $raw : explode(',', (string) $raw);

        return array_values(array_unique(array_filter(
            array_map(fn ($v) => (int) trim((string) $v), $parts),
            fn ($v) => $v > 0
        )));
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

        $query->whereIn($query->getModel()->getTable().'.integration_id', $ids);
    }
}
