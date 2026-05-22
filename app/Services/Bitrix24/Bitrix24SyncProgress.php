<?php

namespace App\Services\Bitrix24;

use App\Models\BitrixSyncShard;
use App\Models\BitrixSyncState;
use App\Support\Bitrix24Schema;
use Illuminate\Support\Facades\DB;

class Bitrix24SyncProgress
{
    public const SYNC_KEY = 'global_sync';

    public static function globalState(): BitrixSyncState
    {
        return BitrixSyncState::firstOrCreate(
            ['key' => self::SYNC_KEY],
            ['status' => 'idle', 'cursor' => 0],
        );
    }

    public static function isCancelled(): bool
    {
        $state = static::globalState();
        return in_array($state->status, ['cancelled', 'paused'], true);
    }

    public static function markRunning(int $userId, bool $skipExisting, string $syncMode = 'sequential'): void
    {
        $state = static::globalState();
        if ($state->status !== 'running') {
            $state->forceFill([
                'status'                  => 'running',
                'started_at'              => now(),
                'finished_at'             => null,
                'last_error'              => null,
                'user_id'                 => $userId,
                'skip_existing'           => $skipExisting,
                'sync_mode'               => $syncMode,
                'last_progress_at'        => now(),
                'last_processed_snapshot' => (int) $state->processed,
                'leads_per_sec'           => 0,
                'eta_seconds'             => null,
            ])->save();
        }
    }

    /**
     * Push incremental progress during a long importBatch (delta since last flush).
     *
     * @param  array{processed: int, new: int, existing: int, errors: int}  $stats
     * @param  array{processed: int, new: int, existing: int, errors: int}  $lastSnap
     */
    public static function flushImportProgress(
        array $stats,
        array &$lastSnap,
        ?int $cursor = null,
        ?int $total = null,
    ): void {
        $dp = (int) $stats['processed'] - (int) ($lastSnap['processed'] ?? 0);
        $dn = (int) $stats['new'] - (int) ($lastSnap['new'] ?? 0);
        $de = (int) $stats['existing'] - (int) ($lastSnap['existing'] ?? 0);
        $derr = (int) $stats['errors'] - (int) ($lastSnap['errors'] ?? 0);

        if ($dp === 0 && $dn === 0 && $de === 0 && $derr === 0 && $cursor === null && $total === null) {
            return;
        }

        self::recordChunk($dp, $dn, $de, $derr, $cursor, $total);

        $lastSnap = [
            'processed' => (int) $stats['processed'],
            'new'       => (int) $stats['new'],
            'existing'  => (int) $stats['existing'],
            'errors'    => (int) $stats['errors'],
        ];
    }

    public static function recordChunk(
        int $processed,
        int $newCount,
        int $existingCount,
        int $errorCount,
        ?int $cursor = null,
        ?int $total = null,
    ): void {
        if ($processed === 0 && $newCount === 0 && $existingCount === 0 && $errorCount === 0 && $cursor === null && $total === null) {
            return;
        }

        $state = static::globalState();
        $now = now();

        DB::transaction(function () use ($state, $processed, $newCount, $existingCount, $errorCount, $cursor, $total, $now) {
            $locked = BitrixSyncState::where('key', self::SYNC_KEY)->lockForUpdate()->first();
            if (!$locked) {
                return;
            }

            $prevProcessed = (int) $locked->processed;
            $newProcessed = $prevProcessed + $processed;

            $updates = [
                'processed'      => $newProcessed,
                'new_count'      => (int) $locked->new_count + $newCount,
                'existing_count' => (int) $locked->existing_count + $existingCount,
                'error_count'    => (int) $locked->error_count + $errorCount,
            ];

            if ($cursor !== null) {
                $updates['cursor'] = $cursor;
            }
            if ($total !== null && $total > 0) {
                $updates['total'] = $total;
            }

            $lastAt = $locked->last_progress_at;
            $lastSnap = (int) $locked->last_processed_snapshot;
            if ($lastAt && $processed > 0) {
                // Minimum 1s window avoids absurd rates (e.g. 50k/sec) when flushes are sub-second.
                $elapsed = max(1.0, $now->diffInMilliseconds($lastAt) / 1000);
                $delta = max(0, $newProcessed - $lastSnap);
                $instantRate = min(500.0, $delta / $elapsed);
                $prevRate = (float) ($locked->leads_per_sec ?? 0);
                $smoothed = $prevRate > 0
                    ? ($prevRate * 0.7) + ($instantRate * 0.3)
                    : $instantRate;
                $updates['leads_per_sec'] = round(min(500.0, $smoothed), 2);
                $updates['last_progress_at'] = $now;
                $updates['last_processed_snapshot'] = $newProcessed;

                $totalLeads = (int) ($updates['total'] ?? $locked->total);
                if ($totalLeads > 0 && $smoothed > 0) {
                    $remaining = max(0, $totalLeads - $newProcessed);
                    $updates['eta_seconds'] = (int) ceil($remaining / $smoothed);
                }
            }

            $locked->forceFill($updates)->save();
        });
    }

