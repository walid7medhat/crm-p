<?php

namespace App\Services;

use App\Models\LeadPoolAssignment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LeadPoolAssignmentService
{
    public const DAILY_LIMIT = 40;

    public const BATCH_LIMIT = 10;

    public const COOLDOWN_MINUTES = 60;

    /** Admins (by user id) who may assign Lead Pool leads to any agent, like super_admin. */
    public const ALL_AGENTS_ADMIN_IDS = [30, 33];

    /** Roles that can receive Lead Pool leads from an admin. */
    public const AGENT_ROLES = ['sales', 'team_lead', 'manager'];

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
        /*
        |--------------------------------------------------------------------------
        | Maximum BATCH_LIMIT per batch
        |--------------------------------------------------------------------------
        */

        if ($count > self::BATCH_LIMIT) {
            throw new \RuntimeException(
                'You can assign a maximum of ' . self::BATCH_LIMIT . ' leads at a time.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Daily limit
        |--------------------------------------------------------------------------
        */

        $todayCount = $this->getTodayCount($userId);

        $remaining = max(
            0,
            self::DAILY_LIMIT - $todayCount
        );

        if ($remaining <= 0) {
            throw new \RuntimeException(
                'You have reached your daily limit of ' . self::DAILY_LIMIT . ' leads. You cannot assign more leads today. Please come back tomorrow.'
            );
        }

        if ($count > $remaining) {
            throw new \RuntimeException(
                "You can assign a maximum of {$remaining} more leads today."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Rounds of BATCH_LIMIT — leads can be taken one by one (or together) until
        | the round is full, then the receiver is stopped for COOLDOWN_MINUTES
        | counted from the last lead of the round.
        |--------------------------------------------------------------------------
        */

        $round = $this->getRoundState($userId);

        if ($round['locked_until']) {
            $nextAvailableAt = $round['locked_until'];
            $minutes = max(
                1,
                (int) ceil(now()->diffInSeconds($nextAvailableAt) / 60)
            );
            $time = $nextAvailableAt->format('g:i A');

            throw new \RuntimeException(
                'You have assigned ' . self::BATCH_LIMIT . " leads. You can assign more leads at {$time} (in {$minutes} minutes)."
            );
        }

        if ($count > $round['remaining']) {
            throw new \RuntimeException(
                "You can assign only {$round['remaining']} more lead(s) before the one-hour break."
            );
        }
    }

    /**
     * Walks today's assignments in order: every BATCH_LIMIT-th one starts a COOLDOWN_MINUTES
     * lock; assignments after a lock ends start a new round.
     *
     * @return array{remaining: int, locked_until: ?\Illuminate\Support\Carbon}
     */
    public function getRoundState(int $userId): array
    {
        return $this->roundStateFromTimes($this->todayTimes([$userId])->get($userId, collect()));
    }

    /** @param Collection<int, \Illuminate\Support\Carbon> $times today's assigned_at, oldest first */
    private function roundStateFromTimes(Collection $times): array
    {
        $inRound = 0;
        $lockedUntil = null;

        foreach ($times as $at) {
            if ($lockedUntil && $at->gte($lockedUntil)) {
                $lockedUntil = null;
            }
            $inRound++;
            if ($inRound >= self::BATCH_LIMIT) {
                $lockedUntil = $at->copy()->addMinutes(self::COOLDOWN_MINUTES);
                $inRound = 0;
            }
        }

        if ($lockedUntil && now()->gte($lockedUntil)) {
            $lockedUntil = null;
        }

        return [
            'remaining' => $lockedUntil ? 0 : self::BATCH_LIMIT - $inRound,
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
     * getStatus() for many users with one query (the admin's agent picker).
     *
     * @return array<int, array>
     */
    public function getStatuses(array $userIds): array
    {
        $timesByUser = $this->todayTimes($userIds);
        $statuses = [];

        foreach ($userIds as $userId) {
            $times = $timesByUser->get($userId, collect());
            $todayCount = $times->count();

            $remaining = max(
                0,
                self::DAILY_LIMIT - $todayCount
            );

            $round = $this->roundStateFromTimes($times);
            $cooldownActive = $round['locked_until'] !== null;
            $nextAvailableAt = $round['locked_until'];

            $statuses[$userId] = [
                // Leads left in the current round (0 while the one-hour break runs).
                'remaining_in_hour' => $round['remaining'],

                'today_count' => $todayCount,
                'daily_limit' => self::DAILY_LIMIT,

                'remaining_today' => $remaining,

                'batch_limit' => self::BATCH_LIMIT,

                'cooldown_active' => $cooldownActive,

                'next_available_at' => $nextAvailableAt?->toISOString(),

                'can_assign' =>
                    $remaining > 0 &&
                    ! $cooldownActive,
            ];
        }

        return $statuses;
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
            'name' => $agent->display_name ?: $agent->name,
            'email' => $agent->email,
            'avatar' => $agent->avatar,
            'branch' => $agent->admin_parent?->name,
        ])->values()->all();
    }
}
