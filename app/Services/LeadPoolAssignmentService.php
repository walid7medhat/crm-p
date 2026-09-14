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
        | If this lead belongs to an already started batch,
        | don't check cooldown again.
        |--------------------------------------------------------------------------
        */

        if ($batchId) {
            $existingBatch = LeadPoolAssignment::where('user_id', $userId)
                ->where('batch_id', $batchId)
                ->exists();

            if ($existingBatch) {
                return;
            }
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
        | One hour cooldown
        |--------------------------------------------------------------------------
        */

        $lastAssignment = $this->getLastAssignment($userId);

        if ($lastAssignment) {
            $nextAvailableAt = $lastAssignment->assigned_at
                ->copy()
                ->addMinutes(self::COOLDOWN_MINUTES);

            if (now()->lt($nextAvailableAt)) {
                $minutes =max(
                        1,
                        (int) ceil(now()->diffInSeconds($nextAvailableAt) / 60)
                    );

                $time = $nextAvailableAt->format('g:i A');

                throw new \RuntimeException(
                    "You can assign more leads at {$time} (in {$minutes} minutes)."
                );
            }
        }
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

        $lastAssignment = $this->getLastAssignment($userId);

        $nextAvailableAt = null;
        $cooldownActive = false;

        if ($lastAssignment) {
            $nextAvailableAt = $lastAssignment->assigned_at
                ->copy()
                ->addMinutes(self::COOLDOWN_MINUTES);

            $cooldownActive = now()->lt($nextAvailableAt);
        }

        return [
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