<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lead search "Inactive Sales": inactive users who still own leads, within the viewer's
 * lead scope. Shared by the select's option list (LeadController::inactiveSales)
 * and the "All Inactive" option (inactive_person_id=all) so both always match.
 */
class InactiveSales
{
    public const ROLES = ['super_admin', 'admin', 'branch_admin', 'manager', 'team_lead'];

    public static function allowed(User $viewer): bool
    {
        return $viewer->hasAnyRole(self::ROLES);
    }

    /** @return Builder<User> */
    public static function query(User $viewer): Builder
    {
        $query = User::query()
            ->where('users.status', '!=', 'active')
            // Only people who still own leads — nothing to search for the others.
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('leads')
                    ->whereColumn('leads.responsible_person_id', 'users.id')
                    ->whereNull('leads.deleted_at');
            });

        // Same lead scope as the board: everyone for super_admin / admin, otherwise the
        // viewer's hierarchy (whole branch for branch_admin / show-branch-leads).
        if (! $viewer->hasAnyRole(['super_admin', 'admin'])) {
            $query->whereIn('users.id', $viewer->leadScopeUserIds());
        }

        return $query;
    }

    /** @return array<int, int> */
    public static function ids(User $viewer): array
    {
        if (! self::allowed($viewer)) {
            return [];
        }

        return self::query($viewer)->pluck('users.id')->map(fn ($id) => (int) $id)->all();
    }

    /** True when the request's inactive_person_id holds the "All Inactive" option. */
    public static function requestsAll(mixed $raw): bool
    {
        $values = is_array($raw) ? $raw : preg_split('/\s*,\s*/', (string) $raw);

        return in_array('all', array_map(fn ($v) => is_array($v) ? '' : strtolower((string) $v), $values), true);
    }
}
