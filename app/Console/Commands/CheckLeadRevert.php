<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Lead;
use Illuminate\Support\Facades\Log;
use App\Events\LeadUpdated;
use App\Notifications\LeadRevertWarningNotification;
use Illuminate\Support\Facades\Notification;
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
        // 🔄 Revert
        Lead::with('stage')
            ->whereHas('stage', function ($q) {
                $q->where('auto_revert', true);
            })
            ->whereNotNull('last_stage_change_at')
            ->chunkById(100, function ($leads) {

                foreach ($leads as $lead) {
                    if ($lead->shouldAutoRevert()) {

                        $lead->revertToPreviousStage();

                        $this->info("Lead {$lead->id} reverted");
                    }
                }
            });

        // 🔔 Notification
        Lead::with('stage')
            ->whereHas('stage', function ($q) {
                $q->where('auto_revert', true);
            })
            ->where('notified_revert', false)
            ->chunkById(100, function ($leads) {

                foreach ($leads as $lead) {
                 if ($lead->shouldSendRevertNotification()) {

                    $previousStage = $lead->getPreviousStage();

                    $users = collect([$lead->responsiblePerson])
                        ->merge($lead->observingUsers)
                        ->filter();

                    Notification::send(
                        $users,
                        new LeadRevertWarningNotification(
                            $lead,
                            $previousStage?->name ?? 'previous stage',
                            $lead->stage->notify_before_minutes
                        )
                    );

                    $lead->updateQuietly([
                        'notified_revert' => true
                    ]);

                    $this->info("Lead {$lead->id} notified before revert");
                }
                }
            });
    }
}