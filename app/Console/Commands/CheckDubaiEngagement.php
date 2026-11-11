<?php

namespace App\Console\Commands;

use App\Models\LeadActivity;
use App\Models\LeadComment;
use App\Models\User;
use App\Services\Bitrix24\Bitrix24Client;
use App\Services\Bitrix24\Bitrix24LeadImporter;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Check (read-only unless --import) of Bitrix24 comments + activities on Dubai leads since a date,
 * compared with what the CRM already has (lead_comments / lead_activities.bitrix24_id).
 *
 *   php artisan leads:check-dubai-engagement --since=2026-10-27
 *
 * Writes the Bitrix lead IDs that are missing something to
 * storage/app/dubai-engagement-to-import.txt — import them with
 *   php artisan bitrix:import-leads-by-id --file=storage/app/dubai-engagement-to-import.txt
 */
class CheckDubaiEngagement extends Command
{
    protected $signature = 'leads:check-dubai-engagement
        {--since= : Start date, e.g. 2026-10-27 (default: 7 days ago)}
        {--until= : End date (default: now)}
        {--branch=Dubai : Branch name}
        {--all : Check comments on EVERY branch lead (slow); default only leads changed in Bitrix since the date}
        {--show=50 : How many leads to list in the table}
        {--comments-only : Only list the leads that have Bitrix24 comments in the period (skip activities)}
        {--activities-only : Only list the leads that have Bitrix24 activities in the period (skip comments)}
        {--missing : With --comments-only / --activities-only: list only leads with items NOT in the CRM}
        {--import : Insert ONLY the missing comments/activities into the CRM leads (nothing else on the lead changes)}
        {--force : With --import: do not ask for confirmation}';

    protected $description = 'Check Bitrix24 comments/activities on Dubai leads since a date vs the CRM (read-only)';

