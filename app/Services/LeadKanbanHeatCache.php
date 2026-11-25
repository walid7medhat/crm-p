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
        'modified_by',
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
        'integration_id',
        'country_code',
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
        'modified_from',
        'modified_to',
        'modified_at',
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
        self::store($user, $request, $value);

        return $value;
    }

    /** Last-known totals survive version bumps this long (first-paint fallback only). */
    private const STALE_TTL_SECONDS = 900;

    /**
     * Save freshly computed totals: versioned copy (exact) + unversioned last-known copy
     * that getOnly() falls back to after a lead change bumps the version.
     *
     * @param  array<string, int>  $value
     */
    public static function store(User $user, Request $request, array $value): void
    {
        $ttl = (int) config('lead_scoring.kanban_heat.ttl_seconds', 30);
        if ($ttl < 1) {
            return;
        }

        Cache::put(self::key($user, $request), $value, now()->addSeconds($ttl));
        Cache::put(self::key($user, $request, false), $value, now()->addSeconds(self::STALE_TTL_SECONDS));
    }

    /**
     * Return a warm cache hit only — never run the expensive SUM scan.
     * Used by the fast board first-paint so chips can fill in the background.
     *
     * @return array<string, int>|null
     */
    public static function getOnly(User $user, Request $request): ?array
    {
        $ttl = (int) config('lead_scoring.kanban_heat.ttl_seconds', 30);
        if ($ttl < 1) {
            return null;
        }

        $cached = Cache::get(self::key($user, $request));
        if (self::isAnalytics($cached)) {
            return $cached;
        }

        // Any lead save bumps the version, so the exact copy is usually gone. For the
        // first paint a last-known total (≤15 min old) beats empty chips; the background
        // stage-counts call replaces it with exact numbers a moment later.
        $stale = Cache::get(self::key($user, $request, false));

        return self::isAnalytics($stale) ? $stale : null;
    }

    /**
     * Exact (versioned) copy only — never the last-known fallback.
     *
     * @return array<string, int>|null
     */
    public static function getExact(User $user, Request $request): ?array
    {
        if ((int) config('lead_scoring.kanban_heat.ttl_seconds', 30) < 1) {
            return null;
        }
        $cached = Cache::get(self::key($user, $request));

        return self::isAnalytics($cached) ? $cached : null;
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

    public static function key(User $user, Request $request, bool $versioned = true): string
    {
        $roles = $user->roles->pluck('name')->sort()->values()->all();
        $seesBranch = self::seesBranchLeads($user);
        $scope = [
            'brand' => \App\Support\Brand\BrandIsolation::cacheSegment(),
            'user' => (int) $user->id,
            'roles' => $roles,
            'parent_id' => $user->parent_id ? (int) $user->parent_id : null,
            'sees_branch_leads' => $seesBranch,
            'branch_id' => $seesBranch ? $user->getBranchNode()?->id : null,
            'filters' => self::filters($request),
            'version' => $versioned ? (int) Cache::get(self::VERSION_KEY, 0) : 'last-known',
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
