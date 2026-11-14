<?php

namespace App\Services;

use App\Models\LeadPoolAssignment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LeadPoolAssignmentService
{
    /** Default "Assign To Me" leads per day (users.lead_pool_daily_limit = null). */
    public const DAILY_LIMIT = 40;

    /** Default "Assign To Me" leads per hour round (users.lead_pool_hourly_limit = null). */
    public const BATCH_LIMIT = 10;

    public const COOLDOWN_MINUTES = 60;

    /** Bounds a manager may set on the Lead Pool Settings page. */
    public const MAX_DAILY_LIMIT = 500;

    public const MAX_HOURLY_LIMIT = 100;

    /** Admins (by user id) who may assign Lead Pool leads to any agent, like super_admin. */
    public const ALL_AGENTS_ADMIN_IDS = [30, 33];

    /** Besides super_admin, these users manage every agent's Lead Pool settings. */
    public const ALL_SETTINGS_MANAGER_IDS = [30];

    /** Permission to manage the Lead Pool settings of one's own branch (given per user / role). */
    public const SETTINGS_PERMISSION = 'manage-lead-pool-settings';

    /** Roles that can receive Lead Pool leads from an admin. */
    public const AGENT_ROLES = ['sales', 'team_lead', 'manager'];

    /*
    |--------------------------------------------------------------------------
    | Per-agent settings
    |--------------------------------------------------------------------------
    */

    public function dailyLimitFor(User $user): int
    {
        $limit = (int) ($user->lead_pool_daily_limit ?? 0);

        return $limit > 0 ? $limit : self::DAILY_LIMIT;
    }

    public function hourlyLimitFor(User $user): int
    {
        $limit = (int) ($user->lead_pool_hourly_limit ?? 0);

        return $limit > 0 ? $limit : self::BATCH_LIMIT;
    }

    /** Managers always see the Lead Pool; agents unless a manager turned it off. */
    public function isEnabledFor(User $user): bool
    {
        if ($this->canManageSettings($user)) {
            return true;
        }

        return $user->lead_pool_enabled === null || (bool) $user->lead_pool_enabled;
    }

    public function getTodayCount(int $userId): int
    {
        return LeadPoolAssignment::where('user_id', $userId)
            ->whereBetween('assigned_at', [
                now()->startOfDay(),
                now()->endOfDay(),
            ])
            ->count();
    }

    public function getLastAssignment(int $userId)
    {
        return LeadPoolAssignment::where('user_id', $userId)
            ->latest('assigned_at')
            ->first();
    }

    /** Assign To Me limits only — an admin assigning to someone else has no limits. */
    public function validateBatch(
        int $userId,
        int $count,
        ?string $batchId = null
    ): void {
        $user = User::find($userId);
        if (! $user) {
            throw new \RuntimeException('User not found.');
        }

        if (! $this->isEnabledFor($user)) {
            throw new \RuntimeException('Lead Pool is disabled for your account.');
        }

        $dailyLimit = $this->dailyLimitFor($user);
        $batchLimit = $this->hourlyLimitFor($user);

        if ($count > $batchLimit) {
            throw new \RuntimeException(
                'You can assign a maximum of ' . $batchLimit . ' leads at a time.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Daily limit
        |--------------------------------------------------------------------------
        */

        $remaining = max(0, $dailyLimit - $this->getTodayCount($userId));

        if ($remaining <= 0) {
            throw new \RuntimeException(
                'You have reached your daily limit of ' . $dailyLimit . ' leads. You cannot assign more leads today. Please come back tomorrow.'
            );
        }

        if ($count > $remaining) {
            throw new \RuntimeException(
                "You can assign a maximum of {$remaining} more leads today."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Rounds of the hourly limit — leads can be taken one by one (or together)
        | until the round is full, then the receiver is stopped for COOLDOWN_MINUTES
        | counted from the last lead of the round.
        |--------------------------------------------------------------------------
        */

        $round = $this->roundStateFromTimes(
            $this->todayTimes([$userId])->get($userId, collect()),
            $batchLimit
        );

        if ($round['locked_until']) {
            $nextAvailableAt = $round['locked_until'];
            $minutes = max(
                1,
                (int) ceil(now()->diffInSeconds($nextAvailableAt) / 60)
            );
            $time = $nextAvailableAt->format('g:i A');

            throw new \RuntimeException(
                'You have assigned ' . $batchLimit . " leads. You can assign more leads at {$time} (in {$minutes} minutes)."
            );
        }

        if ($count > $round['remaining']) {
            throw new \RuntimeException(
                "You can assign only {$round['remaining']} more lead(s) before the one-hour break."
            );
        }
    }

    /**
     * Walks today's assignments in order: every hourly-limit-th one starts a COOLDOWN_MINUTES
     * lock; assignments after a lock ends start a new round.
     *
     * @return array{remaining: int, locked_until: ?\Illuminate\Support\Carbon}
     */
    public function getRoundState(int $userId): array
    {
        $user = User::find($userId);
        $batchLimit = $user ? $this->hourlyLimitFor($user) : self::BATCH_LIMIT;

        return $this->roundStateFromTimes($this->todayTimes([$userId])->get($userId, collect()), $batchLimit);
    }

    /** @param Collection<int, \Illuminate\Support\Carbon> $times today's assigned_at, oldest first */
    private function roundStateFromTimes(Collection $times, int $batchLimit): array
    {
        $batchLimit = max(1, $batchLimit);
        $inRound = 0;
        $lockedUntil = null;

        foreach ($times as $at) {
            if ($lockedUntil && $at->gte($lockedUntil)) {
                $lockedUntil = null;
            }
            $inRound++;
            if ($inRound >= $batchLimit) {
                $lockedUntil = $at->copy()->addMinutes(self::COOLDOWN_MINUTES);
                $inRound = 0;
            }
        }

        if ($lockedUntil && now()->gte($lockedUntil)) {
            $lockedUntil = null;
        }

        return [
            'remaining' => $lockedUntil ? 0 : $batchLimit - $inRound,
            'locked_until' => $lockedUntil,
        ];
    }

    /** @return Collection<int, Collection> user id => today's assigned_at times, oldest first */
    private function todayTimes(array $userIds): Collection
    {
        return LeadPoolAssignment::whereIn('user_id', $userIds)
            ->where('assigned_at', '>=', now()->startOfDay())
            ->orderBy('assigned_at')
            ->get(['user_id', 'assigned_at'])
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('assigned_at'));
    }

    public function createAssignment(
        int $userId,
        int $leadId,
        ?string $batchId = null
    ): void {
        LeadPoolAssignment::create([
            'user_id' => $userId,
            'lead_id' => $leadId,
            'batch_id' => $batchId ?: (string) Str::uuid(),
            'assigned_at' => now(),
        ]);
    }

    public function getStatus(int $userId): array
    {
        return $this->getStatuses([$userId])[$userId];
    }

    /**
     * getStatus() for many users with one query per table.
     *
     * @return array<int, array>
     */
    public function getStatuses(array $userIds): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $timesByUser = $this->todayTimes($userIds);
        $users = User::with('roles')->whereIn('id', $userIds)->get()->keyBy('id');
        $statuses = [];

        foreach ($userIds as $userId) {
            $user = $users->get($userId);
            $enabled = $user ? $this->isEnabledFor($user) : false;
            $dailyLimit = $user ? $this->dailyLimitFor($user) : self::DAILY_LIMIT;
            $batchLimit = $user ? $this->hourlyLimitFor($user) : self::BATCH_LIMIT;

            $times = $timesByUser->get($userId, collect());
            $todayCount = $times->count();
            $remaining = max(0, $dailyLimit - $todayCount);

            $round = $this->roundStateFromTimes($times, $batchLimit);
            $cooldownActive = $round['locked_until'] !== null;

            $statuses[$userId] = [
                'enabled' => $enabled,

                // Leads left in the current round (0 while the one-hour break runs).
                'remaining_in_hour' => $round['remaining'],

                'today_count' => $todayCount,
                'daily_limit' => $dailyLimit,

                'remaining_today' => $remaining,

                'batch_limit' => $batchLimit,

                'cooldown_active' => $cooldownActive,

                'next_available_at' => $round['locked_until']?->toISOString(),

                'can_assign' => $enabled && $remaining > 0 && ! $cooldownActive,
            ];
        }

        return $statuses;
    }

    /*
    |--------------------------------------------------------------------------
    | Lead Pool Settings page (who sees the pool, per-day / per-hour limits)
    |--------------------------------------------------------------------------
    | super_admin and ALL_SETTINGS_MANAGER_IDS (user 30) → every agent;
    | anyone with the SETTINGS_PERMISSION permission → agents of their own branch only.
    */

    public function managesAllSettings(User $user): bool
    {
        return $user->hasRole('super_admin')
            || in_array((int) $user->id, self::ALL_SETTINGS_MANAGER_IDS, true);
    }

    public function canManageSettings(User $user): bool
    {
        // checkPermissionTo (not hasPermissionTo): false instead of throwing when the
        // permission row doesn't exist yet.
        return $this->managesAllSettings($user)
            || $user->checkPermissionTo(self::SETTINGS_PERMISSION);
    }

    public function manageableAgentsQuery(User $user)
    {
        $query = User::query()
            ->where('status', 'active')
            ->where('id', '!=', $user->id)
            ->role(self::AGENT_ROLES);

        if (! $this->managesAllSettings($user)) {
            $query->whereIn('id', $user->getBranchUserIds());
        }

        return $query;
    }

    public function canManageAgent(User $user, int $agentId): bool
    {
        return $this->canManageSettings($user)
            && $this->manageableAgentsQuery($user)->whereKey($agentId)->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | Assigning Lead Pool leads to someone else
    |--------------------------------------------------------------------------
    | super_admin and admin only, with no limits. super_admin and ALL_AGENTS_ADMIN_IDS
    | (users 30, 33) pick from every active agent; any other admin only from their own
    | team (getAllSubordinatesIds).
    */

    public function canAssignToOthers(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']);
    }

    public function seesAllAgents(User $user): bool
    {
        return $user->hasRole('super_admin') || in_array((int) $user->id, self::ALL_AGENTS_ADMIN_IDS, true);
    }

    public function agentsQuery(User $user)
    {
        $query = User::query()
            ->where('status', 'active')
            ->where('id', '!=', $user->id)
            ->role(self::AGENT_ROLES);

        if (! $this->seesAllAgents($user)) {
            $query->whereIn('id', $user->getAllSubordinatesIds());
        }

        return $query;
    }

    public function canAssignTo(User $user, int $agentId): bool
    {
        return $this->canAssignToOthers($user)
            && $this->agentsQuery($user)->whereKey($agentId)->exists();
    }

    /** Agents this user may assign Lead Pool leads to. */
    public function assignableAgents(User $user, ?string $search = null): array
    {
        $agents = $this->agentsQuery($user)
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->get(['id', 'name', 'display_name', 'email', 'avatar', 'parent_id']);

        return $agents->map(fn (User $agent) => [
            'id' => $agent->id,
            'name' => User::resolveDisplayName($agent),
            'email' => $agent->email,
            'avatar' => $agent->avatar,
            'branch' => $agent->admin_parent?->name,
        ])->values()->all();
    }
}
