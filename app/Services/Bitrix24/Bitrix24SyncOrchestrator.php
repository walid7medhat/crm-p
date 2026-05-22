<?php

namespace App\Services\Bitrix24;

use App\Jobs\SyncBitrix24ShardJob;
use App\Models\BitrixSyncShard;
use App\Models\BitrixSyncState;
use App\Support\Bitrix24Schema;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class Bitrix24SyncOrchestrator
{
    public static function shouldUseParallelShards(): bool
    {
        return Bitrix24Schema::parallelShardsEnabled();
    }

    public static function startFresh(int $userId, bool $skipExisting): void
    {
        $shardCount = (int) config('bitrix24.parallel_shards', 1);

        if ($shardCount <= 1) {
            \App\Jobs\SyncBitrix24LeadsJob::dispatch($userId, $skipExisting);
            return;
        }

        $client = new Bitrix24Client();

        try {
            $bounds = $client->probeLeadIdBounds();
        } catch (\Throwable $e) {
            Log::warning('Bitrix24 shard probe failed, falling back to sequential sync.', [
                'error' => $e->getMessage(),
            ]);
            \App\Jobs\SyncBitrix24LeadsJob::dispatch($userId, $skipExisting);
            return;
        }

        $minId = (int) $bounds['min_id'];
        $maxId = (int) $bounds['max_id'];
        $total = (int) $bounds['total'];

        if ($maxId <= $minId) {
            \App\Jobs\SyncBitrix24LeadsJob::dispatch($userId, $skipExisting);
            return;
        }

        if (!Bitrix24Schema::shardsTableExists()) {
            \App\Jobs\SyncBitrix24LeadsJob::dispatch($userId, $skipExisting);
            return;
        }

        BitrixSyncShard::where('sync_key', Bitrix24SyncProgress::SYNC_KEY)->delete();

        $state = Bitrix24SyncProgress::globalState();
        $state->forceFill([
            'status'           => 'running',
            'sync_mode'        => 'parallel',
            'parallel_shards'  => $shardCount,
            'shards_completed' => 0,
            'total'            => $total,
            'cursor'           => 0,
            'processed'        => 0,
            'new_count'        => 0,
            'existing_count'   => 0,
            'error_count'      => 0,
            'started_at'       => now(),
            'finished_at'      => null,
            'last_error'       => null,
            'user_id'          => $userId,
            'skip_existing'    => $skipExisting,
            'last_progress_at' => now(),
            'last_processed_snapshot' => 0,
            'leads_per_sec'    => 0,
            'eta_seconds'      => null,
        ])->save();

        $range = $maxId - $minId + 1;
        $step = (int) max(1, ceil($range / $shardCount));
        $jobs = [];

        for ($i = 0; $i < $shardCount; $i++) {
            $shardMin = $minId + ($i * $step);
            $shardMax = ($i === $shardCount - 1) ? $maxId : min($maxId, $shardMin + $step - 1);
            if ($shardMin > $maxId) {
                break;
            }

            $shard = BitrixSyncShard::create([
                'sync_key'       => Bitrix24SyncProgress::SYNC_KEY,
                'shard_index'    => $i,
                'min_bitrix_id'  => $shardMin,
                'max_bitrix_id'  => $shardMax,
                'cursor'         => 0,
                'status'         => 'pending',
            ]);

            $jobs[] = new SyncBitrix24ShardJob(
                $userId,
                $skipExisting,
                $shard->id,
            );
        }

        $queue = config('bitrix24.queue', 'default');

        try {
            Bus::batch($jobs)
                ->name('bitrix24-parallel-sync')
                ->onQueue($queue)
                ->allowFailures()
                ->dispatch();
        } catch (\Throwable $e) {
            Log::error('Bitrix24 parallel batch dispatch failed — falling back to sequential', [
                'error' => $e->getMessage(),
            ]);
            \App\Jobs\SyncBitrix24LeadsJob::dispatch($userId, $skipExisting);
        }
    }

    public static function resumeShards(int $userId, bool $skipExisting): void
    {
        if (!Bitrix24Schema::shardsTableExists()) {
            \App\Jobs\SyncBitrix24LeadsJob::dispatch($userId, $skipExisting);
            return;
        }

        $shards = BitrixSyncShard::where('sync_key', Bitrix24SyncProgress::SYNC_KEY)
            ->incomplete()
            ->orderBy('shard_index')
            ->get();

        if ($shards->isEmpty()) {
            \App\Jobs\SyncBitrix24LeadsJob::dispatch($userId, $skipExisting);
            return;
        }

        $state = Bitrix24SyncProgress::globalState();
        $state->forceFill([
            'status'        => 'running',
            'sync_mode'     => 'parallel',
            'skip_existing' => $skipExisting,
            'user_id'       => $userId,
            'finished_at'   => null,
            'last_error'    => null,
        ])->save();

        $queue = config('bitrix24.queue', 'default');
        $jobs = $shards->map(fn (BitrixSyncShard $shard) => new SyncBitrix24ShardJob(
            $userId,
            $skipExisting,
            $shard->id,
        ))->all();

        Bus::batch($jobs)
            ->name('bitrix24-parallel-sync-resume')
            ->onQueue($queue)
            ->allowFailures()
            ->dispatch();
    }

    public static function cancelShards(): void
    {
        if (!Bitrix24Schema::shardsTableExists()) {
            return;
        }

        BitrixSyncShard::where('sync_key', Bitrix24SyncProgress::SYNC_KEY)
            ->whereNotIn('status', ['done', 'cancelled'])
            ->update([
                'status'      => 'cancelled',
                'finished_at' => now(),
            ]);
    }
}
