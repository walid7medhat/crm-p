<?php

namespace App\Console\Commands;

use App\Services\LeadAssignmentService;
use Illuminate\Console\Command;

class LeadSlaEscalationCommand extends Command
{
    protected $signature = 'leads:sla-escalation';

    protected $description = 'Escalate assigned leads with no first contact past SLA to higher-performing sales';

    public function handle(LeadAssignmentService $service): int
    {
        $n = $service->processSlaEscalations();
        $this->info('SLA escalations processed: '.$n);

        return self::SUCCESS;
    }
}
