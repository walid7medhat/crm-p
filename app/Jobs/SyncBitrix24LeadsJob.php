<?php

namespace App\Jobs;

use App\Jobs\Concerns\RetriesBitrix24Sync;
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
 * Multi-page Bitrix24 import per run. Self-chains until all leads are fetched.
 * Never stops on rate limits — schedules delayed auto-retry instead.
 */
class SyncBitrix24LeadsJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, RetriesBitrix24Sync, SerializesModels;

    public int $timeout = 900;

    /** High try count; recoverable errors re-dispatch without throwing. */
    public int $tries = 50;

    public int $maxExceptions = 50;

    public int $backoff = 60;

    public function __construct(
        public int $userId,
        public bool $skipExisting = false,
    ) {
        $this->onQueue(config('bitrix24.queue', 'default'));
    }

    public function handle(): void
    {
        try {
            if ($this->batch()?->cancelled() || Bitrix24SyncProgress::isCancelled()) {
                return;
            }

            $state = Bitrix24SyncProgress::globalState();

            if (!in_array($state->status, ['running', 'paused'], true)) {
                Bitrix24SyncProgress::markRunning($this->userId, $this->skipExisting, 'sequential');
            } elseif ($state->status === 'paused') {
                $state->forceFill(['status' => 'running', 'finished_at' => null])->save();
            }

            $configuredPages = (int) config('bitrix24.pages_per_job', 20);
            if (!$this->skipExisting) {
                $configuredPages = min(
                    $configuredPages,
                    (int) config('bitrix24.pages_per_job_enrich_max', 2)
                );
            }

            $pagesPerJob = Bitrix24SyncThrottler::resolvePagesPerJob($configuredPages);

            $client = new Bitrix24Client();
            $importer = new Bitrix24LeadImporter($client, $this->userId);

            $cursor = (int) ($state->cursor ?? 0);
            $total = (int) ($state->total ?? 0);
            $lastSnap = ['processed' => 0, 'new' => 0, 'existing' => 0, 'errors' => 0];
            $next = $cursor;

            Log::info('Bitrix24 sync chunk started', [
                'cursor'        => $cursor,
                'pages_per_job' => $pagesPerJob,
                'skip_existing' => $this->skipExisting,
            ]);

            for ($pageNum = 0; $pageNum < $pagesPerJob; $pageNum++) {
                if (Bitrix24SyncProgress::isCancelled()) {
                    return;
                }

                if ($pageNum > 0) {
                    $this->interPageDelay();
                }

                $page = $client->listLeads($next);
                $total = (int) ($page['total'] ?? $total);
                $b24Leads = $page['result'] ?? [];
                $pageNext = isset($page['next']) ? (int) $page['next'] : null;
                $pageCount = count($b24Leads);

                $onProgress = function (array $stats) use (&$lastSnap, $total) {
                    Bitrix24SyncProgress::flushImportProgress($stats, $lastSnap, null, $total);
                };

                $pageStats = $importer->importBatch($b24Leads, $this->skipExisting, null, $onProgress);

                Bitrix24SyncProgress::flushImportProgress(
                    $pageStats,
                    $lastSnap,
                    $pageNext ?? $next,
                    $total,
                );

                $processedNow = (int) Bitrix24SyncProgress::globalState()->processed;

                Log::info('Bitrix24 sync page finished', $pageStats + [
                    'page'      => $pageNum + 1,
                    'next'      => $pageNext,
                    'total'     => $total,
                    'processed' => $processedNow,
                ]);

                if (Bitrix24SyncProgress::shouldMarkComplete($total, $processedNow, $pageCount, $pageNext)) {
                    Bitrix24SyncProgress::markDone();
                    Log::info('Bitrix24 sync completed', [
                        'processed' => $processedNow,
                        'total'     => $total,
                    ]);
                    return;
                }

                if ($pageNext === null) {
                    Log::warning('Bitrix24 pagination ended early — scheduling retry', [
                        'processed' => $processedNow,
                        'total'     => $total,
                        'cursor'    => $next,
                    ]);
                    $this->chainNextJob($this->userId, $this->skipExisting);
                    return;
                }

                $next = $pageNext;
            }

            if (Bitrix24SyncProgress::isCancelled()) {
                return;
            }

            $state->forceFill(['last_error' => null])->save();
            $this->chainNextJob($this->userId, $this->skipExisting);
        } catch (\Throwable $e) {
            if (self::isRecoverableError($e)) {
                $this->scheduleSyncRetry($e, $this->userId, $this->skipExisting);
                return;
            }

            Bitrix24SyncProgress::markFailed($e->getMessage());
            Log::error('SyncBitrix24LeadsJob failed (non-recoverable)', ['error' => $e->getMessage()]);
            throw $e;
        } finally {
            Bitrix24SyncThrottler::reset();
        }
    }

    public function failed(\Throwable $exception): void
    {
        if (self::isRecoverableError($exception)) {
            $this->scheduleSyncRetry($exception, $this->userId, $this->skipExisting);
            return;
        }

        Bitrix24SyncProgress::markFailed($exception->getMessage());
    }
}
