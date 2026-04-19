<?php

namespace App\Console\Commands;

use App\Services\LeadAssignmentService;
use Illuminate\Console\Command;

class LeadAssignmentRefreshPerformanceCommand extends Command
{
    protected $signature = 'lead-assignment:refresh-performance';

    protected $description = 'Refresh sales_performance snapshots (deals, conversion, response time) for the assignment engine';

    public function handle(LeadAssignmentService $service): int
    {
        $service->refreshSalesPerformanceSnapshot();
        $this->info('Sales performance snapshots updated.');

        return self::SUCCESS;
    }
}