    public function handle(Bitrix24Client $client): int
    {
        $since = $this->option('since') ? Carbon::parse($this->option('since'))->startOfDay() : now()->subDays(7)->startOfDay();
        $until = $this->option('until') ? Carbon::parse($this->option('until'))->endOfDay() : now();
        $branchName = (string) $this->option('branch');

        $what = $this->option('comments-only') ? 'comments'
            : ($this->option('activities-only') ? 'activities' : 'comments & activities');
        $this->info("Checking {$branchName} leads — {$what} from {$since->toDateTimeString()} to {$until->toDateTimeString()}");

        // 1) Local branch leads that came from Bitrix.
        $leads = $this->branchLeads($branchName);
        if ($leads->isEmpty()) {
            $this->warn("No {$branchName} leads with a Bitrix24 ID found.");
            return self::SUCCESS;
        }
        $byB24 = $leads->keyBy('bitrix24_id');
        $this->line("Local {$branchName} leads with Bitrix ID: {$leads->count()}");

        $sinceB24 = $since->format('Y-m-d\TH:i:sP');
        $untilB24 = $until->format('Y-m-d\TH:i:sP');

        $commentsOnly = (bool) $this->option('comments-only');
        $activitiesOnly = (bool) $this->option('activities-only') && ! $commentsOnly;

        // 2) All Bitrix activities on leads in the date range (one paged list), keep branch ones.
        $b24Activities = [];   // b24 lead id => [activity ids]
        $lastActivity = [];    // b24 lead id => latest activity created time
        $activityRows = [];    // activity id => [b24 lead id, raw Bitrix row] (for --import)
        $commentRows = [];     // comment id  => [b24 lead id, raw Bitrix row] (for --import)
        $start = $commentsOnly ? null : 0;
        if (! $commentsOnly) {
            $this->line('Fetching Bitrix24 activities…');
        }
        while ($start !== null) {
            $r = $client->call('crm.activity.list', [
                'filter' => ['OWNER_TYPE_ID' => 1, '>=CREATED' => $sinceB24, '<=CREATED' => $untilB24],
                'select' => ['ID', 'OWNER_ID', 'CREATED', 'SUBJECT', 'RESPONSIBLE_ID', 'AUTHOR_ID',
                    'DEADLINE', 'START_TIME', 'END_TIME', 'COMPLETED'],
                'order' => ['ID' => 'ASC'],
                'start' => $start,
            ]);
            foreach ($r['result'] ?? [] as $a) {
                $owner = (int) ($a['OWNER_ID'] ?? 0);
                if (isset($byB24[$owner])) {
                    $b24Activities[$owner][] = (int) $a['ID'];
                    $activityRows[(int) $a['ID']] = [$owner, $a];
                    $created = Carbon::parse($a['CREATED'] ?? 'now');
                    if (! isset($lastActivity[$owner]) || $created->gt($lastActivity[$owner])) {
                        $lastActivity[$owner] = $created;
                    }
                }
            }
            $start = $r['next'] ?? null;
        }

        if ($activitiesOnly) {
            $haveAct = $this->existingIds('lead_activities', array_merge([], ...array_values($b24Activities)));
            if ($this->option('import')) {
                if (! $this->importMissing($client, [], $activityRows, [], $haveAct, $byB24)) {
                    return self::SUCCESS;
                }
                $haveAct = $this->existingIds('lead_activities', array_merge([], ...array_values($b24Activities)));
            }
            return $this->reportLeads('activities', $b24Activities, $lastActivity, $haveAct, $byB24, $branchName);
        }

        // 3) Which leads to check for comments.
        if ($this->option('all')) {
            $candidates = $leads->pluck('bitrix24_id')->all();
        } else {
            $this->line('Finding leads changed in Bitrix24 since the date…');
            $candidates = array_keys($b24Activities);
            foreach (['DATE_MODIFY', 'LAST_ACTIVITY_TIME'] as $field) {
                try {
                    foreach ($this->listLeadIds($client, ['>='.$field => $sinceB24]) as $id) {
                        if (isset($byB24[$id])) {
                            $candidates[] = $id;
                        }
                    }
                } catch (\Throwable $e) {
                    $this->warn("  (skipped {$field} filter: {$e->getMessage()})");
                }
            }
            $candidates = array_values(array_unique($candidates));
        }
        $this->line('Leads to check for comments: '.count($candidates));

        // 4) Comments per lead, 50 leads per Bitrix batch call.
        $b24Comments = [];   // b24 lead id => [comment ids]
        $lastComment = [];   // b24 lead id => latest comment time in the period
        $bar = $this->output->createProgressBar(count($candidates));
        foreach (array_chunk($candidates, 50) as $chunk) {
            $cmd = [];
            foreach ($chunk as $id) {
                $cmd['c'.$id] = 'crm.timeline.comment.list?'.http_build_query([
                    'filter' => ['ENTITY_TYPE' => 'lead', 'ENTITY_ID' => $id],
                    'select' => ['ID', 'CREATED', 'COMMENT', 'AUTHOR_ID'],
                ]);
            }
            $res = $client->call('batch', ['halt' => 0, 'cmd' => $cmd]);
            $results = $res['result']['result'] ?? [];
            $nexts = $res['result']['result_next'] ?? [];

            foreach ($chunk as $id) {
                $rows = $results['c'.$id] ?? [];
                if (isset($nexts['c'.$id])) {
                    $rows = $client->listTimelineComments($id); // more than 50 comments: fetch all
                }
                foreach ($rows as $c) {
                    if (trim(strip_tags((string) ($c['COMMENT'] ?? ''))) === '') {
                        continue; // the importer skips empty comments too
                    }
                    $created = Carbon::parse($c['CREATED'] ?? 'now');
                    if ($created->betweenIncluded($since, $until)) {
                        $b24Comments[$id][] = (int) $c['ID'];
                        $commentRows[(int) $c['ID']] = [$id, $c];
                        if (! isset($lastComment[$id]) || $created->gt($lastComment[$id])) {
                            $lastComment[$id] = $created;
                        }
                    }
                }
                $bar->advance();
            }
        }
        $bar->finish();
        $this->newLine(2);

        // 5) Compare with the CRM.
        $allActIds = array_merge([], ...array_values($b24Activities));
        $allComIds = array_merge([], ...array_values($b24Comments));
        $haveAct = $this->existingIds('lead_activities', $allActIds);
        $haveCom = $this->existingIds('lead_comments', $allComIds);

        if ($this->option('import')) {
            if (! $this->importMissing($client, $commentRows, $activityRows, $haveCom, $haveAct, $byB24)) {
                return self::SUCCESS;
            }
            // Re-read so the report below shows what is still missing (should be 0).
            $haveAct = $this->existingIds('lead_activities', $allActIds);
            $haveCom = $this->existingIds('lead_comments', $allComIds);
        }

        if ($commentsOnly) {
            return $this->reportLeads('comments', $b24Comments, $lastComment, $haveCom, $byB24, $branchName);
        }

        $rows = [];
        $missingAct = 0;
        $missingCom = 0;
        foreach (array_unique(array_merge(array_keys($b24Activities), array_keys($b24Comments))) as $b24Id) {
            $mA = count(array_diff($b24Activities[$b24Id] ?? [], $haveAct));
            $mC = count(array_diff($b24Comments[$b24Id] ?? [], $haveCom));
            $missingAct += $mA;
            $missingCom += $mC;
            if ($mA || $mC) {
                $lead = $byB24[$b24Id];
                $rows[] = [$b24Id, $lead->id, mb_strimwidth((string) $lead->lead_name, 0, 30, '…'),
                    count($b24Comments[$b24Id] ?? []), $mC, count($b24Activities[$b24Id] ?? []), $mA];
            }
        }

        // Also show what was written directly in the new CRM in the same period.
        $leadIds = $leads->pluck('id');
        $crmComments = DB::table('lead_comments')->whereIn('lead_id', $leadIds)->whereNull('bitrix24_id')
            ->whereBetween('created_at', [$since, $until])->count();
        $crmActivities = DB::table('lead_activities')->whereIn('lead_id', $leadIds)->whereNull('bitrix24_id')
            ->whereBetween('created_at', [$since, $until])->count();

        $this->table(['', 'In Bitrix24', 'Missing in CRM'], [
            ['Comments', count($allComIds), $missingCom],
            ['Activities', count($allActIds), $missingAct],
        ]);
        $this->line("Added directly in the CRM in this period: {$crmComments} comment(s), {$crmActivities} activit(ies).");

        if (! $rows) {
            $this->info('✓ All Bitrix24 comments and activities in this period are in the CRM.');
            return self::SUCCESS;
        }

        usort($rows, fn ($a, $b) => ($b[4] + $b[6]) <=> ($a[4] + $a[6]));
        $show = max(1, (int) $this->option('show'));
        $this->warn(count($rows).' lead(s) are missing comments/activities:');
        $this->table(['Bitrix ID', 'CRM ID', 'Lead', 'B24 comments', 'Missing', 'B24 activities', 'Missing'], array_slice($rows, 0, $show));
        if (count($rows) > $show) {
            $this->line('… and '.(count($rows) - $show).' more (use --show=N).');
        }

        $path = storage_path('app/dubai-engagement-to-import.txt');
        file_put_contents($path, implode(PHP_EOL, array_column($rows, 0)).PHP_EOL);
        $this->newLine();
        $this->info("Bitrix lead IDs saved to: {$path}");
        $this->line('To bring them in run:');
        $this->line('  php artisan bitrix:import-leads-by-id --file='.$path);

        return self::SUCCESS;
    }

