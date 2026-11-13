<?php

namespace App\Support\Maintenance;

use App\Events\MaintenanceModeUpdated;
use App\Models\PlatformMaintenanceSetting;
use App\Models\User;
use App\Services\AccountAccessMailer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Platform maintenance — shared DB row + cache.
 * Scope "all" locks everyone (except Super Admin).
 * Scope "branches" locks only users under selected CRM branch/office roots.
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
     *   title?: ?string,
     *   message?: ?string,
     *   estimated_return?: ?string
     * }  $input
     * @return array{
     *   enabled: bool,
     *   scope: string,
     *   branch_user_ids: list<int>,
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

        $scope = array_key_exists('scope', $input)
            ? (string) $input['scope']
            : (string) ($row->scope ?: PlatformMaintenanceSetting::SCOPE_ALL);

        if (! in_array($scope, [PlatformMaintenanceSetting::SCOPE_ALL, PlatformMaintenanceSetting::SCOPE_BRANCHES], true)) {
            $scope = PlatformMaintenanceSetting::SCOPE_ALL;
        }

        $branchIds = array_key_exists('branch_user_ids', $input)
            ? $this->normalizeBranchIds($input['branch_user_ids'] ?? [])
            : array_values(array_map('intval', $row->branch_user_ids ?? []));

        if ($scope === PlatformMaintenanceSetting::SCOPE_ALL) {
            $branchIds = [];
        }

        if ($willEnable && $scope === PlatformMaintenanceSetting::SCOPE_BRANCHES && $branchIds === []) {
            throw new \InvalidArgumentException('Select at least one branch when maintenance scope is branches.');
        }

        DB::transaction(function () use ($row, $input, $actor, $willEnable, $scope, $branchIds) {
            if (array_key_exists('is_enabled', $input)) {
                $row->is_enabled = $willEnable;
            }
            $row->scope = $scope;
            $row->branch_user_ids = $branchIds;
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
     * @return array{
     *   enabled: bool,
     *   scope: string,
     *   branch_user_ids: list<int>,
     *   title: ?string,
     *   message: ?string,
     *   estimated_return: ?string,
     *   updated_at: ?string
     * }
     */
    public function toPublicArray(PlatformMaintenanceSetting $row): array
    {
        return [
            'enabled' => (bool) $row->is_enabled,
            'scope' => $row->scope ?: PlatformMaintenanceSetting::SCOPE_ALL,
            'branch_user_ids' => array_values(array_map('intval', $row->branch_user_ids ?? [])),
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

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
