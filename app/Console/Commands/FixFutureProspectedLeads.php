<?php

namespace App\Console\Commands;

use App\Helpers\LeadHistoryHelper;
use App\Models\Lead;
use App\Models\Stage;
use Illuminate\Console\Command;

/**
 * Move local leads whose Bitrix24 status is "Future Prospected" into the local
 * Qualified stage (id 6 by default). The leads were imported with
 * status_lead = the Bitrix status name, so we match on that.
 *
 *   php artisan bitrix24:fix-future-prospected
 *   php artisan bitrix24:fix-future-prospected --dry-run
 *   php artisan bitrix24:fix-future-prospected --stage=6 --status="Future Prospected"
 */
class FixFutureProspectedLeads extends Command
{
    protected $signature = 'bitrix24:fix-future-prospected
        {--stage=6 : Target local stage id (Qualified)}
        {--status=Future Prospect : Match leads whose status_lead equals this}
        {--dry-run : Show what would change without writing}';

    protected $description = 'Move local leads with Bitrix status "Future Prospected" into the Qualified stage (id 6)';

    public function handle(): int
    {
        $stageId = (int) $this->option('stage') ?: 6;
        $matchStatus = (string) $this->option('status');
        $dryRun = (bool) $this->option('dry-run');

        $stage = Stage::find($stageId);
        if (! $stage) {
            $this->error("Stage #{$stageId} not found.");
            return self::FAILURE;
        }

        $base = Lead::query()
            ->whereRaw('LOWER(TRIM(status_lead)) = ?', [mb_strtolower(trim($matchStatus))])
            ->where('stage_id', '!=', $stageId);

        $total = (clone $base)->count();
        if ($total === 0) {
            $this->info("No leads with status \"{$matchStatus}\" need moving to \"{$stage->name}\" (#{$stageId}).");
            return self::SUCCESS;
        }

        $this->info("Found {$total} lead(s) with status \"{$matchStatus}\" → moving to \"{$stage->name}\" (#{$stageId}).".($dryRun ? ' [dry-run]' : ''));

        $moved = 0;
        $base->select('id', 'stage_id', 'status_lead', 'lead_name')
            ->chunkById(200, function ($leads) use ($stageId, $stage, $dryRun, &$moved) {
                foreach ($leads as $lead) {
                    $oldStageId = (int) $lead->stage_id;
                    $this->line("  #{$lead->id} \"{$lead->lead_name}\"  stage {$oldStageId} → {$stageId}");

                    if (! $dryRun) {
                        Lead::withoutEvents(fn () => $lead->update([
                            'stage_id'             => $stageId,
                            'last_stage_change_at' => now(),
                        ]));

                        LeadHistoryHelper::log($lead->id, [
                            'action'       => 'stage_changed',
                            'old_stage_id' => $oldStageId,
                            'new_stage_id' => $stageId,
                            'new_stage'    => $stage->name,
                            'source'       => 'fix-future-prospected',
                            'note'         => "Future Prospected → {$stage->name}",
                        ]);
                    }

                    $moved++;
                }
            });

        $this->newLine();
        $this->info(($dryRun ? 'Would move' : 'Moved')." {$moved} lead(s) to \"{$stage->name}\" (#{$stageId}).");

        return self::SUCCESS;
    }
}
