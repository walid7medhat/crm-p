<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\Stage;
use App\Models\User;
use App\Services\Bitrix24\Bitrix24Client;
use App\Services\Bitrix24\Bitrix24LeadImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * FAST Bitrix24 lead sync — creates new leads and refreshes existing ones
 * (stage, source, activity-person, updated_at) WITHOUT pulling comments,
 * activities or the timeline. No per-lead timeline API calls, so it's far
 * faster than `bitrix24:sync-leads`; use it for routine top-ups / big passes.
 *
 * Publishes progress to the SAME cache key as `bitrix24:sync-leads`, so the
 * /sync-bitrix-leads dashboard shows it live too.
 */
class SyncBitrix24LeadsFast extends Command
{
    protected $signature = 'bitrix24:sync-leads-fast
        {--skip-existing : Only insert new leads; do not re-sync existing ones}
        {--limit=0 : Stop after processing N leads (0 = all)}
        {--start=0 : Bitrix24 list cursor to start from (overrides saved resume cursor)}
        {--restart : Ignore the saved cursor and start from the beginning}
        {--light : Fastest: stage/source/activity-person only, skip comments + activities (no per-lead API calls)}
        {--no-comments : Skip importing new comments}
        {--no-activities : Skip importing new activities}
        {--fallback-user=1 : Local user id used when a Bitrix24 user has no local match}';

    protected $description = 'FAST Bitrix24 lead sync (stage/source/activity only, no comments/timeline) with live progress';

    private int $total = 0;
    /** Bitrix24 offset we resumed from = leads already walked in previous runs. */
    private int $startCursor = 0;
    private ?string $startedAt = null;
    private ?string $lastError = null;

    /** @var array<int, string> stage id => name */
    private array $stageNames = [];

    /** @var array<int, string> user id => name */
    private array $userNameCache = [];

    /** @var array<int, array{at: string, type: string, message: string, b24: int|null}> */
    private array $events = [];

    /** @var array<string, int> */
    private array $counts = [
        'processed' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0,
        'stage_changed' => 0, 'status_changed' => 0, 'owner_changed' => 0,
        'source_changed' => 0, 'activity_changed' => 0, 'errors' => 0,
    ];