    /**
     * --import: insert only the Bitrix comments/activities that are not in the CRM yet.
     * Same mapping as Bitrix24LeadImporter (author, text cleaning, dates), but nothing else
     * on the lead (stage, responsible, contact fields…) is touched. Returns false if cancelled.
     */
    private function importMissing(Bitrix24Client $client, array $commentRows, array $activityRows, array $haveCom, array $haveAct, $byB24): bool
    {
        $haveCom = array_flip($haveCom);
        $haveAct = array_flip($haveAct);
        $newComments = array_diff_key($commentRows, $haveCom);
        $newActivities = array_diff_key($activityRows, $haveAct);

        if (! $newComments && ! $newActivities) {
            $this->info('Nothing to import — no missing comments/activities.');
            return true;
        }

        $this->warn('Will add '.count($newComments).' comment(s) and '.count($newActivities)
            .' activit(ies) to '.count(array_unique(array_merge(array_column($newComments, 0), array_column($newActivities, 0))))
            .' lead(s). Nothing else on the leads changes.');
        if (! $this->option('force') && ! $this->confirm('Continue?', true)) {
            $this->line('Cancelled — nothing imported.');
            return false;
        }

        $importer = new Bitrix24LeadImporter($client, 1);
        $parse = fn ($v) => ($v === null || $v === '') ? null : Carbon::parse($v)->setTimezone(config('app.timezone'));
        $addedC = 0;
        $addedA = 0;

        foreach ($newComments as $b24CommentId => [$b24LeadId, $c]) {
            $body = $importer->cleanRichText($c['COMMENT'] ?? '') ?? '';
            if ($body === '') {
                continue;
            }
            $createdAt = $parse($c['CREATED'] ?? null) ?? now();
            LeadComment::withoutEvents(fn () => LeadComment::create([
                'lead_id' => $byB24[$b24LeadId]->id,
                'user_id' => $importer->mapBitrixUser($c['AUTHOR_ID'] ?? null) ?? 1,
                'comment' => $body,
                'bitrix24_id' => $b24CommentId,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]));
            $addedC++;
        }

        foreach ($newActivities as $b24ActivityId => [$b24LeadId, $a]) {
            $deadline = $parse($a['DEADLINE'] ?? null) ?? $parse($a['START_TIME'] ?? null)
                ?? $parse($a['END_TIME'] ?? null) ?? $parse($a['CREATED'] ?? null) ?? now();
            LeadActivity::withoutEvents(fn () => LeadActivity::create([
                'lead_id' => $byB24[$b24LeadId]->id,
                'user_id' => $importer->mapBitrixUser($a['RESPONSIBLE_ID'] ?? $a['AUTHOR_ID'] ?? null) ?? 1,
                'title' => trim((string) ($a['SUBJECT'] ?? '')) ?: 'Bitrix24 activity',
                'reminder_date' => $deadline,
                'reminders' => null,
                'is_completed' => ($a['COMPLETED'] ?? 'N') === 'Y',
                'bitrix24_id' => $b24ActivityId,
                'created_at' => $parse($a['CREATED'] ?? null) ?? now(),
                'updated_at' => now(),
            ]));
            $addedA++;
        }

        $this->info("✓ Imported {$addedC} comment(s) and {$addedA} activit(ies).");
        $this->newLine();

        return true;
    }

