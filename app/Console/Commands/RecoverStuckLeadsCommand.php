<?php

namespace App\Console\Commands;

use App\Jobs\RecoverStuckLeadsJob;
use App\Services\LeadAssignmentService;
use Illuminate\Console\Command;

class RecoverStuckLeadsCommand extends Command
{
    protected $signature = 'leads:recover-stuck {--sync : Run inline instead of queueing}';

    protected $description = 'Reassign leads stuck in the Assigned stage with no updates (auto-recovery)';

    public function handle(LeadAssignmentService $service): int
    {
        if ($this->option('sync')) {
            $n = $service->recoverStuckLeads();
            $this->info('Recovered (reassigned) leads: '.$n);

            return self::SUCCESS;
        }

        RecoverStuckLeadsJob::dispatch();
        $this->info('Recover stuck leads job queued.');

        return self::SUCCESS;
    }
}
