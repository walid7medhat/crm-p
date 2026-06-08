<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\User;
use App\Services\Bitrix24\Bitrix24Client;
use App\Services\Bitrix24\Bitrix24LeadImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Sync Bitrix24 leads into the CRM, with live progress + counts.
 *
 * Per lead (via Bitrix24LeadImporter::importOne):
 *   - exists  → updates stage (Bitrix "Future Prospected" maps to local
 *               "qualified"), refreshes source, the activity-person mirror
 *               (bitrix24_last_activity_by_id) and re-imports comments /
 *               activities / timeline. Reports WHICH fields changed.
 *   - missing → creates a new lead.
 *
 * Progress (counts + a rolling event feed) is published to the cache under
 * PROGRESS_KEY every lead, so the Vue dashboard can poll and show what happens
 * live — whether the command is started from the UI or run on the server.
 */
class SyncBitrix24LeadsProgress extends Command
{
    protected $signature = 'bitrix24:sync-leads
        {--skip-existing : Only insert new leads; do not re-sync existing ones}
        {--limit=0 : Stop after processing N leads (0 = all)}
        {--start=0 : Bitrix24 list cursor to start from}
        {--fallback-user=1 : Local user id used when a Bitrix24 user has no local match}';

    protected $description = 'Sync Bitrix24 leads (create new, update stage/source/activity for existing) with live progress and counts';

    /** Cache key the Vue dashboard polls. */
    public const PROGRESS_KEY = 'bitrix24_sync_leads_progress';

    /** Cache flag the UI sets to request cancellation. */
    public const CANCEL_KEY = 'bitrix24_sync_leads_cancel';

    private int $total = 0;
    private ?string $startedAt = null;
    private ?string $lastError = null;

    /** @var array<int, array{at: string, type: string, message: string}> rolling feed (oldest→newest). */
    private array $events = [];

    /** @var array<string, int> */
    private array $counts = [
        'processed' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0,
        'stage_changed' => 0, 'source_changed' => 0, 'activity_changed' => 0, 'errors' => 0,
    ];