    /**
     * --comments-only / --activities-only: every branch lead with Bitrix items in the period
     * (imported or not); with --missing only the leads that still lack some in the CRM.
     *
     * @param  string  $kind  'comments' | 'activities'
     */
    private function reportLeads(string $kind, array $b24Items, array $lastAt, array $have, $byB24, string $branchName): int
    {
        $one = rtrim($kind === 'activities' ? 'activity' : 'comment');
        if (! $b24Items) {
            $this->info("No {$branchName} leads have Bitrix24 {$kind} in this period.");
            return self::SUCCESS;
        }

        $rows = [];
        $totalMissing = 0;
        foreach ($b24Items as $b24Id => $ids) {
            $missing = count(array_diff($ids, $have));
            $totalMissing += $missing;
            $lead = $byB24[$b24Id];
            $rows[] = [$b24Id, $lead->id, mb_strimwidth((string) $lead->lead_name, 0, 30, '…'),
                count($ids), $missing, $lastAt[$b24Id]->format('Y-m-d H:i')];
        }
        usort($rows, fn ($a, $b) => strcmp($b[5], $a[5])); // newest first

        $this->info(count($rows)." {$branchName} lead(s) have {$kind} in this period — "
            .array_sum(array_column($rows, 3))." {$kind}, {$totalMissing} not in the CRM yet.");

        if ($this->option('missing')) {
            $rows = array_values(array_filter($rows, fn ($r) => $r[4] > 0));
            if (! $rows) {
                $this->info("✓ No missing {$kind} — every Bitrix24 {$one} in this period is in the CRM.");
                return self::SUCCESS;
            }
            $this->warn(count($rows)." lead(s) have Bitrix24 {$kind} that are not in the CRM:");
        }
        $show = max(1, (int) $this->option('show'));
        $this->table(['Bitrix ID', 'CRM ID', 'Lead', ucfirst($kind), 'Not in CRM', 'Last '.$one], array_slice($rows, 0, $show));
        if (count($rows) > $show) {
            $this->line('… and '.(count($rows) - $show).' more (use --show=N).');
        }

        $file = $this->option('missing') ? "dubai-leads-missing-{$kind}.txt" : "dubai-leads-with-{$kind}.txt";
        $path = storage_path('app/'.$file);
        file_put_contents($path, implode(PHP_EOL, array_column($rows, 0)).PHP_EOL);
        $this->newLine();
        $this->info("All Bitrix lead IDs saved to: {$path}");
        if ($totalMissing) {
            $this->line("To bring the missing {$kind} in run:");
            $this->line('  php artisan bitrix:import-leads-by-id --file='.$path);
        }

        return self::SUCCESS;
    }

