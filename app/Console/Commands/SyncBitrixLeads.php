<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\SyncBitrix24LeadsJob;

class SyncBitrixLeads extends Command
{
    protected $signature = 'bitrix:sync-leads {--skip-existing}';
    protected $description = 'Sync Bitrix24 leads using queue job';

    public function handle()
    {
        $userId = 1; 
        $skipExisting = $this->option('skip-existing');

        SyncBitrix24LeadsJob::dispatch($userId, $skipExisting);

        $this->info('✅ Sync started successfully!');
    }
}