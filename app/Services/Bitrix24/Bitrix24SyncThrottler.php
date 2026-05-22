<?php

namespace App\Services\Bitrix24;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Adaptive batch sizing based on memory and DB latency.
 */
class Bitrix24SyncThrottler
{
    private static ?float $lastDbMs = null;

    public static function resolvePagesPerJob(int $configured): int
    {
        $pages = max(1, min(50, $configured));
        $memoryMb = memory_get_usage(true) / 1048576;

        if ($memoryMb > 450) {
            $pages = max(3, (int) floor($pages / 3));
            Log::debug('Bitrix24 sync: reduced pages_per_job due to memory', [
                'memory_mb' => round($memoryMb, 1),
                'pages'     => $pages,
            ]);
        } elseif ($memoryMb > 320) {
            $pages = max(5, (int) floor($pages / 2));
        }

        $dbMs = self::probeDbLatencyMs();
        if ($dbMs !== null && $dbMs > 800) {
            $pages = max(3, (int) floor($pages / 2));
            Log::debug('Bitrix24 sync: reduced pages_per_job due to slow DB', [
                'db_ms' => round($dbMs, 1),
                'pages' => $pages,
            ]);
        }

        return $pages;
    }

    public static function resolveDbInsertChunk(int $configured): int
    {
        $chunk = max(50, min(2000, $configured));
        $dbMs = self::probeDbLatencyMs();

        if ($dbMs !== null && $dbMs > 1200) {
            return max(50, (int) floor($chunk / 2));
        }

        return $chunk;
    }

    private static function probeDbLatencyMs(): ?float
    {
        if (self::$lastDbMs !== null) {
            return self::$lastDbMs;
        }

        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            self::$lastDbMs = (microtime(true) - $start) * 1000;

            return self::$lastDbMs;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function reset(): void
    {
        self::$lastDbMs = null;
    }
}
