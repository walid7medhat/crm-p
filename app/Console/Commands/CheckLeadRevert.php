<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Lead;
use Illuminate\Support\Facades\Log;

class CheckLeadRevert extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leads:check-revert';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Check and revert leads that stayed in stage 2 for more than 1 hour';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $leadsToRevert = Lead::needsRevert()->get();
        
        $revertedCount = 0;
        
        foreach ($leadsToRevert as $lead) {
            $lead->revertToStageOne();
            $revertedCount++;
            
            $this->info("Lead ID {$lead->id} reverted to stage 1");
            Log::info("Lead ID {$lead->id} reverted to stage 1 due to inactivity");
        }

        $this->info("Successfully reverted {$revertedCount} leads to stage 1");
        
        if ($revertedCount > 0) {
            Log::info("Lead revert job executed. Reverted {$revertedCount} leads to stage 1");
        }
    }
}