<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadComment;
use App\Models\User;
use App\Services\Bitrix24\Bitrix24Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Single command covering both cases for lead_comments.user_id / lead_activities.user_id:
 *
 *  1) Rows with a bitrix24_id (imported from Bitrix) — CHECKED AND FIXED against the
 *     current Bitrix24 author (AUTHOR_ID for comments, RESPONSIBLE_ID falling back to
 *     AUTHOR_ID for activities — same fallback Bitrix24LeadImporter uses on import),
 *     mapped to the local user via users.bitrix24_id.
 *  2) Rows with no bitrix24_id (added directly in the CRM via LeadController /
 *     LeadActivityController, not synced from Bitrix) — nothing to compare them
 *     against, so these are only FLAGGED if their user_id is broken (points at a
 *     user id that no longer exists), never auto-fixed.
 *
 * Only leads that actually have at least one such row are visited at all — starts
 * from the comments/activities themselves, not from every lead, and skips both the
 * local queries and the Bitrix lookup for leads with neither.
 *
 * Batched against Bitrix: Bitrix has no flat "all comments"/"all activities" endpoint
 * — they're normally fetched per lead. At real-world volume (hundreds of thousands of
 * leads) that's hundreds of thousands of sequential HTTP round trips — hours. Instead
 * this packs up to BATCH_SIZE crm.timeline.comment.list / crm.activity.list calls (one
 * or two per lead) into a single Bitrix `batch` REST call, cutting round trips by
 * roughly BATCH_SIZE×. Trade-off: a batched list call returns one page (Bitrix's
 * default page size, typically 50) rather than the full paginated history — a lead
 * with more than that many comments or activities will only have the first page
 * checked. Such leads are logged (storage/logs/sync-engagement-authors.log) so they
 * can be re-checked individually with --lead-id, which still only reads one page but
 * at least makes it obvious which leads need a closer look.
 *
 * Resumable: the last lead id fully processed is checkpointed after every lead (real
 * runs only, --dry-run never touches it), so a stopped/crashed/Ctrl+C'd run picks up
 * right after where it left off next time instead of starting over. Pass --restart to
 * discard the checkpoint and scan from the first lead again. The checkpoint only
 * auto-clears itself once an unrestricted run (no --limit/--lead-id) reaches the true
 * end of the dataset.
 *
 *   php artisan bitrix24:sync-engagement-authors --dry-run --limit=20
 *   php artisan bitrix24:sync-engagement-authors --lead-id=123 --dry-run
 *   php artisan bitrix24:sync-engagement-authors
 *   php artisan bitrix24:sync-engagement-authors --restart
 */
class SyncEngagementAuthors extends Command
{
    protected $signature = 'bitrix24:sync-engagement-authors
        {--dry-run : Show what would change without writing}
        {--limit=0 : Only process the first N local leads that have a bitrix24_id (0 = all)}
        {--lead-id= : Only process this one local lead id (for testing)}
        {--restart : Ignore any saved resume checkpoint and start over from the first lead}';

    /** Cache key: last local lead id fully processed, so a stopped/crashed run resumes
     *  from there instead of rescanning from the first lead. Real runs only — --dry-run
     *  never reads or writes this, so a preview run can't disturb a real run's progress. */
    private const RESUME_KEY = 'bitrix24_engagement_authors_resume_lead_id';

    /** Bitrix24 REST batch hard limit: at most 50 commands per HTTP request. */
    private const BATCH_SIZE = 50;

    protected $description = "Check/fix lead_comments.user_id and lead_activities.user_id against Bitrix24 (linked rows), and flag broken user_id on rows with no Bitrix link";

    /** @var array<string, int> */
    private array $counts = [
        'leads_scanned' => 0,
        'comments_updated' => 0,
        'activities_updated' => 0,
        'unmapped' => 0,
        'no_local' => 0,
        'comments_unlinked_broken' => 0,
        'activities_unlinked_broken' => 0,
    ];

    /** @var array<int, int> bitrix24 user id => how many rows point to it (but it isn't in our DB) */
    private array $unmappedUsers = [];

