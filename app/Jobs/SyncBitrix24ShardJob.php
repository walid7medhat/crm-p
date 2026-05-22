<?php

namespace App\Jobs;

use App\Jobs\Concerns\RetriesBitrix24Sync;
use App\Models\BitrixSyncShard;
use App\Services\Bitrix24\Bitrix24Client;
use App\Services\Bitrix24\Bitrix24LeadImporter;
use App\Services\Bitrix24\Bitrix24SyncProgress;
use App\Services\Bitrix24\Bitrix24SyncThrottler;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Parallel shard worker — processes ID-filtered Bitrix24 pages within
 * [min_bitrix_id, max_bitrix_id], self-chains until the shard is complete.
 */
class SyncBitrix24ShardJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, RetriesBitrix24Sync, SerializesModels;

    public int $timeout = 900;

    public int $tries = 50;

    public int $maxExceptions = 50;

    public int $backoff = 60;

    public function __construct(
        public int $userId,
        public bool $skipExisting,
        public int $shardId,
    ) {
        $this->onQueue(config('bitrix24.queue', 'default'));
    }

    public function handle(): void
    {
        try {
            if ($this->batch()?->cancelled() || Bitrix24SyncProgress::isCancelled()) {
                return;
            }

            $shard = BitrixSyncShard::find($this->shardId);
            if (!$shard || $shard->status === 'done' || $shard->status === 'cancelled') {
                return;
            }

            $state = Bitrix24SyncProgress::globalState();
            if ($state->status !== 'running') {
                Bitrix24SyncProgress::markRunning($this->userId, $this->skipExisting, 'parallel');
            }

            $client = new Bitrix24Client();
            $importer = new Bitrix24LeadImporter($client, $this->userId);
            $pagesPerJob = Bitrix24SyncThrottler::resolvePagesPerJob(
                (int) config('bitrix24.pages_per_job', 20)
            );

            Log::info('Bitrix24 shard chunk started', [
                'shard_id'      => $this->shardId,
                'min_id'        => $shard->min_bitrix_id,
                'max_id'        => $shard->max_bitrix_id,
                'cursor'        => $shard->cursor,
                'pages_per_job' => $pagesPerJob,
            ]);

            $bundle = $client->listLeadPagesForShard(
                (int) $shard->min_bitrix_id,
                (int) $shard->max_bitrix_id,
                (int) $shard->cursor,
                $pagesPerJob,
            );

            $totalHint = (int) ($bundle['total'] ?? 0);
            $lastSnap = ['processed' => 0, 'new' => 0, 'existing' => 0, 'errors' => 0];
            $onProgress = function (array $stats) use (&$lastSnap, $totalHint) {
                Bitrix24SyncProgress::flushImportProgress(
                    $stats,
                    $lastSnap,
                    null,
                    $totalHint > 0 ? $totalHint : null,
                );
            };

            $stats = $importer->importBatch(
                $bundle['result'] ?? [],
                $this->skipExisting,
                null,
                $onProgress,
            );
            $next = $bundle['next'] ?? null;

            Bitrix24SyncProgress::flushImportProgress(
                $stats,
                $lastSnap,
                $next ?? (int) $shard->cursor,
                $totalHint > 0 ? $totalHint : null,
            );

            $shard->forceFill([
                'processed'      => (int) $shard->processed + $stats['processed'],
                'new_count'      => (int) $shard->new_count + $stats['new'],
                'existing_count' => (int) $shard->existing_count + $stats['existing'],
                'error_count'    => (int) $shard->error_count + $stats['errors'],
                'cursor'         => $next ?? $shard->cursor,
                'status'         => 'running',
                'started_at'     => $shard->started_at ?? now(),
            ])->save();

            if ($state->total <= 0 && ($bundle['total'] ?? 0) > 0) {
                Bitrix24SyncProgress::recordChunk(0, 0, 0, 0, null, (int) $bundle['total']);
            }

            if ($next === null) {
                Bitrix24SyncProgress::markShardDone($shard);
                Bitrix24SyncProgress::aggregateShardTotals();
                return;
            }

            if (Bitrix24SyncProgress::isCancelled()) {
                return;
            }

            $delay = max(0, (int) config('bitrix24.chain_delay_seconds', 1));
            $dispatch = self::dispatch($this->userId, $this->skipExisting, $this->shardId);
            if ($delay > 0) {
                $dispatch->delay(now()->addSeconds($delay));
            }
        } catch (\Throwable $e) {
            if (self::isRecoverableError($e)) {
                Bitrix24SyncProgress::noteTransientError($e->getMessage());
                $delay = self::retryDelaySeconds($e);
                self::dispatch($this->userId, $this->skipExisting, $this->shardId)
                    ->delay(now()->addSeconds($delay));
                return;
            }

            $shard = BitrixSyncShard::find($this->shardId);
            if ($shard) {
                $shard->forceFill([
                    'status'      => 'failed',
                    'last_error'  => $e->getMessage(),
                    'finished_at' => now(),
                ])->save();
            }
            Bitrix24SyncProgress::markFailed($e->getMessage());
            Log::error('SyncBitrix24ShardJob chunk failed', [
                'shard_id' => $this->shardId,
                'error'    => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            Bitrix24SyncThrottler::reset();
        }
    }

    public function failed(\Throwable $exception): void
    {
        if (self::isRecoverableError($exception)) {
            Bitrix24SyncProgress::noteTransientError($exception->getMessage());
            $delay = self::retryDelaySeconds($exception);
            self::dispatch($this->userId, $this->skipExisting, $this->shardId)
                ->delay(now()->addSeconds($delay));
            return;
        }

        $shard = BitrixSyncShard::find($this->shardId);
        if ($shard) {
            $shard->forceFill([
                'status'      => 'failed',
                'last_error'  => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();
        }
        Bitrix24SyncProgress::markFailed($exception->getMessage());
        Log::error('SyncBitrix24ShardJob failed', [
            'shard_id' => $this->shardId,
            'error'    => $exception->getMessage(),
        ]);
    }
}
