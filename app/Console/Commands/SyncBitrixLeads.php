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
             ini_set('memory_limit', '1024M');  // 1GB for safety
        ini_set('max_execution_time', '3600');  // 1 hour
        ini_set('max_input_time', '3600');
        $userId = 1; 
        $skipExisting = $this->option('skip-existing');

        SyncBitrix24LeadsJob::dispatch($userId, $skipExisting);

        $this->info('✅ Sync started successfully!');
    }
}