    private function logChange(string $message, array $context = []): void
    {
        Log::channel('sync_engagement_authors')->info($message, $context);
    }

    public function handle(): int
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);

        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $onlyLeadId = $this->option('lead-id') ? (int) $this->option('lead-id') : null;

        $t0 = microtime(true);
        $this->line('➤ Loading users.bitrix24_id map…');
        $userMap = User::whereNotNull('bitrix24_id')->pluck('id', 'bitrix24_id');
        $this->line(sprintf('  %d user(s) mapped (%.1fs)', $userMap->count(), microtime(true) - $t0));
        if ($userMap->isEmpty()) {
            $this->warn('No users have bitrix24_id — run bitrix24:provision-users first.');
            return self::SUCCESS;
        }

        $t0 = microtime(true);
        $this->line('➤ Connecting to Bitrix24…');
        try {
            $client = new Bitrix24Client();
        } catch (\Throwable $e) {
            $this->error('Bitrix24 is not configured: '.$e->getMessage());
            return self::FAILURE;
        }
        $this->line(sprintf('  connected (%.1fs)', microtime(true) - $t0));

        // Start from the comments/activities themselves, not from every lead — only leads
        // that actually have at least one locally-imported comment or activity are worth
        // visiting at all, so this skips the Bitrix call (and the loop iteration) entirely
        // for leads with neither, instead of looping every lead and discovering that per-lead.
        //
        // This MUST stay an EXISTS-based filter, not `whereIn('id', $allMatchingIds)` — on
        // a large table that id list can run into the hundreds of thousands, which inlines
        // into a WHERE IN (...) clause bigger than the server's max_allowed_packet. MySQL
        // then receives a truncated/garbled query and throws a confusing, unrelated-looking
        // error (e.g. "Unknown column") instead of an obvious size error. EXISTS lets MySQL
        // use the lead_comments.lead_id / lead_activities.lead_id indexes directly — no id
        // list ever gets built in PHP or sent over the wire.
        $hasComment = function ($q) {
            $q->selectRaw('1')
                ->from('lead_comments')
                ->whereColumn('lead_comments.lead_id', 'leads.id')
                ->whereNotNull('lead_comments.bitrix24_id');
        };
        $hasActivity = function ($q) {
            $q->selectRaw('1')
                ->from('lead_activities')
                ->whereColumn('lead_activities.lead_id', 'leads.id')
                ->whereNotNull('lead_activities.bitrix24_id');
        };

        $leadsQuery = Lead::withTrashed()
            ->whereNotNull('bitrix24_id')
            ->where(fn ($q) => $q->whereExists($hasComment)->orWhereExists($hasActivity))
            ->orderBy('id');

        if ($onlyLeadId) {
            $leadsQuery->where('id', $onlyLeadId);
        } elseif (! $dryRun) {
            if ($this->option('restart')) {
                Cache::forget(self::RESUME_KEY);
            } elseif ($resumeFrom = Cache::get(self::RESUME_KEY)) {
                $leadsQuery->where('id', '>', $resumeFrom);
                $this->info("Resuming after lead id {$resumeFrom} (use --restart to scan from the beginning instead).");
            }
        }

        if ($limit > 0) {
            $leadsQuery->limit($limit);
        }

        // Only a genuinely unrestricted run (no --limit, no --lead-id) can reach the true
        // end of the dataset — that's the only case where finishing means "start fresh
        // next time" instead of "there may be more left for the next chunk to pick up".
        $canReachEnd = $limit <= 0 && ! $onlyLeadId;

        $t0 = microtime(true);
        $this->line('➤ Fetching lead records…');
        $leads = $leadsQuery->get(['id', 'bitrix24_id', 'lead_name']);
        $this->line(sprintf('  %d lead(s) to process (%.1fs)', $leads->count(), microtime(true) - $t0));

        if ($leads->isEmpty()) {
            $this->warn('No matching leads with a locally-imported comment or activity found.');
            return self::SUCCESS;
        }

        $this->logChange('Sync started', ['dry_run' => $dryRun, 'lead_count' => $leads->count(), 'lead_id_filter' => $onlyLeadId]);

        /** @var ProgressBar $bar */
        $bar = $this->output->createProgressBar($leads->count());
        $bar->setFormat(" %current%/%max% [%bar%] %percent:3s%%  ETA: %remaining:-10s%  %message%\n");
        $bar->setMessage('starting…');
        $bar->start();

        $queue = $leads->values();
        $queueTotal = $queue->count();
        $cursor = 0;

        while ($cursor < $queueTotal) {
            // Pack leads into this batch until either the queue runs out or the next
            // lead's command(s) would exceed Bitrix's 50-commands-per-request limit.
            $commands = [];
            $batchLeads = [];

            while ($cursor < $queueTotal) {
                $lead = $queue[$cursor];

                $localComments = LeadComment::where('lead_id', $lead->id)
                    ->whereNotNull('bitrix24_id')
                    ->get(['id', 'user_id', 'bitrix24_id'])
                    ->keyBy('bitrix24_id');
                $localActivities = LeadActivity::where('lead_id', $lead->id)
                    ->whereNotNull('bitrix24_id')
                    ->get(['id', 'user_id', 'bitrix24_id'])
                    ->keyBy('bitrix24_id');

                $wantsComments = $localComments->isNotEmpty();
                $wantsActivities = $localActivities->isNotEmpty();
                $needed = ($wantsComments ? 1 : 0) + ($wantsActivities ? 1 : 0);

                if ($needed === 0) {
                    // The EXISTS filter guarantees this shouldn't happen, but stay safe.
                    $cursor++;
                    $this->finishLead($lead, $dryRun, $bar);
                    continue;
                }

                if (count($commands) + $needed > self::BATCH_SIZE) {
                    break; // doesn't fit — leave it for the next batch
                }

                $b24LeadId = (int) $lead->bitrix24_id;
                if ($wantsComments) {
                    $commands["c_{$lead->id}"] = "crm.timeline.comment.list?filter[ENTITY_TYPE]=lead&filter[ENTITY_ID]={$b24LeadId}&order[CREATED]=ASC";
                }
                if ($wantsActivities) {
                    $commands["a_{$lead->id}"] = "crm.activity.list?filter[OWNER_ID]={$b24LeadId}&filter[OWNER_TYPE_ID]=1&order[CREATED]=ASC";
                }

                $batchLeads[] = [
                    'lead' => $lead,
                    'wants_comments' => $wantsComments,
                    'wants_activities' => $wantsActivities,
                    'local_comments' => $localComments,
                    'local_activities' => $localActivities,
                ];
                $cursor++;
            }

            if (empty($batchLeads)) {
                continue;
            }

            $results = [];
            $errors = [];
            try {
                $response = $client->batch($commands);
                $results = $response['result']['result'] ?? [];
                $errors = $response['result']['result_error'] ?? [];
            } catch (\Throwable $e) {
                foreach ($batchLeads as $entry) {
                    $this->logChange('Lead batch failed', ['lead_id' => $entry['lead']->id, 'error' => $e->getMessage()]);
                }
            }

            foreach ($batchLeads as $entry) {
                $lead = $entry['lead'];

                if ($entry['wants_comments']) {
                    $key = "c_{$lead->id}";
                    if (isset($errors[$key])) {
                        $this->logChange('Lead comments batch item failed', ['lead_id' => $lead->id, 'error' => $errors[$key]]);
                    } else {
                        $items = $results[$key] ?? [];
                        $this->reconcileComments($lead, $entry['local_comments'], $items, $userMap, $dryRun);
                        if (count($items) >= 50) {
                            $this->logChange('Lead may have more comments than one batch page covers — re-check with --lead-id', ['lead_id' => $lead->id, 'fetched' => count($items)]);
                        }
                    }
                }

                if ($entry['wants_activities']) {
                    $key = "a_{$lead->id}";
                    if (isset($errors[$key])) {
                        $this->logChange('Lead activities batch item failed', ['lead_id' => $lead->id, 'error' => $errors[$key]]);
                    } else {
                        $items = $results[$key] ?? [];
                        $this->reconcileActivities($lead, $entry['local_activities'], $items, $userMap, $dryRun);
                        if (count($items) >= 50) {
                            $this->logChange('Lead may have more activities than one batch page covers — re-check with --lead-id', ['lead_id' => $lead->id, 'fetched' => count($items)]);
                        }
                    }
                }

                $this->finishLead($lead, $dryRun, $bar);
            }
        }

        $bar->finish();

        // Reached the true end of the dataset (no --limit/--lead-id cutting it short) —
        // start fresh next time instead of resuming from here forever.
        if ($canReachEnd && ! $dryRun) {
            Cache::forget(self::RESUME_KEY);
        }

        $t0 = microtime(true);
        $this->line('➤ Flagging broken user_id on rows with no Bitrix link…');
        $this->flagUnlinkedBrokenRows();
        $this->line(sprintf('  done (%.1fs)', microtime(true) - $t0));

        $this->logChange('Sync finished', array_merge($this->counts, ['dry_run' => $dryRun]));

        $this->newLine(2);
        $verb = $dryRun ? 'Would update' : 'Updated';
        $this->info("{$verb} user_id on {$this->counts['comments_updated']} comment(s) and {$this->counts['activities_updated']} activity/activities. "
            ."(leads scanned: {$this->counts['leads_scanned']}, unmapped bitrix users: {$this->counts['unmapped']}, rows not found locally: {$this->counts['no_local']})");
        if ($this->counts['comments_unlinked_broken'] > 0 || $this->counts['activities_unlinked_broken'] > 0) {
            $this->warn("Also flagged {$this->counts['comments_unlinked_broken']} comment(s) and {$this->counts['activities_unlinked_broken']} activity/activities "
                .'with a broken user_id and no Bitrix link to fix them from (added directly in the CRM) — see the log for details.');
        }
        $this->line('Detailed per-row changes: storage/logs/sync-engagement-authors.log');

        if (! empty($this->unmappedUsers)) {
            arsort($this->unmappedUsers);
            $distinct = count($this->unmappedUsers);
            $this->newLine();
            $this->warn("{$distinct} distinct Bitrix24 user(s) are not in your DB (provision them with `bitrix24:provision-users`):");
            foreach ($this->unmappedUsers as $b24UserId => $rowCount) {
                $this->line("  Bitrix24 user #{$b24UserId} → {$rowCount} row(s)");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Rows with no bitrix24_id (added directly in the CRM, not synced from Bitrix) have
     * nothing to reconcile against — just report broken ones (read-only, never fixed here).
     */
    private function flagUnlinkedBrokenRows(): void
    {
        $brokenComments = LeadComment::whereNull('bitrix24_id')
            ->whereNotNull('user_id')
            ->whereNotIn('user_id', fn ($q) => $q->select('id')->from('users'))
            ->get(['id', 'lead_id', 'user_id']);

        foreach ($brokenComments as $row) {
            $this->counts['comments_unlinked_broken']++;
            $this->logChange('lead_comments.user_id broken (no Bitrix link to fix from)', [
                'comment_id' => $row->id, 'lead_id' => $row->lead_id, 'broken_user_id' => $row->user_id,
            ]);
        }

        $brokenActivities = LeadActivity::whereNull('bitrix24_id')
            ->whereNotNull('user_id')
            ->whereNotIn('user_id', fn ($q) => $q->select('id')->from('users'))
            ->get(['id', 'lead_id', 'user_id']);

        foreach ($brokenActivities as $row) {
            $this->counts['activities_unlinked_broken']++;
            $this->logChange('lead_activities.user_id broken (no Bitrix link to fix from)', [
                'activity_id' => $row->id, 'lead_id' => $row->lead_id, 'broken_user_id' => $row->user_id,
            ]);
        }
    }

    private function resolveLocalUser(int $b24UserId, $userMap): ?int
    {
        if ($b24UserId <= 0) {
            return null;
        }
        $local = (int) ($userMap[$b24UserId] ?? 0);
        if (! $local) {
            $this->counts['unmapped']++;
            $this->unmappedUsers[$b24UserId] = ($this->unmappedUsers[$b24UserId] ?? 0) + 1;
            return null;
        }
        return $local;
    }

    /** Bookkeeping shared by every lead once its comments/activities are reconciled
     *  (or skipped because it had neither): bump the count, checkpoint, advance the bar. */
    private function finishLead(Lead $lead, bool $dryRun, ProgressBar $bar): void
    {
        $this->counts['leads_scanned']++;

        // Saved after every lead (not just at the end) so a Ctrl+C, crash, or timeout
        // mid-run loses nothing — the next invocation resumes right after this lead
        // instead of rescanning everything already checked.
        if (! $dryRun) {
            Cache::put(self::RESUME_KEY, $lead->id, now()->addDays(7));
        }

        $bar->advance();
        $bar->setMessage("updated: {$this->counts['comments_updated']} comments, {$this->counts['activities_updated']} activities");
    }

    /**
     * Pure reconciliation — no Bitrix call here, $items is whatever came back from this
     * lead's "c_{id}" slot in the batch response (one page, see the class docblock).
     */
    private function reconcileComments(Lead $lead, $byBitrixId, array $items, $userMap, bool $dryRun): void
    {
        foreach ($items as $c) {
            $b24CommentId = (int) ($c['ID'] ?? 0);
            if ($b24CommentId <= 0 || ! $byBitrixId->has($b24CommentId)) {
                if ($b24CommentId > 0) {
                    $this->counts['no_local']++;
                }
                continue;
            }

            $newUser = $this->resolveLocalUser((int) ($c['AUTHOR_ID'] ?? 0), $userMap);
            if ($newUser === null) {
                continue;
            }

            $row = $byBitrixId->get($b24CommentId);
            $oldUser = (int) $row->user_id;
            if ($oldUser === $newUser) {
                continue;
            }

            $this->logChange('lead_comments.user_id updated', [
                'comment_id' => $row->id, 'lead_id' => $lead->id, 'bitrix24_comment_id' => $b24CommentId,
                'old' => $oldUser, 'new' => $newUser, 'dry_run' => $dryRun,
            ]);
            $this->counts['comments_updated']++;

            if (! $dryRun) {
                LeadComment::withoutEvents(fn () => LeadComment::whereKey($row->id)->update(['user_id' => $newUser]));
            }
        }
    }

    /**
     * Pure reconciliation — no Bitrix call here, $items is whatever came back from this
     * lead's "a_{id}" slot in the batch response (one page, see the class docblock).
     */
    private function reconcileActivities(Lead $lead, $byBitrixId, array $items, $userMap, bool $dryRun): void
    {
        foreach ($items as $a) {
            $b24ActivityId = (int) ($a['ID'] ?? 0);
            if ($b24ActivityId <= 0 || ! $byBitrixId->has($b24ActivityId)) {
                if ($b24ActivityId > 0) {
                    $this->counts['no_local']++;
                }
                continue;
            }

            // Same fallback as Bitrix24LeadImporter::importActivities().
            $b24UserId = (int) ($a['RESPONSIBLE_ID'] ?? $a['AUTHOR_ID'] ?? 0);
            $newUser = $this->resolveLocalUser($b24UserId, $userMap);
            if ($newUser === null) {
                continue;
            }

            $row = $byBitrixId->get($b24ActivityId);
            $oldUser = (int) $row->user_id;
            if ($oldUser === $newUser) {
                continue;
            }

            $this->logChange('lead_activities.user_id updated', [
                'activity_id' => $row->id, 'lead_id' => $lead->id, 'bitrix24_activity_id' => $b24ActivityId,
                'old' => $oldUser, 'new' => $newUser, 'dry_run' => $dryRun,
            ]);
            $this->counts['activities_updated']++;

            if (! $dryRun) {
                LeadActivity::withoutEvents(fn () => LeadActivity::whereKey($row->id)->update(['user_id' => $newUser]));
            }
        }
    }
}
