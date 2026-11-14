<?php

namespace App\Console\Commands;

use App\Support\Brand\BrandCrmScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time fix: Luxvant leads that were auto-reverted too early.
 *
 * Before Luxvant had its own kanban_settings row, its revert timer fell back to
 * 1 hour, so Assigned leads were reverted to New Lead with `revert` set — and
 * hidden from the sales agent's board. This puts them back:
 *   - clears `revert`
 *   - moves the lead back to the stage it had before the revert (from lead history)
 *   - restarts last_stage_change_at so it does not revert again immediately
 *
 * Only Luxvant leads (brand_key = luxvant or responsible person in the Admin
 * Luxvant tree) reverted on/after --since. OIA leads are never touched.
 *
 *   php artisan brand:restore-luxvant-reverted --since=2026-11-13 --dry-run
 */
class BrandRestoreLuxvantRevertedCommand extends Command
{
    protected $signature = 'brand:restore-luxvant-reverted
                            {--since= : Only leads reverted on/after this date (YYYY-MM-DD), required}
                            {--dry-run : Show what would change without writing}';

    protected $description = 'Give Luxvant agents back leads that were auto-reverted by the 1-hour fallback timer';

    public function handle(): int
    {
        $since = (string) $this->option('since');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $since)) {
            $this->error('Pass --since=YYYY-MM-DD (the day the Luxvant brand changes went live).');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        BrandCrmScope::reset();
        $tree = BrandCrmScope::resolveConfiguredBranchUserIds();

        $leads = DB::table('leads')
            ->whereNull('deleted_at')
            ->whereNotNull('revert')
            ->where('revert', '>=', $since.' 00:00:00')
            ->where(function ($q) use ($tree) {
                $q->where('brand_key', 'luxvant');
                if ($tree !== []) {
                    $q->orWhereIn('responsible_person_id', $tree);
                }
            })
            ->get(['id', 'lead_name', 'responsible_person_id', 'stage_id', 'revert']);

        if ($leads->isEmpty()) {
            $this->info('No Luxvant leads were reverted since '.$since.'. Nothing to do.');

            return self::SUCCESS;
        }

        $stageIdByName = DB::table('stages')->where('stage_type', 'lead')->pluck('id', 'name');
        $stageNameById = DB::table('stages')->pluck('name', 'id');
        $agentNames = DB::table('users')->whereIn('id', $leads->pluck('responsible_person_id')->filter()->unique())->pluck('name', 'id');

        $rows = [];
        $restored = 0;

        foreach ($leads as $lead) {
            $history = DB::table('lead_histories')
                ->where('lead_id', $lead->id)
                ->where('changes', 'like', '%"action":"revert"%')
                ->orderByDesc('id')
                ->value('changes');
            $oldStageName = $history ? (json_decode($history, true)['old_stage'] ?? null) : null;
            $restoreStageId = $oldStageName ? ($stageIdByName[$oldStageName] ?? null) : null;

            $rows[] = [
                $lead->id,
                mb_strimwidth((string) $lead->lead_name, 0, 30, '…'),
                $agentNames[$lead->responsible_person_id] ?? '#'.$lead->responsible_person_id,
                $lead->revert,
                $stageNameById[$lead->stage_id] ?? '-',
                $restoreStageId ? $oldStageName : '(stays) '.($stageNameById[$lead->stage_id] ?? '-'),
            ];

            if ($dry) {
                continue;
            }

            DB::transaction(function () use ($lead, $restoreStageId, $oldStageName, $stageNameById) {
                $update = [
                    'revert' => null,
                    'last_stage_change_at' => now(),
                    'updated_at' => now(),
                ];
                if ($restoreStageId) {
                    $update['stage_id'] = $restoreStageId;
                }
                DB::table('leads')->where('id', $lead->id)->update($update);

                DB::table('lead_histories')->insert([
                    'lead_id' => $lead->id,
                    'user_id' => null,
                    'changes' => json_encode([
                        'action' => 'revert_undone',
                        'old_stage' => $stageNameById[$lead->stage_id] ?? null,
                        'new_stage' => $restoreStageId ? $oldStageName : ($stageNameById[$lead->stage_id] ?? null),
                        'note' => 'Restored: Luxvant lead was auto-reverted by the 1-hour fallback timer',
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
            $restored++;
        }

        $this->table(['Lead', 'Name', 'Agent', 'Reverted at', 'Now in', 'Goes back to'], $rows);

        if ($dry) {
            $this->warn(count($rows).' lead(s) would be restored. Dry run — no changes written.');
        } else {
            $this->info("Restored {$restored} lead(s). Their agents can see them again.");
        }

        return self::SUCCESS;
    }
}
