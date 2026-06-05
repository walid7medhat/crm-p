<?php

namespace App\Jobs;

use App\Models\BitrixSyncState;
use App\Services\Bitrix24\Bitrix24Client;
use App\Services\Bitrix24\Bitrix24LeadImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessBitrixLeadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 2;

    public function __construct(
        public array $b24,
        public int $userId
    ) {}

    public function handle(): void
    {
        // The worker stays alive across thousands of leads. Without disabling the
        // query log, every statement importOne runs (timeline dedup checks, user
        // lookups, inserts) is retained in memory for the worker's whole lifetime
        // and eventually exhausts PHP's limit. A per-job headroom bump covers the
        // occasional lead with a very large timeline payload.
        @ini_set('memory_limit', '512M');
        DB::connection()->disableQueryLog();

        $client = null;
        $importer = null;

        try {
            $client   = new Bitrix24Client();
            $importer = new Bitrix24LeadImporter($client, $this->userId);

            $result = $importer->importOne($this->b24);

            // Tally the live counters the UI reads. The page-walker only bumps
            // `processed` (leads dispatched); the actual new-vs-existing outcome is
            // only known here, after importOne runs. Atomic SQL increments avoid
            // races between the parallel 'bitrix' queue workers.
            $column = ($result['created'] ?? false) ? 'new_count' : 'existing_count';
            BitrixSyncState::where('key', 'global_sync')->increment($column);

        } catch (\Throwable $e) {
            Log::error('Process lead failed', [
                'lead' => $this->b24['ID'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            // Drop references to the per-lead importer (which holds the timeline
            // payload + per-instance caches) so it's freed before the next job.
            unset($importer, $client);
        }
    }

    /**
     * Runs once after all retries are exhausted — count the lead as a hard error
     * so the UI's "Errors" tile reflects leads that genuinely failed to import
     * (incrementing in handle()'s catch would over-count every retry attempt).
     */
    public function failed(\Throwable $e): void
    {
        BitrixSyncState::where('key', 'global_sync')->increment('error_count');
    }
}