<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Access rules for the Marketing workspace:
 * - permission `marketing` (or super_admin) => into the workspace (route middleware). Admins need
 *   the permission too — the admin role alone does not open Marketing.
 * - inside the workspace, roles admin / super_admin => stage management, deleting cards, daily report
 */
abstract class MarketingBaseController extends Controller
{
    protected function isAdmin(?User $user = null): bool
    {
        $user ??= Auth::user();

        return $user && $user->hasAnyRole(['super_admin', 'admin']);
    }

    /** Active users holding the `marketing` permission (directly or via a role) — the assignee pool. */
    protected function memberQuery()
    {
        return User::permission('marketing')->where('status', 'active');
    }

    /**
     * Validation rule: the id must belong to a marketing member. $alsoAllow keeps ids already
     * saved on a card valid, so editing a card doesn't fail after someone loses the permission.
     */
    protected function memberRule(array $alsoAllow = []): string
    {
        $ids = array_unique(array_merge($this->memberQuery()->pluck('id')->all(), array_filter($alsoAllow)));

        return 'in:' . implode(',', $ids ?: [0]);
    }
}
