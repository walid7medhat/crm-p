<?php

namespace App\Console\Commands;

use App\Helpers\LeadHistoryHelper;
use App\Models\Lead;
use App\Models\Stage;
use Illuminate\Console\Command;

/**
 * Apply fixed Bitrix24-status → local-stage corrections in one pass.
 * Matches local leads by their stored status_lead (LIKE, case-insensitive)
 * and moves them to the target stage id. Idempotent.
 *
 *   php artisan bitrix24:remap-stages --dry-run
 *   php artisan bitrix24:remap-stages
 *
 * Add a new rule by appending to MAP below: 'status pattern' => target stage id.
 */
class RemapBitrixStages extends Command
{
    protected $signature = 'bitrix24:remap-stages {--dry-run : Show what would change without writing}';

    protected $description = 'Move local leads to the correct stage based on their Bitrix status (Future Prospect→6, Job Seeker→11, ...)';

    /**
     * status_lead pattern (lowercase, matched with LIKE %pattern%) => target stage id.
     * "seeker" covers both "Job Seeker" and the misspelled "Jop Seeker".
     *
     * @var array<string, int>
     */
    private const MAP = [
        'future prospect' => 6,   // Qualified
        'seeker'          => 11,  // Unqualified  (Job Seeker / Jop Seeker)
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $grandTotal = 0;

        foreach (self::MAP as $pattern => $stageId) {
            $stage = Stage::find($stageId);
            if (! $stage) {
                $this->warn("Stage #{$stageId} not found — skipping pattern \"{$pattern}\".");
                continue;
            }

            $base = Lead::query()
            ->where('stage_id',3)
                ->whereRaw('LOWER(TRIM(status_lead)) LIKE ?', ['%'.mb_strtolower($pattern).'%'])
                ->where('stage_id', '!=', $stageId);

            $count = (clone $base)->count();
            $this->info("\"{$pattern}\" → \"{$stage->name}\" (#{$stageId}): {$count} lead(s)".($dryRun ? ' [dry-run]' : ''));

            if ($count === 0) {
                continue;
            }

            $base->select('id', 'stage_id', 'lead_name')->chunkById(200, function ($leads) use ($stageId, $stage, $dryRun, &$grandTotal) {
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
                            'source'       => 'remap-stages',
                        ]);
                    }

                    $grandTotal++;
                }
            });
        }

        $this->newLine();
        $this->info(($dryRun ? 'Would move' : 'Moved')." {$grandTotal} lead(s) total.");

        return self::SUCCESS;
    }
}
