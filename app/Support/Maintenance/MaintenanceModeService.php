<?php

namespace App\Support\Maintenance;

use App\Events\MaintenanceModeUpdated;
use App\Models\PlatformMaintenanceSetting;
use App\Models\User;
use App\Services\AccountAccessMailer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Platform maintenance — shared DB row + cache.
 * Scope "all" locks everyone except Super Admin, and any extra exemptions.
 * Scope "branches" locks only users under selected CRM branch/office roots.
 * Scope "users" locks only the people a super admin picked.
 * Super Admin always bypasses in middleware and cannot be selected.
 */
class MaintenanceModeService
{
    public const CACHE_KEY = 'platform:maintenance:v1';

    public const AFFECTED_USERS_CACHE_KEY = 'platform:maintenance:affected_users:v1';

    public const CACHE_TTL_SECONDS = 30;

    public function __construct(private AccountAccessMailer $mailer)
    {
    }

    public function current(): PlatformMaintenanceSetting
    {
        return PlatformMaintenanceSetting::query()->orderBy('id')->firstOrCreate(
            [],
            [
                'is_enabled' => false,
                'scope' => PlatformMaintenanceSetting::SCOPE_ALL,
                'branch_user_ids' => [],
                'title' => "We'll Be Back Soon",
                'message' => 'The CRM is temporarily unavailable while we perform scheduled maintenance.',
                'estimated_return' => null,
            ]
        );
    }

