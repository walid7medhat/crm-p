<?php

namespace App\Console\Commands;

use App\Services\LeadKanbanHeatCache;
use App\Support\Brand\BrandCrmScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Undo SLA escalation: put every escalated lead back exactly as it was before.
 *
 *   php artisan leads:undo-sla-escalation --since=2026-11-10
 *
 * 1. Dry run: shows what will change per lead and writes a CSV report of every change
 *    made after the escalation (storage/app/sla-undo-report-*.csv).
 * 2. Asks for confirmation. Only on "yes" it applies, per lead:
 *    - owner back to the rep before the (first) escalation
 *    - stage back to the stage the lead had before it entered the escalation stage
 *      (e.g. New Lead → Assigned gives New Lead); revert flags / Lead Pool count undone
 *    - lead fields edited after the escalation back to their old value
 *    - comments (with replies/attachments/mentions) and activities added after the
 *      escalation permanently deleted; first_contacted_at reset
 *    - one `sla_escalation_undone` history entry holding a full copy of the deleted rows
 *
 * Skipped (listed in the report): deleted/merged leads, blocked/missing old owner, lead
 * already with the old owner, already undone, or reassigned by hand after the escalation.
 */
class LeadUndoSlaEscalationCommand extends Command
{
    protected $signature = 'leads:undo-sla-escalation
                            {--since= : Escalations on/after this date (YYYY-MM-DD)}';

    protected $description = 'Put leads moved by SLA escalation back to their state before it (dry run + confirm)';

    /** Columns never written by the field restore (owner/stage/timers are handled separately). */
    protected const FIELD_RESTORE_SKIP = [
        'id', 'stage_id', 'responsible_person_id', 'last_stage_change_at', 'updated_at', 'created_at', 'deleted_at',
        'brand_key', 'first_contacted_at', 'last_sla_escalation_at', 'revert', 'notified_revert',
        'notification_times_sent', 'lead_pool_visits', 'merged_into_lead_id', 'lead_number',
    ];

    /** @var array<int, array{name: string, status: ?string}> */
    protected array $users = [];

    /** @var array<string, int> lowercase lead stage name => id */
    protected array $stageIdByName = [];

    /** @var array<int, string> */
    protected array $stageNameById = [];

    protected ?string $poolStageName = null;

    /** @var array<string, int> */
    protected array $leadColumns = [];

