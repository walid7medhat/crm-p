<?php

namespace App\Console\Commands;

use App\Jobs\SyncBitrix24LeadsJob;
use App\Jobs\SyncBitrix24ShardJob;
use App\Models\BitrixSyncShard;
use App\Models\BitrixSyncState;
use App\Services\Bitrix24\Bitrix24SyncOrchestrator;
use App\Services\Bitrix24\Bitrix24SyncProgress;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Bitrix24RetryStuckCommand extends Command
{
    protected $signature = 'bitrix24:retry-stuck {--cancel : Cancel current run and re-queue sequential sync}';

    protected $description = 'Re-dispatch stuck Bitrix24 sync jobs (e.g. worker was on wrong queue)';

    public function handle(): int
    {
        $state = BitrixSyncState::where('key', Bitrix24SyncProgress::SYNC_KEY)->first();

        if (!$state) {
            $this->warn('No sync state found.');
            return self::SUCCESS;
        }

        $bitrixJobs = DB::table('jobs')->where('queue', 'bitrix24')->count();
        $defaultJobs = DB::table('jobs')->where('queue', 'default')->count();

        $this->line("Sync status: {$state->status}, processed: {$state->processed}/{$state->total}");
        $this->line("Pending jobs — bitrix24: {$bitrixJobs}, default: {$defaultJobs}");
        $this->line('Sync queue config: ' . config('bitrix24.queue'));

        if ($this->option('cancel')) {
            $state->forceFill(['status' => 'cancelled', 'finished_at' => now()])->save();
            \App\Services\Bitrix24\Bitrix24SyncOrchestrator::cancelShards();
            $this->info('Cancelled previous sync.');
        }

        if ($state->status !== 'running' && !$this->option('cancel')) {
            $this->warn('Sync is not running. Start from the UI or use start-queue API.');
            return self::SUCCESS;
        }

        $userId = (int) ($state->user_id ?? 1);
        $skip = (bool) $state->skip_existing;

        if ($state->sync_mode === 'parallel' && BitrixSyncShard::incomplete()->exists()) {
            $shards = BitrixSyncShard::incomplete()->get();
            foreach ($shards as $shard) {
                SyncBitrix24ShardJob::dispatch($userId, $skip, $shard->id);
                $this->line("Re-dispatched shard #{$shard->shard_index} (id {$shard->id})");
            }
        } else {
            SyncBitrix24LeadsJob::dispatch($userId, $skip);
            $this->info('Re-dispatched sequential SyncBitrix24LeadsJob.');
        }

        $this->newLine();
        $this->info('Run worker: php artisan queue:work database --queue=default,bitrix24 --timeout=900 --memory=256');

        return self::SUCCESS;
    }
}