    /**
     * @return array{
     *   enabled: bool,
     *   scope: string,
     *   branch_user_ids: list<int>,
     *   except_admin_role: bool,
     *   except_user_ids: list<int>,
     *   target_user_ids: list<int>,
     *   title: ?string,
     *   message: ?string,
     *   estimated_return: ?string,
     *   updated_at: ?string
     * }
     */
    public function statusPayload(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            return $this->toPublicArray($this->current());
        });
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->statusPayload()['enabled'] ?? false);
    }

    /**
     * Whether this request subject is locked by the current maintenance settings.
     * Unauthenticated callers are only locked when scope is "all".
     */
    public function subjectIsAffected(?User $user): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $payload = $this->statusPayload();
        $scope = $payload['scope'] ?? PlatformMaintenanceSetting::SCOPE_ALL;

        if ($scope === PlatformMaintenanceSetting::SCOPE_USERS) {
            if (! $user) {
                return false;
            }

            $targets = array_values(array_map('intval', $payload['target_user_ids'] ?? []));

            return in_array((int) $user->id, $targets, true);
        }

        if ($this->userIsExempt($user, $payload)) {
            return false;
        }

        if ($scope !== PlatformMaintenanceSetting::SCOPE_BRANCHES) {
            return true;
        }

        // Partial branch lock: leave public/unauthenticated traffic open.
        if (! $user) {
            return false;
        }

        $selected = array_values(array_map('intval', $payload['branch_user_ids'] ?? []));
        if ($selected === []) {
            return false;
        }

        return in_array((int) $user->id, $this->affectedUserIds(), true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function userIsExempt(?User $user, array $payload): bool
    {
        if (! $user) {
            return false;
        }

        $exceptIds = array_values(array_map('intval', $payload['except_user_ids'] ?? []));
        if (in_array((int) $user->id, $exceptIds, true)) {
            return true;
        }

        return ! empty($payload['except_admin_role']) && $user->hasRole('admin');
    }

    /**
     * @return list<int>
     */
    public function affectedUserIds(): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        $payload = $this->statusPayload();
        if (($payload['scope'] ?? '') !== PlatformMaintenanceSetting::SCOPE_BRANCHES) {
            return [];
        }

        $selected = array_values(array_map('intval', $payload['branch_user_ids'] ?? []));
        if ($selected === []) {
            return [];
        }

        sort($selected);
        $cacheKey = self::AFFECTED_USERS_CACHE_KEY.':'.md5(implode(',', $selected));

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($selected) {
            $ids = [];
            foreach ($selected as $branchId) {
                $branch = User::query()->find($branchId);
                if (! $branch) {
                    continue;
                }
                foreach ($branch->getAllSubordinatesIds() as $id) {
                    $ids[(int) $id] = true;
                }
            }

            return array_map('intval', array_keys($ids));
        });
    }

    /**
     * @param  array{
     *   is_enabled?: bool,
     *   scope?: string,
     *   branch_user_ids?: list<int>|null,
     *   except_admin_role?: bool,
     *   except_user_ids?: list<int>|null,
     *   target_user_ids?: list<int>|null,
     *   title?: ?string,
     *   message?: ?string,
     *   estimated_return?: ?string
     * }  $input
     * @return array{
     *   enabled: bool,
     *   scope: string,
     *   branch_user_ids: list<int>,
     *   except_admin_role: bool,
     *   except_user_ids: list<int>,
     *   target_user_ids: list<int>,
     *   title: ?string,
     *   message: ?string,
     *   estimated_return: ?string,
     *   updated_at: ?string
     * }
     */
    public function update(array $input, User $actor): array
    {
        $row = $this->current();
        $previous = $this->toPublicArray($row);

        $wasEnabled = (bool) $row->is_enabled;
        $willEnable = array_key_exists('is_enabled', $input)
            ? (bool) $input['is_enabled']
            : $wasEnabled;

        $allowedScopes = [
            PlatformMaintenanceSetting::SCOPE_ALL,
            PlatformMaintenanceSetting::SCOPE_BRANCHES,
            PlatformMaintenanceSetting::SCOPE_USERS,
        ];

        $scope = array_key_exists('scope', $input)
            ? (string) $input['scope']
            : (string) ($row->scope ?: PlatformMaintenanceSetting::SCOPE_ALL);

        if (! in_array($scope, $allowedScopes, true)) {
            $scope = PlatformMaintenanceSetting::SCOPE_ALL;
        }

        if ($scope === PlatformMaintenanceSetting::SCOPE_USERS && ! $this->supportsPeople()) {
            throw new \InvalidArgumentException('Selected people is not available until the database migration runs.');
        }

        $branchIds = array_key_exists('branch_user_ids', $input)
            ? $this->normalizeBranchIds($input['branch_user_ids'] ?? [])
            : array_values(array_map('intval', $row->branch_user_ids ?? []));

        if ($scope !== PlatformMaintenanceSetting::SCOPE_BRANCHES) {
            $branchIds = [];
        }

        $exceptAdminRole = array_key_exists('except_admin_role', $input)
            ? (bool) $input['except_admin_role']
            : (bool) ($this->supportsPeople() ? $row->except_admin_role : false);

        $exceptUserIds = array_key_exists('except_user_ids', $input)
            ? $this->normalizeUserIds($input['except_user_ids'] ?? [])
            : array_values(array_map('intval', $this->supportsPeople() ? ($row->except_user_ids ?? []) : []));

        $targetUserIds = array_key_exists('target_user_ids', $input)
            ? $this->normalizeUserIds($input['target_user_ids'] ?? [])
            : array_values(array_map('intval', $this->supportsPeople() ? ($row->target_user_ids ?? []) : []));

        if ($scope === PlatformMaintenanceSetting::SCOPE_USERS) {
            $exceptAdminRole = false;
            $exceptUserIds = [];
        } else {
            $targetUserIds = [];
        }

        if ($willEnable && $scope === PlatformMaintenanceSetting::SCOPE_BRANCHES && $branchIds === []) {
            throw new \InvalidArgumentException('Select at least one branch when maintenance scope is branches.');
        }

        if ($willEnable && $scope === PlatformMaintenanceSetting::SCOPE_USERS && $targetUserIds === []) {
            throw new \InvalidArgumentException('Select at least one person when maintenance applies only to selected people.');
        }

        DB::transaction(function () use ($row, $input, $actor, $willEnable, $scope, $branchIds, $exceptAdminRole, $exceptUserIds, $targetUserIds) {
            if (array_key_exists('is_enabled', $input)) {
                $row->is_enabled = $willEnable;
            }
            $row->scope = $scope;
            $row->branch_user_ids = $branchIds;
            if ($this->supportsPeople()) {
                $row->except_admin_role = $exceptAdminRole;
                $row->except_user_ids = $exceptUserIds;
                $row->target_user_ids = $targetUserIds;
            }
            if (array_key_exists('title', $input)) {
                $row->title = $this->nullableString($input['title']);
            }
            if (array_key_exists('message', $input)) {
                $row->message = $this->nullableString($input['message']);
            }
            if (array_key_exists('estimated_return', $input)) {
                $row->estimated_return = $this->nullableString($input['estimated_return']);
            }
            $row->updated_by = $actor->id;
            $row->save();
        });

        $row->refresh();
        $next = $this->toPublicArray($row);

        $this->forgetCache();

        $description = match (true) {
            ! $wasEnabled && $willEnable => 'maintenance.enabled',
            $wasEnabled && ! $willEnable => 'maintenance.disabled',
            default => 'maintenance.updated',
        };

        activity('maintenance')
            ->causedBy($actor)
            ->performedOn($row)
            ->withProperties([
                'previous' => $previous,
                'new' => $next,
            ])
            ->log($description);

        event(new MaintenanceModeUpdated($next));

        return $next;
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        // Affected-user keys are TTL-bound; also bust common prefix via full status refresh.
        // Individual md5 keys expire within CACHE_TTL_SECONDS.
    }

    /**
     * Settings for the super-admin screen, including the people who were picked.
     *
     * @return array<string, mixed>
     */
    public function toAdminArray(PlatformMaintenanceSetting $row): array
    {
        $payload = $this->toPublicArray($row);
        $payload['updated_by'] = $row->updated_by;
        $payload['available_branches'] = $this->availableBranches();
        $payload['except_users'] = $this->userSummaries($payload['except_user_ids']);
        $payload['target_users'] = $this->userSummaries($payload['target_user_ids']);

        return $payload;
    }

    /**
     * Active users a super admin can exempt or target. Super Admin is omitted.
     *
     * @return list<array{id:int, name:string, email:?string, role:string}>
     */
    public function searchUsers(string $term): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        if (mb_strlen($term) > 80) {
            $term = mb_substr($term, 0, 80);
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $query = User::query()
            ->where('status', 'active')
            ->whereDoesntHave('roles', function ($roles) {
                $roles->where('name', 'super_admin');
            })
            ->where(function ($inner) use ($like, $term) {
                $inner->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
                if (Schema::hasColumn('users', 'display_name')) {
                    $inner->orWhere('display_name', 'like', $like);
                }
                if (ctype_digit($term)) {
                    $inner->orWhere('id', (int) $term);
                }
            })
            ->with('roles')
            ->orderBy('name')
            ->limit(15);

        return $query->get()->map(fn (User $user) => $this->summarizeUser($user))->values()->all();
    }

    /**
     * @return array{
     *   enabled: bool,
     *   scope: string,
     *   branch_user_ids: list<int>,
     *   except_admin_role: bool,
     *   except_user_ids: list<int>,
     *   target_user_ids: list<int>,
     *   title: ?string,
     *   message: ?string,
     *   estimated_return: ?string,
     *   updated_at: ?string
     * }
     */
    public function toPublicArray(PlatformMaintenanceSetting $row): array
    {
        $people = $this->supportsPeople();

        return [
            'enabled' => (bool) $row->is_enabled,
            'scope' => $row->scope ?: PlatformMaintenanceSetting::SCOPE_ALL,
            'branch_user_ids' => array_values(array_map('intval', $row->branch_user_ids ?? [])),
            'except_admin_role' => $people && (bool) $row->except_admin_role,
            'except_user_ids' => $people ? array_values(array_map('intval', $row->except_user_ids ?? [])) : [],
            'target_user_ids' => $people ? array_values(array_map('intval', $row->target_user_ids ?? [])) : [],
            'title' => $row->title,
            'message' => $row->message,
            'estimated_return' => $row->estimated_return,
            'updated_at' => optional($row->updated_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array{id:int, name:string, parent_id:?int, level:string}>
     */
    public function availableBranches(): array
    {
        return $this->mailer->branches();
    }

    /**
     * @param  mixed  $ids
     * @return list<int>
     */
    private function normalizeBranchIds(mixed $ids): array
    {
        if (! is_array($ids)) {
            return [];
        }

        $out = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0 || ! $this->mailer->isBranch($id)) {
                continue;
            }
            $out[$id] = true;
        }

        return array_map('intval', array_keys($out));
    }

    private ?bool $peopleColumns = null;

    private function supportsPeople(): bool
    {
        return $this->peopleColumns ??= (
            Schema::hasColumn('platform_maintenance_settings', 'except_admin_role')
            && Schema::hasColumn('platform_maintenance_settings', 'except_user_ids')
            && Schema::hasColumn('platform_maintenance_settings', 'target_user_ids')
        );
    }

    /**
     * @param  mixed  $ids
     * @return list<int>
     */
    private function normalizeUserIds(mixed $ids): array
    {
        if (! is_array($ids)) {
            return [];
        }

        $clean = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $clean[$id] = true;
            }
        }

        $clean = array_map('intval', array_keys($clean));
        if ($clean === []) {
            return [];
        }

        if (count($clean) > 50) {
            $clean = array_slice($clean, 0, 50);
        }

        $allowed = User::query()
            ->whereIn('id', $clean)
            ->whereDoesntHave('roles', function ($roles) {
                $roles->where('name', 'super_admin');
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_filter(
            $clean,
            fn (int $id) => in_array($id, $allowed, true)
        ));
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{id:int, name:string, email:?string, role:string}>
     */
    private function userSummaries(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }

        $users = User::query()->whereIn('id', $ids)->with('roles')->get()->keyBy('id');
        $out = [];
        foreach ($ids as $id) {
            $user = $users->get($id);
            if ($user instanceof User) {
                $out[] = $this->summarizeUser($user);
            }
        }

        return $out;
    }

    /**
     * @return array{id:int, name:string, email:?string, role:string}
     */
    private function summarizeUser(User $user): array
    {
        $roles = $user->roles
            ->pluck('name')
            ->reject(fn ($name) => $name === 'super_admin')
            ->values()
            ->all();

        return [
            'id' => (int) $user->id,
            'name' => User::resolveDisplayName($user) ?: (string) $user->name,
            'email' => $user->email,
            'role' => $roles === [] ? '' : implode(', ', $roles),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
