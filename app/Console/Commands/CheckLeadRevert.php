<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Lead;
use Illuminate\Support\Facades\Log;
use App\Events\LeadUpdated;

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
            $oldStage=$lead->stage;
            $lead->revertToStageOne();
            $revertedCount++;
            $newStage=$lead->stage;
            $this->info("Lead ID {$lead->id} reverted to stage 1");
               $changes = [
                'old_stage' => $oldStage->name,
                'new_stage' => $newStage->name
            ];
            broadcast(new LeadUpdated($lead, 'stage_changed', null, $changes));
            Log::info("Lead ID {$lead->id} reverted to stage 1 due to inactivity");
        }

        $this->info("Successfully reverted {$revertedCount} leads to stage 1");
        
        if ($revertedCount > 0) {
            Log::info("Lead revert job executed. Reverted {$revertedCount} leads to stage 1");
        }
    }
}