    public function handle(): int
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);

        $skipExisting = (bool) $this->option('skip-existing');
        $limit = (int) $this->option('limit');
        $startOpt = (int) $this->option('start');
        $restart = (bool) $this->option('restart');
        $light = (bool) $this->option('light');
        $withComments = ! $light && ! $this->option('no-comments');
        $withActivities = ! $light && ! $this->option('no-activities');
        $fallbackUserId = (int) $this->option('fallback-user') ?: 1;

        // Skip only if another run is actively going (fresh heartbeat). Self-heals
        // after a crash — shared guard with bitrix24:sync-leads.
        if (SyncBitrix24LeadsProgress::anotherRunActive()) {
            $this->warn('Another lead sync is already running — skipping this run.');
            return self::SUCCESS;
        }

        // Resume from the saved cursor by default (shared with bitrix24:sync-leads).
        if ($startOpt > 0) {
            $cursor = $startOpt;
        } elseif ($restart) {
            $cursor = 0;
            SyncBitrix24LeadsProgress::clearSavedCursor();
        } else {
            $cursor = SyncBitrix24LeadsProgress::loadSavedCursor();
        }

        // Resume continues the displayed count: startCursor + this run's processed.
        $this->startCursor = $cursor;

        $this->startedAt = now()->toIso8601String();
        Cache::forget(SyncBitrix24LeadsProgress::CANCEL_KEY);

        try {
            $client = new Bitrix24Client();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            $this->publish('failed');
            $this->error('Bitrix24 is not configured: '.$e->getMessage());
            return self::FAILURE;
        }

        $importer = new Bitrix24LeadImporter($client, $fallbackUserId);
        $this->stageNames = Stage::pluck('name', 'id')->toArray();

        $this->logBoth('info', 'START sync-leads-fast', [
            'skip_existing' => $skipExisting, 'limit' => $limit ?: 'all', 'start_cursor' => $cursor,
        ]);
        $mode = $light ? ' [light: stage/source/owner only]' : (! $withComments || ! $withActivities ? ' [partial: '.($withComments ? 'comments' : '').($withActivities ? ' activities' : '').']' : '');
        $this->pushEvent('info', ($cursor > 0 ? "Resuming from cursor {$cursor}" : 'Fast sync started').$mode.($skipExisting ? ' (skip existing)' : '').($limit ? ", limit {$limit}" : ''));
        $this->publish('running');

        $next = null;
        $stop = false;

        do {
            $pageCursor = $cursor;
            try {
                $page = $client->listLeads($cursor);
            } catch (\Throwable $e) {
                // Save where we were so the next run resumes from this page.
                SyncBitrix24LeadsProgress::saveCursor($pageCursor);
                $this->lastError = $e->getMessage();
                $this->publish('failed');
                $this->logBoth('error', 'Failed to list leads', ['cursor' => $cursor, 'error' => $e->getMessage()]);
                $this->error('Failed to fetch leads from Bitrix24: '.$e->getMessage());
                return self::FAILURE;
            }

            $leads = $page['result'] ?? [];
            $next = $page['next'] ?? null;

            if ($this->total === 0) {
                $this->total = (int) ($page['total'] ?? 0);
                $this->info("Bitrix24 reports {$this->total} lead(s) total.".($limit > 0 ? " Processing up to {$limit}." : ''));
            }

            foreach ($leads as $b24) {
                if ($limit > 0 && $this->counts['processed'] >= $limit) {
                    $stop = true;
                    break;
                }

                if (Cache::get(SyncBitrix24LeadsProgress::CANCEL_KEY)) {
                    Cache::forget(SyncBitrix24LeadsProgress::CANCEL_KEY);
                    SyncBitrix24LeadsProgress::saveCursor($pageCursor);
                    $this->pushEvent('info', 'Cancelled by user');
                    $this->publish('cancelled');
                    $this->warn('Sync cancelled.');
                    return self::SUCCESS;
                }

                $b24Id = (int) ($b24['ID'] ?? 0);
                if ($b24Id <= 0) {
                    continue;
                }

                $this->counts['processed']++;
                $pos = $this->startCursor + $this->counts['processed'];

                $before = Lead::where('bitrix24_id', $b24Id)
                    ->first(['id', 'bitrix24_id', 'stage_id', 'status_lead', 'lead_source', 'responsible_person_id', 'bitrix24_last_activity_by_id']);

                if ($skipExisting && $before) {
                    $this->counts['skipped']++;
                    $this->line("[{$pos}] b24#{$b24Id} ⏭ skip (exists)");
                    $this->publish('running');
                    continue;
                }

                try {
                    $result = $importer->importOneFast($b24, $withComments, $withActivities);
                    $lead = $result['lead'];
                    $who = $this->ownerName($lead);

                    if ($result['created']) {
                        $this->counts['created']++;
                        $stageName = $this->stageName($lead->stage_id);
                        $this->line("[{$pos}] b24#{$b24Id} ➕ created #{$lead->id} \"{$lead->lead_name}\" stage={$stageName} status={$lead->status_lead} · owner: {$who}");
                        $this->pushEvent('created', "Created “{$lead->lead_name}” · stage {$stageName} · status {$lead->status_lead} · owner {$who}", $b24Id);
                    } else {
                        $this->counts['updated']++;
                        $changed = $this->detectChanges($before, $lead);
                        $tag = $changed ? implode(' · ', $changed) : 'no field change (comments/activity refreshed)';
                        $this->line("[{$pos}] b24#{$b24Id} ♻ updated #{$lead->id} \"{$lead->lead_name}\" — {$tag}");
                        $this->pushEvent('updated', "Updated “{$lead->lead_name}” · {$tag}", $b24Id);
                    }
                } catch (\Throwable $e) {
                    $this->counts['errors']++;
                    $this->line("[{$pos}] b24#{$b24Id} ❌ {$e->getMessage()}");
                    $this->pushEvent('error', "b24#{$b24Id}: {$e->getMessage()}", $b24Id);
                    $this->logBoth('error', 'failed', ['b24' => $b24Id, 'error' => $e->getMessage()]);
                }

                $this->publish('running');
            }

            // Persist resume point in DB (shared cursor with bitrix24:sync-leads).
            if ($stop) {
                SyncBitrix24LeadsProgress::saveCursor($pageCursor);
            } elseif ($next === null) {
                SyncBitrix24LeadsProgress::clearSavedCursor();
            } else {
                SyncBitrix24LeadsProgress::saveCursor((int) $next);
            }

            $cursor = $next ?? $cursor;
        } while ($next !== null && ! $stop);

        $this->pushEvent('info', 'Fast sync finished');
        $this->publish('done');

        $this->logBoth('info', 'FINISHED sync-leads-fast', $this->counts);
        $this->newLine();
        $this->info(sprintf(
            'Done. processed=%d created=%d updated=%d skipped=%d errors=%d (stage=%d, status=%d, owner=%d, source=%d, activity=%d)',
            $this->counts['processed'], $this->counts['created'], $this->counts['updated'], $this->counts['skipped'],
            $this->counts['errors'], $this->counts['stage_changed'], $this->counts['status_changed'],
            $this->counts['owner_changed'], $this->counts['source_changed'], $this->counts['activity_changed']
        ));

        return self::SUCCESS;
    }

    /**
     * Compare the lead's pre/post state and return human "from → to" change lines.
     *
     * @return array<int, string>
     */
    private function detectChanges(?Lead $before, Lead $lead): array
    {
        if (! $before) {
            return [];
        }

        $changed = [];

        if ((int) $before->stage_id !== (int) $lead->stage_id) {
            $this->counts['stage_changed']++;
            $changed[] = 'stage: '.$this->stageName($before->stage_id).' → '.$this->stageName($lead->stage_id);
        }
        if ((string) $before->status_lead !== (string) $lead->status_lead) {
            $this->counts['status_changed']++;
            $changed[] = 'status: '.($before->status_lead ?: '—').' → '.($lead->status_lead ?: '—');
        }
        if ((int) $before->responsible_person_id !== (int) $lead->responsible_person_id) {
            $this->counts['owner_changed']++;
            $changed[] = 'owner: '.$this->userNameById($before->responsible_person_id).' → '.$this->userNameById($lead->responsible_person_id);
        }
        if ((string) $before->lead_source !== (string) $lead->lead_source) {
            $this->counts['source_changed']++;
            $changed[] = 'source: '.($before->lead_source ?: '—').' → '.($lead->lead_source ?: '—');
        }
        if ((int) $before->bitrix24_last_activity_by_id !== (int) $lead->bitrix24_last_activity_by_id) {
            $this->counts['activity_changed']++;
            $changed[] = 'activity-person: '.$this->userNameByBitrixId($before->bitrix24_last_activity_by_id).' → '.$this->userNameByBitrixId($lead->bitrix24_last_activity_by_id);
        }

        return $changed;
    }

    private function stageName($id): string
    {
        $id = (int) $id;
        return $id > 0 ? ($this->stageNames[$id] ?? "#{$id}") : '—';
    }

    private function userNameById($id): string
    {
        $id = (int) $id;
        if ($id <= 0) {
            return '—';
        }
        if (! array_key_exists($id, $this->userNameCache)) {
            $this->userNameCache[$id] = User::where('id', $id)->value('name') ?? "#{$id}";
        }
        return $this->userNameCache[$id];
    }

    private function userNameByBitrixId($b24Id): string
    {
        $b24Id = (int) $b24Id;
        if ($b24Id <= 0) {
            return '—';
        }
        return User::where('bitrix24_id', $b24Id)->value('name') ?? "b24#{$b24Id}";
    }

    private function ownerName(Lead $lead): string
    {
        $lead->loadMissing('responsiblePerson:id,name');
        $name = $lead->responsiblePerson?->name;
        if ($name) {
            return $name;
        }

        if ($lead->bitrix24_last_activity_by_id) {
            $byActivity = User::where('bitrix24_id', (int) $lead->bitrix24_last_activity_by_id)->value('name');
            if ($byActivity) {
                return $byActivity;
            }
        }

        return 'Unknown';
    }

    private function pushEvent(string $type, string $message, ?int $b24Id = null): void
    {
        $this->events[] = ['at' => now()->toIso8601String(), 'type' => $type, 'message' => $message, 'b24' => $b24Id];
        if (count($this->events) > 40) {
            array_shift($this->events);
        }
    }

    private function publish(string $status): void
    {
        // Absolute position = where we resumed from + what this run has done.
        $processed = $this->startCursor + $this->counts['processed'];
        $progress = $this->total > 0
            ? (int) min(100, floor(($processed / $this->total) * 100))
            : ($status === 'done' ? 100 : 0);

        Cache::put(SyncBitrix24LeadsProgress::PROGRESS_KEY, array_merge($this->counts, [
            'status' => $status,
            'mode' => 'fast',
            'total' => $this->total,
            'progress' => $progress,
            'processed' => $processed,
            'last_error' => $this->lastError,
            'started_at' => $this->startedAt,
            'updated_at' => now()->toIso8601String(),
            'finished_at' => in_array($status, ['done', 'failed', 'cancelled'], true) ? now()->toIso8601String() : null,
            'events' => array_reverse($this->events),
        ]), now()->addHours(6));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logBoth(string $level, string $message, array $context = []): void
    {
        Log::channel('bitrix_leads')->{$level}($message, $context);
    }
}