    public function handle(): int
    {
        $since = (string) $this->option('since');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $since)) {
            $this->error('Pass --since=YYYY-MM-DD');

            return self::FAILURE;
        }

        $this->warnIfSlaStillOn();
        $this->loadStages();
        $this->leadColumns = array_flip(Schema::getColumnListing('leads'));

        $escalations = DB::table('lead_histories')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(changes, '$.action')) = 'sla_escalation'")
            ->where('created_at', '>=', $since.' 00:00:00')
            ->orderBy('created_at')->orderBy('id')
            ->get(['id', 'lead_id', 'changes', 'created_at'])
            ->groupBy('lead_id');

        if ($escalations->isEmpty()) {
            $this->info("No SLA escalations since {$since}.");

            return self::SUCCESS;
        }

        // ---------- 1. dry run: plan + report ----------
        $reportPath = storage_path('app/sla-undo-report-'.now()->format('Ymd-His').'.csv');
        $csv = fopen($reportPath, 'w');
        fwrite($csv, "\xEF\xBB\xBF");
        fputcsv($csv, [
            'lead_id', 'lead_name', 'escalated_at', 'old_owner', 'moved_to', 'owner_now',
            'stage_now', 'stage_back_to', 'plan', 'change_at', 'changed_by', 'type', 'action', 'details',
        ]);

        $plans = [];
        $skipped = [];
        $rows = [];

        foreach ($escalations->keys()->chunk(500) as $ids) {
            $leads = DB::table('leads')->whereIn('id', $ids->all())
                ->get(['id', 'lead_name', 'responsible_person_id', 'stage_id', 'deleted_at', 'merged_into_lead_id'])
                ->keyBy('id');
            $need = $leads->pluck('responsible_person_id')->all();
            foreach ($ids as $id) {
                foreach ($escalations[$id] as $e) {
                    $need[] = (int) ((json_decode($e->changes, true) ?: [])['old_person_id'] ?? 0);
                }
            }
            $this->loadUsers($need);

            foreach ($ids as $leadId) {
                $lead = $leads[$leadId] ?? null;
                $first = $escalations[$leadId]->first();
                $last = $escalations[$leadId]->last();
                $firstC = json_decode($first->changes, true) ?: [];
                $lastC = json_decode($last->changes, true) ?: [];
                $oldOwnerId = (int) ($firstC['old_person_id'] ?? 0);

                $after = $lead ? $this->changesAfter($leadId, $first) : [];
                $targetStageId = null;
                if ($lead) {
                    $atEscalation = $this->stageAtEscalation($after, (int) $lead->stage_id);
                    $targetStageId = $atEscalation ? ($this->stageBefore($leadId, (int) $first->id, $atEscalation) ?? $atEscalation) : null;
                }
                $fieldRestore = $this->fieldsBeforeEscalation($after);
                $notes = count(array_filter($after, fn ($e) => $e['type'] !== 'history'));

                $reason = $this->skipReason($lead, $oldOwnerId, $lastC, $last);
                $ownerNow = $lead ? $this->userName((int) $lead->responsible_person_id) : '-';
                $stageNow = $lead ? ($this->stageNameById[(int) $lead->stage_id] ?? '-') : '-';
                $stageBack = $targetStageId ? ($this->stageNameById[$targetStageId] ?? '-') : '-';

                $plan = $reason ? 'SKIP: '.$reason : 'RESTORE → '.$this->userName($oldOwnerId).' / '.$stageBack
                    .($fieldRestore ? ' / fields: '.implode(', ', array_keys($fieldRestore)) : '')
                    .($notes ? " / delete {$notes} comment(s)+activity(ies)" : '');

                $head = [
                    $leadId, $lead->lead_name ?? '(deleted)', (string) $first->created_at,
                    $firstC['old_person'] ?? $this->userName($oldOwnerId), $lastC['new_person'] ?? '-',
                    $ownerNow, $stageNow, $stageBack, $plan,
                ];
                if ($after === []) {
                    fputcsv($csv, array_merge($head, ['', '', '', 'no changes after escalation', '']));
                }
                foreach ($after as $e) {
                    fputcsv($csv, array_merge($head, [$e['at'], $e['by'], $e['type'], $e['action'], $e['details']]));
                }

                if ($reason) {
                    $skipped[$reason][] = $leadId;

                    continue;
                }

                $rows[] = [
                    $leadId, mb_strimwidth((string) $lead->lead_name, 0, 26, '…'), $ownerNow, $this->userName($oldOwnerId),
                    $stageNow, $stageBack, $fieldRestore ? count($fieldRestore) : '-', $notes ?: '-',
                ];
                $plans[] = [
                    'lead_id' => $leadId,
                    'old_owner' => $oldOwnerId,
                    'current_owner' => (int) $lead->responsible_person_id,
                    'stage' => $targetStageId,
                    'pool_reverts' => $this->countPoolReverts($after),
                    'escalation_id' => (int) $first->id,
                    'since' => (string) $first->created_at,
                    'fields' => $fieldRestore,
                ];
            }
        }
        fclose($csv);

        if ($rows !== []) {
            $this->table(['Lead', 'Name', 'Owner now', 'Back to', 'Stage now', 'Stage back to', 'Fields', 'Notes to delete'], $rows);
        }
        foreach ($skipped as $why => $ids) {
            $this->line('Skipped '.count($ids)." ({$why}): ".implode(', ', array_slice($ids, 0, 30)).(count($ids) > 30 ? ' …' : ''));
        }
        $this->info('Full report of every change after escalation: '.$reportPath);

        if ($plans === []) {
            $this->info('Nothing to restore.');

            return self::SUCCESS;
        }

        // ---------- 2. confirm ----------
        if (! $this->confirm(count($plans).' lead(s) will be restored. Comments/activities added after the escalation are PERMANENTLY deleted. Continue?', false)) {
            $this->warn('Cancelled — nothing changed.');

            return self::SUCCESS;
        }

        // ---------- 3. apply ----------
        $done = 0;
        $changed = [];
        $bar = $this->output->createProgressBar(count($plans));
        foreach ($plans as $p) {
            $this->restore($p) ? $done++ : $changed[] = $p['lead_id'];
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        if ($done > 0) {
            LeadKanbanHeatCache::bump();
        }
        $this->info("Restored {$done} lead(s).");
        if ($changed !== []) {
            $this->warn('Not restored (owner changed while running): '.implode(', ', $changed));
        }

        return self::SUCCESS;
    }

    protected function restore(array $p): bool
    {
        return DB::transaction(function () use ($p) {
            $leadId = $p['lead_id'];
            $lead = DB::table('leads')->where('id', $leadId)->lockForUpdate()->first();
            if (! $lead || (int) $lead->responsible_person_id !== $p['current_owner']) {
                return false;
            }

            $update = [
                'responsible_person_id' => $p['old_owner'],
                'last_stage_change_at' => now(),
                'updated_at' => now(),
            ];
            // brand follows the owner (same rule as BrandOwned)
            if (BrandCrmScope::isLuxvantMember($p['old_owner'])) {
                $update['brand_key'] = 'luxvant';
            } elseif (($lead->brand_key ?? null) === 'luxvant') {
                $update['brand_key'] = 'oia';
            }

            $history = [
                'action' => 'sla_escalation_undone',
                'old_person_id' => $p['current_owner'],
                'old_person' => $this->userName($p['current_owner']),
                'new_person_id' => $p['old_owner'],
                'new_person' => $this->userName($p['old_owner']),
                'escalation_history_id' => $p['escalation_id'],
                'note' => 'Restored to its state before SLA escalation',
            ];

            if ($p['stage'] && $p['stage'] !== (int) $lead->stage_id) {
                $update['stage_id'] = $p['stage'];
                $update['revert'] = null;
                $update['notified_revert'] = 0;
                $update['notification_times_sent'] = json_encode([]);
                if ($p['pool_reverts'] > 0) {
                    $update['lead_pool_visits'] = max(0, (int) $lead->lead_pool_visits - $p['pool_reverts']);
                }
                $history['old_stage'] = $this->stageNameById[(int) $lead->stage_id] ?? null;
                $history['new_stage'] = $this->stageNameById[$p['stage']] ?? null;
            }

            if ($p['fields'] !== []) {
                $restored = [];
                foreach ($p['fields'] as $col => $old) {
                    $update[$col] = $old;
                    $restored[$col] = ['old' => $lead->{$col} ?? null, 'new' => $old];
                }
                $history['restored_fields'] = $restored;
            }

            // permanently delete comments + activities added after the escalation, keeping a copy in history
            foreach (['lead_comments' => 'comments', 'lead_activities' => 'activities'] as $table => $key) {
                $rows = DB::table($table)->where('lead_id', $leadId)->where('created_at', '>=', $p['since'])->get();
                if ($rows->isEmpty()) {
                    continue;
                }
                $ids = $rows->pluck('id')->all();
                $history['deleted_'.$key] = $rows->map(fn ($r) => (array) $r)->all();
                if ($table === 'lead_comments') {
                    $history['deleted_comment_attachments'] = DB::table('comment_attachments')->whereIn('comment_id', $ids)->get()->map(fn ($r) => (array) $r)->all();
                    DB::table($table)->whereIn('id', $ids)->whereNotNull('parent_id')->delete();
                }
                DB::table($table)->whereIn('id', $ids)->delete();
            }
            if ($lead->first_contacted_at && (string) $lead->first_contacted_at >= $p['since']) {
                $update['first_contacted_at'] = null;
            }

            DB::table('leads')->where('id', $leadId)->update($update);
            DB::table('lead_histories')->insert([
                'lead_id' => $leadId,
                'user_id' => null,
                'changes' => json_encode($history, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        });
    }

    /**
     * Everything that happened to the lead after its first escalation, oldest first.
     *
     * @return array<int, array{at: string, by: string, type: string, action: string, details: string, changes: array}>
     */
    protected function changesAfter(int $leadId, object $firstEscalation): array
    {
        $out = [];

        $history = DB::table('lead_histories')
            ->where('lead_id', $leadId)->where('id', '>', $firstEscalation->id)
            ->orderBy('id')->get(['user_id', 'changes', 'created_at']);
        foreach ($history as $h) {
            $c = json_decode($h->changes, true) ?: [];
            $action = (string) ($c['action'] ?? '');
            if ($action === 'view') {
                continue;
            }
            $out[] = [
                'at' => (string) $h->created_at,
                'by' => $h->user_id ? $this->userName((int) $h->user_id) : 'System',
                'type' => 'history',
                'action' => $action ?: 'update',
                'details' => $this->describe($c),
                'changes' => $c,
            ];
        }

        foreach (['lead_comments' => ['comment', 'comment'], 'lead_activities' => ['activity', 'title']] as $table => [$type, $col]) {
            $rows = DB::table($table)->where('lead_id', $leadId)
                ->where('created_at', '>=', $firstEscalation->created_at)
                ->orderBy('id')->get(['user_id', $col.' as text', 'created_at']);
            foreach ($rows as $r) {
                $out[] = [
                    'at' => (string) $r->created_at,
                    'by' => $this->userName((int) $r->user_id),
                    'type' => $type,
                    'action' => $type.' added',
                    'details' => mb_strimwidth(trim(strip_tags((string) $r->text)), 0, 300, '…'),
                    'changes' => [],
                ];
            }
        }

        usort($out, fn ($a, $b) => strcmp($a['at'], $b['at']));

        return $out;
    }

    protected function describe(array $c): string
    {
        $parts = [];
        if (isset($c['old_stage']) || isset($c['new_stage'])) {
            $parts[] = 'stage: '.($c['old_stage'] ?? '-').' → '.($c['new_stage'] ?? '-');
        }
        if (isset($c['old_person']) || isset($c['new_person'])) {
            $parts[] = 'owner: '.($c['old_person'] ?? '-').' → '.($c['new_person'] ?? '-');
        }
        $rest = array_diff_key($c, array_flip(['action', 'old_stage', 'new_stage', 'old_person', 'new_person', 'old_person_id', 'new_person_id', 'target_stage_id']));
        if ($rest !== []) {
            $parts[] = mb_strimwidth(json_encode($rest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 400, '…');
        }

        return implode(' | ', $parts);
    }

    /** Stage the lead was in when escalated: the "from" of the first stage move after it, else the current stage. */
    protected function stageAtEscalation(array $after, int $currentStageId): ?int
    {
        foreach ($after as $e) {
            $from = $e['changes']['old_stage'] ?? null;
            if (is_string($from) && $from !== '' && ! is_numeric($from)) {
                return $this->stageIdByName[mb_strtolower($from)] ?? null;
            }
        }

        return $currentStageId ?: null;
    }

    /** The stage the lead came from when it last moved into $stageId before the escalation (null if history doesn't show it). */
    protected function stageBefore(int $leadId, int $escalationHistoryId, int $stageId): ?int
    {
        $stageName = mb_strtolower($this->stageNameById[$stageId] ?? '');
        $moves = DB::table('lead_histories')
            ->where('lead_id', $leadId)->where('id', '<', $escalationHistoryId)
            ->whereRaw("JSON_EXTRACT(changes, '$.new_stage') IS NOT NULL")
            ->orderByDesc('id')->limit(50)->pluck('changes');
        foreach ($moves as $json) {
            $c = json_decode($json, true) ?: [];
            if (mb_strtolower((string) ($c['new_stage'] ?? '')) !== $stageName) {
                continue;
            }
            $from = mb_strtolower((string) ($c['old_stage'] ?? ''));
            if ($from !== '' && $from !== $stageName && isset($this->stageIdByName[$from])) {
                return $this->stageIdByName[$from];
            }
        }

        return null;
    }

    /** Lead columns edited after the escalation → their value before the first such edit. */
    protected function fieldsBeforeEscalation(array $after): array
    {
        $restore = [];
        foreach ($after as $e) {
            $fields = $e['changes']['fields'] ?? null;
            if (($e['changes']['action'] ?? null) !== 'updated' || ! is_array($fields)) {
                continue;
            }
            foreach ($fields as $col => $v) {
                if (! is_string($col) || ! is_array($v) || ! array_key_exists('old', $v)
                    || ! isset($this->leadColumns[$col]) || in_array($col, self::FIELD_RESTORE_SKIP, true)
                    || array_key_exists($col, $restore)) {
                    continue;
                }
                $restore[$col] = is_array($v['old']) ? json_encode($v['old'], JSON_UNESCAPED_UNICODE) : $v['old'];
            }
        }

        return $restore;
    }

    protected function countPoolReverts(array $after): int
    {
        if (! $this->poolStageName) {
            return 0;
        }
        $n = 0;
        foreach ($after as $e) {
            if (($e['changes']['action'] ?? null) === 'revert'
                && mb_strtolower((string) ($e['changes']['new_stage'] ?? '')) === mb_strtolower($this->poolStageName)) {
                $n++;
            }
        }

        return $n;
    }

    protected function skipReason(?object $lead, int $oldOwnerId, array $lastC, object $last): ?string
    {
        if (! $lead || $lead->deleted_at || $lead->merged_into_lead_id) {
            return 'lead deleted or merged';
        }
        if ($oldOwnerId <= 0 || ! isset($this->users[$oldOwnerId])) {
            return 'old owner unknown or deleted';
        }
        if (($this->users[$oldOwnerId]['status'] ?? null) === 'blocked') {
            return 'old owner is blocked';
        }
        if ((int) $lead->responsible_person_id === $oldOwnerId) {
            return 'already with old owner';
        }
        $undone = DB::table('lead_histories')
            ->where('lead_id', $lead->id)->where('id', '>', $last->id)
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(changes, '$.action')) = 'sla_escalation_undone'")
            ->exists();
        if ($undone) {
            return 'already restored before';
        }
        if (($this->users[(int) $lead->responsible_person_id]['name'] ?? null) !== ($lastC['new_person'] ?? null)) {
            return 'reassigned by hand after escalation';
        }

        return null;
    }

    protected function loadStages(): void
    {
        foreach (DB::table('stages')->get(['id', 'name', 'stage_type', 'order']) as $s) {
            $this->stageNameById[(int) $s->id] = (string) $s->name;
            if (($s->stage_type ?? 'lead') === 'lead') {
                $this->stageIdByName[mb_strtolower((string) $s->name)] ??= (int) $s->id;
                if ((int) $s->order === 9) {
                    $this->poolStageName ??= (string) $s->name;
                }
            }
        }
    }

    protected function loadUsers(array $ids): void
    {
        $missing = array_diff(array_unique(array_filter(array_map('intval', $ids))), array_keys($this->users));
        if ($missing === []) {
            return;
        }
        foreach (DB::table('users')->whereIn('id', $missing)->get(['id', 'name', 'status']) as $u) {
            $this->users[(int) $u->id] = ['name' => (string) $u->name, 'status' => $u->status];
        }
    }

    protected function userName(int $id): string
    {
        if ($id <= 0) {
            return '-';
        }
        $this->loadUsers([$id]);

        return $this->users[$id]['name'] ?? '#'.$id;
    }

    protected function warnIfSlaStillOn(): void
    {
        try {
            $on = DB::table('lead_assignment_settings')
                ->where('sla_escalation_enabled', 1)->where('sla_minutes', '>=', 5)
                ->where(fn ($q) => $q->whereNull('system_disabled')->orWhere('system_disabled', 0))
                ->exists();
            if ($on) {
                $this->warn('SLA escalation is still ON — turn it off first, or restored leads can be escalated again.');
            }
        } catch (\Throwable) {
        }
    }
}