    public static function markFailed(string $message): void
    {
        static::globalState()->forceFill([
            'status'      => 'failed',
            'last_error'  => $message,
            'finished_at' => now(),
        ])->save();
    }

    /** Keep sync running; UI shows warning while worker auto-retries. */
    public static function noteTransientError(string $message): void
    {
        $state = static::globalState();
        $state->forceFill([
            'status'      => 'running',
            'last_error'  => $message,
            'finished_at' => null,
        ])->save();
    }

    /**
     * Only mark complete when Bitrix24 pagination truly ended.
     */
    public static function shouldMarkComplete(int $total, int $processed, int $pageLeadCount, ?int $pageNext): bool
    {
        if ($pageNext !== null) {
            return false;
        }

        if ($pageLeadCount > 0 && $total > 0 && $processed < max(100, (int) ($total * 0.9))) {
            return false;
        }

        return true;
    }

    public static function markDone(): void
    {
        static::globalState()->forceFill([
            'status'      => 'done',
            'finished_at' => now(),
            'eta_seconds' => 0,
        ])->save();
    }

    public static function recordShardChunk(
        BitrixSyncShard $shard,
        int $processed,
        int $newCount,
        int $existingCount,
        int $errorCount,
        ?int $cursor = null,
    ): void {
        $shard->forceFill([
            'processed'      => (int) $shard->processed + $processed,
            'new_count'      => (int) $shard->new_count + $newCount,
            'existing_count' => (int) $shard->existing_count + $existingCount,
            'error_count'    => (int) $shard->error_count + $errorCount,
            'cursor'         => $cursor ?? $shard->cursor,
            'status'         => 'running',
            'started_at'     => $shard->started_at ?? now(),
        ])->save();

        static::recordChunk($processed, $newCount, $existingCount, $errorCount, null, null);
    }

    public static function markShardDone(BitrixSyncShard $shard): void
    {
        if (!Bitrix24Schema::shardsTableExists()) {
            static::markDone();
            return;
        }

        $shard->forceFill([
            'status'      => 'done',
            'finished_at' => now(),
        ])->save();

        $completed = BitrixSyncShard::where('sync_key', self::SYNC_KEY)
            ->where('status', 'done')
            ->count();

        $state = static::globalState();
        $state->forceFill(['shards_completed' => $completed])->save();

        $pending = BitrixSyncShard::where('sync_key', self::SYNC_KEY)
            ->whereNotIn('status', ['done', 'cancelled'])
            ->count();

        if ($pending === 0 && $state->status === 'running') {
            static::markDone();
        }
    }

    public static function aggregateShardTotals(): void
    {
        if (!Bitrix24Schema::shardsTableExists()) {
            return;
        }

        $totals = BitrixSyncShard::where('sync_key', self::SYNC_KEY)
            ->selectRaw('SUM(processed) as processed, SUM(new_count) as new_count, SUM(existing_count) as existing_count, SUM(error_count) as error_count')
            ->first();

        if (!$totals) {
            return;
        }

        static::globalState()->forceFill([
            'processed'      => (int) $totals->processed,
            'new_count'      => (int) $totals->new_count,
            'existing_count' => (int) $totals->existing_count,
            'error_count'    => (int) $totals->error_count,
        ])->save();
    }
}
