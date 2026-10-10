<?php

namespace App\Services\DailyMotivation;

use Carbon\Carbon;

/**
 * Pure rotation math. Message numbers do not depend on who logged in first.
 *
 * Each person keeps a stable slot from 0 to 119. On Dubai day index D, after an
 * optional explicit reset, their message is:
 *
 *   ((slot + personalIndex) % 120) + 1
 *
 * personalIndex follows the calendar, so a missed day does not rewind the cycle
 * and does not depend on request timing. People with distinct slots who have
 * not been individually reset therefore see different messages on the same day.
 */
class MotivationRotation
{
    public const CYCLE = 120;

    public static function dayIndex(Carbon $anchor, Carbon $today): int
    {
        $anchor = $anchor->copy()->startOfDay();
        $today = $today->copy()->startOfDay();

        if ($today->lt($anchor)) {
            return 0;
        }

        return (int) $anchor->diffInDays($today);
    }

    public static function personalIndex(int $dayIndex, ?int $cycleResetDayIndex): int
    {
        if ($cycleResetDayIndex === null) {
            return $dayIndex;
        }

        return max(0, $dayIndex - $cycleResetDayIndex);
    }

    public static function messageNumber(int $slot, int $personalIndex, int $cycle = self::CYCLE): int
    {
        $cycle = max(1, $cycle);
        $slot = $slot % $cycle;
        if ($slot < 0) {
            $slot += $cycle;
        }

        return (($slot + $personalIndex) % $cycle) + 1;
    }

    /**
     * @param  array<int, int>  $userIdsInIdOrder
     * @param  array<int, int>  $takenSlots
     * @return array<int, array{slot: int, shared: bool}>
     */
    public static function assignSlots(array $userIdsInIdOrder, array $takenSlots, int $cycle = self::CYCLE): array
    {
        $taken = [];
        foreach ($takenSlots as $slot) {
            $taken[(int) $slot] = true;
        }

        $plan = [];
        foreach ($userIdsInIdOrder as $userId) {
            $userId = (int) $userId;
            $preferred = ($userId - 1) % $cycle;
            if ($preferred < 0) {
                $preferred += $cycle;
            }

            if (! isset($taken[$preferred])) {
                $plan[$userId] = ['slot' => $preferred, 'shared' => false];
                $taken[$preferred] = true;
                continue;
            }

            $found = null;
            for ($step = 1; $step < $cycle; $step++) {
                $candidate = ($preferred + $step) % $cycle;
                if (! isset($taken[$candidate])) {
                    $found = $candidate;
                    break;
                }
            }

            if ($found === null) {
                $plan[$userId] = ['slot' => $preferred, 'shared' => true];
                continue;
            }

            $plan[$userId] = ['slot' => $found, 'shared' => false];
            $taken[$found] = true;
        }

        return $plan;
    }

    /**
     * Next enabled message at or after the canonical number. The canonical
     * number itself is never rewritten, so disabling a row cannot shift the cycle.
     *
     * @param  array<int, int>  $enabledNumbers
     */
    public static function resolveDisplay(int $canonical, array $enabledNumbers, int $cycle = self::CYCLE): ?int
    {
        if ($enabledNumbers === []) {
            return null;
        }

        $enabled = [];
        foreach ($enabledNumbers as $number) {
            $enabled[(int) $number] = true;
        }

        $canonical = (($canonical - 1) % $cycle) + 1;
        if (isset($enabled[$canonical])) {
            return $canonical;
        }

        for ($step = 1; $step < $cycle; $step++) {
            $candidate = (($canonical - 1 + $step) % $cycle) + 1;
            if (isset($enabled[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }
}
