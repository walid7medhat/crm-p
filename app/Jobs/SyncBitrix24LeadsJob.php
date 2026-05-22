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
 * One Bitrix24 page (50 leads) per job, with live DB progress during import.
 * Self-chains so the monitor UI updates every ~5–30 seconds.
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

            $client = new Bitrix24Client();
            $importer = new Bitrix24LeadImporter($client, $this->userId);

            $cursor = (int) ($state->cursor ?? 0);
            $total = (int) ($state->total ?? 0);
            $lastSnap = ['processed' => 0, 'new' => 0, 'existing' => 0, 'errors' => 0];

            Log::info('Bitrix24 sync page started', [
                'cursor'        => $cursor,
                'skip_existing' => $this->skipExisting,
            ]);

            $page = $client->listLeads($cursor);
            $total = (int) ($page['total'] ?? $total);
            $b24Leads = $page['result'] ?? [];
            $next = isset($page['next']) ? (int) $page['next'] : null;

            $onProgress = function (array $stats) use (&$lastSnap, $total) {
                Bitrix24SyncProgress::flushImportProgress($stats, $lastSnap, null, $total);
            };

            $pageStats = $importer->importBatch($b24Leads, $this->skipExisting, null, $onProgress);

            Bitrix24SyncProgress::flushImportProgress($pageStats, $lastSnap, $next ?? $cursor, $total);

            Log::info('Bitrix24 sync page finished', $pageStats + ['next' => $next]);

            if ($next === null) {
                Bitrix24SyncProgress::markDone();
                return;
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