    /** Branch leads: lead.branch = branch, or no branch and (branch source / responsible in that branch). */
    private function branchLeads(string $branchName)
    {
        $branchUser = User::role('admin')->whereRaw('LOWER(name) = ?', [mb_strtolower($branchName)])->first();
        $teamIds = $branchUser ? $branchUser->getAllSubordinatesIds() : [];

        return DB::table('leads')
            ->whereNull('deleted_at')
            ->whereNotNull('bitrix24_id')
            ->where(function ($q) use ($branchName, $teamIds) {
                $q->where('branch', $branchName)
                    ->orWhere(function ($q2) use ($branchName, $teamIds) {
                        $q2->where(fn ($b) => $b->whereNull('branch')->orWhere('branch', ''))
                            ->where(function ($q3) use ($branchName, $teamIds) {
                                $q3->where('lead_branch_source', 'like', '%'.$branchName.'%');
                                if ($teamIds) {
                                    $q3->orWhereIn('responsible_person_id', $teamIds);
                                }
                            });
                    });
            })
            ->get(['id', 'bitrix24_id', 'lead_name'])
            ->map(function ($l) {
                $l->bitrix24_id = (int) $l->bitrix24_id;
                return $l;
            });
    }

    /** @return int[] Bitrix lead IDs matching the filter. */
    private function listLeadIds(Bitrix24Client $client, array $filter): array
    {
        $ids = [];
        $start = 0;
        do {
            $r = $client->call('crm.lead.list', ['filter' => $filter, 'select' => ['ID'], 'order' => ['ID' => 'ASC'], 'start' => $start]);
            foreach ($r['result'] ?? [] as $row) {
                $ids[] = (int) $row['ID'];
            }
            $start = $r['next'] ?? null;
        } while ($start !== null);

        return $ids;
    }

    /** @return int[] which of the given Bitrix IDs already exist in the table. */
    private function existingIds(string $table, array $ids): array
    {
        $found = [];
        foreach (array_chunk(array_values(array_unique($ids)), 1000) as $chunk) {
            $found = array_merge($found, DB::table($table)->whereIn('bitrix24_id', $chunk)->pluck('bitrix24_id')->map(fn ($v) => (int) $v)->all());
        }

        return $found;
    }
}
