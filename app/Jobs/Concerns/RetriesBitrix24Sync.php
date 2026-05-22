<?php

namespace App\Jobs\Concerns;

use App\Jobs\SyncBitrix24LeadsJob;
use App\Services\Bitrix24\Bitrix24Exception;
use App\Services\Bitrix24\Bitrix24SyncProgress;
use Illuminate\Support\Facades\Log;

trait RetriesBitrix24Sync
{
    protected static function isRecoverableError(\Throwable $e): bool
    {
        if ($e instanceof Bitrix24Exception) {
            return $e->isTransient();
        }

        $msg = strtolower($e->getMessage());

        return str_contains($msg, 'timeout')
            || str_contains($msg, 'connection')
            || str_contains($msg, 'rate limit')
            || str_contains($msg, 'operation_time_limit');
    }

    protected static function retryDelaySeconds(\Throwable $e): int
    {
        if ($e instanceof Bitrix24Exception && $e->isRateLimited()) {
            return max(30, (int) config('bitrix24.rate_limit_retry_seconds', 120));
        }

        return max(5, (int) config('bitrix24.error_retry_seconds', 30));
    }

    protected function scheduleSyncRetry(\Throwable $e, int $userId, bool $skipExisting): void
    {
        $delay = self::retryDelaySeconds($e);

        Bitrix24SyncProgress::noteTransientError(
            $e->getMessage() . " — auto-retry in {$delay}s"
        );

        Log::warning('Bitrix24 sync scheduling auto-retry', [
            'delay_seconds' => $delay,
            'error'         => $e->getMessage(),
        ]);

        SyncBitrix24LeadsJob::dispatch($userId, $skipExisting)
            ->delay(now()->addSeconds($delay));
    }

    protected function chainNextJob(int $userId, bool $skipExisting): void
    {
        $delay = max(0, (int) config('bitrix24.chain_delay_seconds', 1));
        $dispatch = SyncBitrix24LeadsJob::dispatch($userId, $skipExisting);

        if ($delay > 0) {
            $dispatch->delay(now()->addSeconds($delay));
        }
    }

    protected function interPageDelay(): void
    {
        $ms = max(0, (int) config('bitrix24.inter_page_delay_ms', 250));
        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }
}
