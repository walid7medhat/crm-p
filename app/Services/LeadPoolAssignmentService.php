<?php

namespace App\Services;

use App\Models\LeadPoolAssignment;
use Illuminate\Support\Str;

class LeadPoolAssignmentService
{
    public const DAILY_LIMIT = 20;

    public const BATCH_LIMIT = 5;

    public const COOLDOWN_MINUTES = 60;

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

    public function validateBatch(
        int $userId,
        int $count,
        ?string $batchId = null
    ): void {
        /*
        |--------------------------------------------------------------------------
        | Maximum 5 per batch
        |--------------------------------------------------------------------------
        */

        if ($count > self::BATCH_LIMIT) {
            throw new \RuntimeException(
                'You can assign a maximum of 5 leads at a time.'
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
                'You have reached your daily limit of 20 leads. You cannot assign more leads today. Please come back tomorrow.'
            );
        }

        if ($count > $remaining) {
            throw new \RuntimeException(
                "You can assign a maximum of {$remaining} more leads today."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Rounds of 5 — the user can take leads one by one (or together) until
        | 5, then is stopped for a full hour counted from the 5th lead.
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
                "You have assigned 5 leads. You can assign more leads at {$time} (in {$minutes} minutes)."
            );
        }

        if ($count > $round['remaining']) {
            throw new \RuntimeException(
                "You can assign only {$round['remaining']} more lead(s) before the one-hour break."
            );
        }
    }

    /**
     * Walks today's assignments in order: every 5th one starts a COOLDOWN_MINUTES lock;
     * assignments after a lock ends start a new round.
     *
     * @return array{remaining: int, locked_until: ?\Illuminate\Support\Carbon}
     */
    public function getRoundState(int $userId): array
    {
        $times = LeadPoolAssignment::where('user_id', $userId)
            ->where('assigned_at', '>=', now()->startOfDay())
            ->orderBy('assigned_at')
            ->pluck('assigned_at');

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
        $todayCount = $this->getTodayCount($userId);

        $remaining = max(
            0,
            self::DAILY_LIMIT - $todayCount
        );

        $round = $this->getRoundState($userId);
        $cooldownActive = $round['locked_until'] !== null;
        $nextAvailableAt = $round['locked_until'];

        return [
            // Leads left in the current round of 5 (0 while the one-hour break runs).
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
}