    public function handle(): int
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);

        $skipExisting = (bool) $this->option('skip-existing');
        $limit = (int) $this->option('limit');
        $cursor = (int) $this->option('start');
        $fallbackUserId = (int) $this->option('fallback-user') ?: 1;

        $this->startedAt = now()->toIso8601String();
        Cache::forget(self::CANCEL_KEY);

        try {
            $client = new Bitrix24Client();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            $this->publish('failed');
            $this->error('Bitrix24 is not configured: '.$e->getMessage());
            return self::FAILURE;
        }

        $importer = new Bitrix24LeadImporter($client, $fallbackUserId);

        $this->logBoth('info', 'START sync-leads', [
            'skip_existing' => $skipExisting, 'limit' => $limit ?: 'all', 'start_cursor' => $cursor,
        ]);
        $this->pushEvent('info', 'Sync started'.($skipExisting ? ' (skip existing)' : '').($limit ? ", limit {$limit}" : ''));
        $this->publish('running');

        $next = null;
        $stop = false;

        do {
            try {
                $page = $client->listLeads($cursor);
            } catch (\Throwable $e) {
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

                if (Cache::get(self::CANCEL_KEY)) {
                    Cache::forget(self::CANCEL_KEY);
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
                $pos = $this->counts['processed'];

                $before = Lead::where('bitrix24_id', $b24Id)
                    ->first(['id', 'bitrix24_id', 'stage_id', 'lead_source', 'bitrix24_last_activity_by_id']);

                if ($skipExisting && $before) {
                    $this->counts['skipped']++;
                    $this->line("[{$pos}] b24#{$b24Id} ⏭ skip (exists)");
                    $this->publish('running');
                    continue;
                }

                try {
                    $result = $importer->importOne($b24);
                    $lead = $result['lead'];
                    $who = $this->ownerName($lead);

                    if ($result['created']) {
                        $this->counts['created']++;
                        $msg = "Created “{$lead->lead_name}” · stage {$lead->status_lead} · owner {$who}";
                        $this->line("[{$pos}] b24#{$b24Id} ➕ created #{$lead->id} \"{$lead->lead_name}\" stage={$lead->status_lead} · owner: {$who}");
                        $this->pushEvent('created', $msg);
                        $this->logBoth('info', 'created', ['b24' => $b24Id, 'lead' => $lead->id, 'stage' => $lead->status_lead, 'owner' => $who]);
                    } else {
                        $this->counts['updated']++;
                        $changed = $this->detectChanges($before, $lead);
                        $tag = $changed ? implode(', ', $changed) : 'no field change';
                        $msg = "Updated “{$lead->lead_name}” · {$tag} · owner {$who}";
                        $this->line("[{$pos}] b24#{$b24Id} ♻ updated #{$lead->id} \"{$lead->lead_name}\" [{$tag}] · owner: {$who}");
                        $this->pushEvent('updated', $msg);
                        $this->logBoth('info', 'updated', ['b24' => $b24Id, 'lead' => $lead->id, 'changed' => $changed, 'owner' => $who]);
                    }
                } catch (\Throwable $e) {
                    $this->counts['errors']++;
                    $this->line("[{$pos}] b24#{$b24Id} ❌ {$e->getMessage()}");
                    $this->pushEvent('error', "b24#{$b24Id}: {$e->getMessage()}");
                    $this->logBoth('error', 'failed', ['b24' => $b24Id, 'error' => $e->getMessage()]);
                }

                $this->publish('running');
            }

            $cursor = $next ?? $cursor;
        } while ($next !== null && ! $stop);

        $this->pushEvent('info', 'Sync finished');
        $this->publish('done');

        $this->logBoth('info', 'FINISHED sync-leads', $this->counts);
        $this->newLine();
        $this->info(sprintf(
            'Done. processed=%d created=%d updated=%d skipped=%d errors=%d',
            $this->counts['processed'], $this->counts['created'], $this->counts['updated'],
            $this->counts['skipped'], $this->counts['errors']
        ));
        $this->info(sprintf(
            'Changes on updates → stage=%d, source=%d, activity-person=%d',
            $this->counts['stage_changed'], $this->counts['source_changed'], $this->counts['activity_changed']
        ));

        return self::SUCCESS;
    }

    /**
     * Compare the lead's pre/post state and tally which fields changed.
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
            $changed[] = 'stage';
            $this->counts['stage_changed']++;
        }
        if ((string) $before->lead_source !== (string) $lead->lead_source) {
            $changed[] = 'source';
            $this->counts['source_changed']++;
        }
        if ((int) $before->bitrix24_last_activity_by_id !== (int) $lead->bitrix24_last_activity_by_id) {
            $changed[] = 'activity-person';
            $this->counts['activity_changed']++;
        }

        return $changed;
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

    private function pushEvent(string $type, string $message): void
    {
        $this->events[] = ['at' => now()->toIso8601String(), 'type' => $type, 'message' => $message];
        if (count($this->events) > 40) {
            array_shift($this->events);
        }
    }

    /** Publish the current snapshot for the Vue dashboard to poll. */
    private function publish(string $status): void
    {
        $processed = $this->counts['processed'];
        $progress = $this->total > 0
            ? (int) min(100, floor(($processed / $this->total) * 100))
            : ($status === 'done' ? 100 : 0);

        Cache::put(self::PROGRESS_KEY, array_merge($this->counts, [
            'status' => $status,
            'total' => $this->total,
            'progress' => $progress,
            'last_error' => $this->lastError,
            'started_at' => $this->startedAt,
            'updated_at' => now()->toIso8601String(),
            'finished_at' => in_array($status, ['done', 'failed', 'cancelled'], true) ? now()->toIso8601String() : null,
            'events' => array_reverse($this->events), // newest first for the UI
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
