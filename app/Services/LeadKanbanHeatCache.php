<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Short-lived cache for the kanban heat chip totals only.
 * The board payload itself is never stored.
 *
 * The key always includes the user id, so one user's scope cannot be served
 * to another. A version integer is bumped when a lead change can alter the
 * totals; the TTL covers updates that bypass model events.
 */
class LeadKanbanHeatCache
{
    public const VERSION_KEY = 'leads.kanban.heat.version';

    /**
     * Request inputs read by StageController::scopedLeadsQuery().
     * `heat` is applied after the totals, so it is not part of the key.
     *
     * @var list<string>
     */
    private const FILTER_KEYS = [
        'changed_by',
        'responsible_person_id',
        'inactive_person_id',
        'stage_id',
        'email',
        'work_phone',
        'added_by',
        'created_from',
        'created_to',
        'created_at',
        'source',
        'bedrooms',
        'source_information',
        'closed',
        'lead_name',
        'first_name',
        'lead_branch_source',
        'office_branch',
        'team_id',
        'status_lead',
        'why_lost_lead',
        'lead_type',
        'property_status',
        'budget_from',
        'budget_to',
        'property_type_id',
        'area_id',
        'interaction_result',
        'purpose_buying',
        'assigned_from',
        'assigned_to',
        'assigned_at',
        'search',
    ];

    /**
     * @param  callable(): array<string, int>  $compute
     * @return array<string, int>
     */
    public static function remember(User $user, Request $request, callable $compute): array
    {
        $ttl = (int) config('lead_scoring.kanban_heat.ttl_seconds', 30);
        if ($ttl < 1) {
            return $compute();
        }

        $key = self::key($user, $request);
        $cached = Cache::get($key);
        if (self::isAnalytics($cached)) {
            return $cached;
        }

        $value = $compute();
        Cache::put($key, $value, now()->addSeconds($ttl));

        return $value;
    }

    public static function bump(): void
    {
        try {
            if (! Cache::has(self::VERSION_KEY)) {
                Cache::forever(self::VERSION_KEY, 0);
            }
            Cache::increment(self::VERSION_KEY);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function key(User $user, Request $request): string
    {
        $roles = $user->roles->pluck('name')->sort()->values()->all();
        $seesBranch = self::seesBranchLeads($user);
        $scope = [
            'user' => (int) $user->id,
            'roles' => $roles,
            'parent_id' => $user->parent_id ? (int) $user->parent_id : null,
            'sees_branch_leads' => $seesBranch,
            'branch_id' => $seesBranch ? $user->getBranchNode()?->id : null,
            'filters' => self::filters($request),
            'version' => (int) Cache::get(self::VERSION_KEY, 0),
        ];

        return 'leads.kanban.heat.'.hash('sha256', json_encode($scope));
    }

    /**
     * Same branch rule as User::seesBranchLeads(), using relations the scope
     * query already loaded. Does not start a new permission lookup.
     */
    private static function seesBranchLeads(User $user): bool
    {
        if ($user->hasRole('branch_admin')) {
            return true;
        }

        if (! $user->relationLoaded('permissions')) {
            return false;
        }

        return $user->permissions->contains(fn ($permission) => $permission->name === 'show-branch-leads');
    }

    /**
     * @return array<string, mixed>
     */
    private static function filters(Request $request): array
    {
        $filters = [];
        foreach (self::FILTER_KEYS as $key) {
            if (! $request->filled($key)) {
                continue;
            }
            $value = $request->input($key);
            if (is_array($value)) {
                $value = array_values($value);
                sort($value);
            }
            $filters[$key] = $value;
        }

        return $filters;
    }

    private static function isAnalytics(mixed $cached): bool
    {
        if (! is_array($cached)) {
            return false;
        }

        foreach (['tempCold', 'tempWarm', 'tempHot', 'callAnswered', 'callNoAnswer'] as $key) {
            if (! array_key_exists($key, $cached)) {
                return false;
            }
        }

        return true;
    }
}
