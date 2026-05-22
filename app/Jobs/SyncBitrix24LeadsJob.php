<?php

namespace App\Jobs;

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
 * Fetches multiple Bitrix24 pages per run (configurable, default ~20 × 50 leads),
 * saves progress after each page so the Vue monitor updates live.
 */
class SyncBitrix24LeadsJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 2;

    public int $backoff = 30;

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

            if ($state->status !== 'running') {
                Bitrix24SyncProgress::markRunning($this->userId, $this->skipExisting, 'sequential');
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
                'cursor'          => $cursor,
                'pages_per_job'   => $pagesPerJob,
                'skip_existing'   => $this->skipExisting,
            ]);

            for ($pageNum = 0; $pageNum < $pagesPerJob; $pageNum++) {
                if (Bitrix24SyncProgress::isCancelled()) {
                    return;
                }

                $page = $client->listLeads($next);
                $total = (int) ($page['total'] ?? $total);
                $b24Leads = $page['result'] ?? [];
                $pageNext = isset($page['next']) ? (int) $page['next'] : null;

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

                Log::info('Bitrix24 sync page finished', $pageStats + [
                    'page' => $pageNum + 1,
                    'next' => $pageNext,
                ]);

                if ($pageNext === null) {
                    Bitrix24SyncProgress::markDone();
                    return;
                }

                $next = $pageNext;
            }

            if (Bitrix24SyncProgress::isCancelled()) {
                return;
            }

            self::dispatch($this->userId, $this->skipExisting);
        } catch (\Throwable $e) {
            Bitrix24SyncProgress::markFailed($e->getMessage());
            Log::error('SyncBitrix24LeadsJob failed', ['error' => $e->getMessage()]);
            throw $e;
        } finally {
            Bitrix24SyncThrottler::reset();
        }
    }

    public function failed(\Throwable $exception): void
    {
        Bitrix24SyncProgress::markFailed($exception->getMessage());
    }
}
