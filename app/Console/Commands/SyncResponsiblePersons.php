<?php

namespace App\Console\Commands;

use App\Helpers\LeadHistoryHelper;
use App\Models\Lead;
use App\Models\User;
use App\Services\Bitrix24\Bitrix24Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Update responsible_person_id and added_by on local leads from the CURRENT
 * Bitrix24 assignee/creator.
 *
 * Fetches the live ASSIGNED_BY_ID and CREATED_BY_ID from Bitrix24 (crm.lead.list,
 * paged) — so it reflects re-assignments done in Bitrix — and maps both to the
 * local user via users.bitrix24_id (run bitrix24:provision-users first). Matches
 * local leads by bitrix24_id. Publishes progress for the Vue dashboard to poll.
 *
 * initial_responsible_person_id has no Bitrix source (Bitrix only knows the
 * *current* assignee, not who was originally assigned) — it's left untouched
 * except when it's broken (null, or points at a user id that no longer exists),
 * in which case it's backfilled with the lead's new added_by as the closest
 * available substitute.
 *
 *   php artisan bitrix24:sync-responsible --dry-run
 *   php artisan bitrix24:sync-responsible
 */
class SyncResponsiblePersons extends Command
{
    protected $signature = 'bitrix24:sync-responsible
        {--dry-run : Show what would change without writing}
        {--start=0 : Bitrix24 list cursor to start from}
        {--limit=0 : Stop after scanning N Bitrix leads (0 = all)}';

    protected $description = 'Update responsible_person_id and added_by on local leads from the current Bitrix24 assignee/creator (ASSIGNED_BY_ID / CREATED_BY_ID)';

    /** Cache keys the Vue dashboard polls / cancels with. */
    public const PROGRESS_KEY = 'bitrix24_responsible_progress';
    public const CANCEL_KEY = 'bitrix24_responsible_cancel';

    private int $total = 0;
    private ?string $startedAt = null;
    private ?string $lastError = null;

    /** @var array<int, array{at: string, type: string, message: string}> */
    private array $events = [];

    /** @var array<string, int> */
    private array $counts = [
        'scanned' => 0,
        'updated' => 0,
        'added_by_updated' => 0,
        'initial_backfilled' => 0,
        'unmapped' => 0,
        'no_local' => 0,
    ];

    /** @var array<int, int> bitrix24 user id => how many leads point to it (but it isn't in our DB) */
    private array $unmappedUsers = [];

    /** @var array<int, bool>|null Lazily-loaded set of every existing users.id, for validity checks. */
    private ?array $validUserIds = null;

    private ?ProgressBar $bar = null;

    /** Detailed per-lead changes go to storage/logs/sync-responsible.log, not the console —
     *  the console only shows the progress bar + final summary. */
    private function logChange(string $message, array $context = []): void
    {
        Log::channel('sync_responsible')->info($message, $context);
    }

    private function isValidUserId(?int $id): bool
    {
        if (! $id) {
            return false;
        }
        if ($this->validUserIds === null) {
            $this->validUserIds = User::pluck('id')->flip()->map(fn () => true)->all();
        }

        return isset($this->validUserIds[$id]);
    }

    public function handle(): int
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);

        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $cursor = (int) $this->option('start');

        $this->startedAt = now()->toIso8601String();
        Cache::forget(self::CANCEL_KEY);

        $userMap = User::whereNotNull('bitrix24_id')->pluck('id', 'bitrix24_id');
        if ($userMap->isEmpty()) {
            $this->lastError = 'No users have bitrix24_id — run bitrix24:provision-users first.';
            $this->publish('failed');
            $this->warn($this->lastError);
            return self::SUCCESS;
        }

        try {
            $client = new Bitrix24Client();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            $this->publish('failed');
            $this->error('Bitrix24 is not configured: '.$e->getMessage());
            return self::FAILURE;
        }

        $this->pushEvent('info', 'Responsible sync started'.($dryRun ? ' [dry-run]' : ''));
        $this->logChange('Sync started', ['dry_run' => $dryRun, 'start_cursor' => $cursor, 'limit' => $limit]);
        $this->publish('running');

        $stop = false;
        $next = null;

        do {
            if (Cache::get(self::CANCEL_KEY)) {
                Cache::forget(self::CANCEL_KEY);
                $this->bar?->finish();
                $this->pushEvent('info', 'Cancelled by user');
                $this->publish('cancelled');
                $this->warn('Cancelled.');
                return self::SUCCESS;
            }

            try {
                $page = $client->call('crm.lead.list', [
                    'start'  => $cursor,
                    'order'  => ['ID' => 'ASC'],
                    'select' => ['ID', 'ASSIGNED_BY_ID', 'CREATED_BY_ID'],
                ]);
            } catch (\Throwable $e) {
                $this->bar?->finish();
                $this->lastError = $e->getMessage();
                $this->publish('failed');
                $this->error('Failed to fetch leads from Bitrix24: '.$e->getMessage());
                return self::FAILURE;
            }

            $rows = $page['result'] ?? [];
            $next = $page['next'] ?? null;
            if ($this->total === 0) {
                $this->total = (int) ($page['total'] ?? 0);
                if ($this->total > 0) {
                    $this->bar = $this->output->createProgressBar($this->total);
                    $this->bar->setFormat(" %current%/%max% [%bar%] %percent:3s%%  %message%\n");
                    $this->bar->setMessage('starting…');
                    $this->bar->start();
                }
            }

            $scannedBeforePage = $this->counts['scanned'];

            $wantRespByBitrixLead = [];
            $wantAddedByBitrixLead = [];
            foreach ($rows as $row) {
                if ($limit > 0 && $this->counts['scanned'] >= $limit) {
                    $stop = true;
                    break;
                }
                $this->counts['scanned']++;

                $bLeadId = (int) ($row['ID'] ?? 0);
                if ($bLeadId <= 0) {
                    continue;
                }

                $bAssignedId = (int) ($row['ASSIGNED_BY_ID'] ?? 0);
                if ($bAssignedId > 0) {
                    $local = (int) ($userMap[$bAssignedId] ?? 0);
                    if ($local) {
                        $wantRespByBitrixLead[$bLeadId] = $local;
                    } else {
                        $this->counts['unmapped']++;
                        $this->unmappedUsers[$bAssignedId] = ($this->unmappedUsers[$bAssignedId] ?? 0) + 1;
                    }
                }

                $bCreatedId = (int) ($row['CREATED_BY_ID'] ?? 0);
                if ($bCreatedId > 0) {
                    $local = (int) ($userMap[$bCreatedId] ?? 0);
                    if ($local) {
                        $wantAddedByBitrixLead[$bLeadId] = $local;
                    } else {
                        $this->counts['unmapped']++;
                        $this->unmappedUsers[$bCreatedId] = ($this->unmappedUsers[$bCreatedId] ?? 0) + 1;
                    }
                }
            }

            $wantedBitrixLeadIds = array_unique(array_merge(
                array_keys($wantRespByBitrixLead),
                array_keys($wantAddedByBitrixLead)
            ));

            if (! empty($wantedBitrixLeadIds)) {
                $localLeads = Lead::whereIn('bitrix24_id', $wantedBitrixLeadIds)
                    ->get(['id', 'bitrix24_id', 'responsible_person_id', 'added_by', 'initial_responsible_person_id', 'lead_name']);

                $found = [];
                foreach ($localLeads as $lead) {
                    $bId = (int) $lead->bitrix24_id;
                    $found[$bId] = true;

                    $updates = [];
                    $historyEntries = [];

                    if (isset($wantRespByBitrixLead[$bId])) {
                        $newResp = (int) $wantRespByBitrixLead[$bId];
                        $oldResp = (int) $lead->responsible_person_id;
                        if ($oldResp !== $newResp) {
                            $updates['responsible_person_id'] = $newResp;
                            $historyEntries[] = ['action' => 'assigned', 'old_person_id' => $oldResp, 'new_person_id' => $newResp, 'source' => 'sync-responsible'];
                            $this->logChange('responsible_person_id updated', [
                                'lead_id' => $lead->id, 'lead_name' => $lead->lead_name,
                                'bitrix24_lead_id' => $bId, 'old' => $oldResp, 'new' => $newResp, 'dry_run' => $dryRun,
                            ]);
                            $this->counts['updated']++;
                            $this->pushEvent('updated', "“{$lead->lead_name}” · responsible {$oldResp} → {$newResp}");
                        }
                    }

                    $newAdded = isset($wantAddedByBitrixLead[$bId]) ? (int) $wantAddedByBitrixLead[$bId] : null;
                    if ($newAdded !== null) {
                        $oldAdded = (int) $lead->added_by;
                        if ($oldAdded !== $newAdded) {
                            $updates['added_by'] = $newAdded;
                            $historyEntries[] = ['action' => 'added_by_changed', 'old_added_by' => $oldAdded, 'new_added_by' => $newAdded, 'source' => 'sync-responsible'];
                            $this->logChange('added_by updated', [
                                'lead_id' => $lead->id, 'lead_name' => $lead->lead_name,
                                'bitrix24_lead_id' => $bId, 'old' => $oldAdded, 'new' => $newAdded, 'dry_run' => $dryRun,
                            ]);
                            $this->counts['added_by_updated']++;
                            $this->pushEvent('updated', "“{$lead->lead_name}” · added_by {$oldAdded} → {$newAdded}");
                        }

                        // initial_responsible_person_id has no Bitrix source — only touch it
                        // when it's broken (null or a user id that no longer exists), using
                        // the resolved added_by as the closest available substitute.
                        if (! $this->isValidUserId($lead->initial_responsible_person_id)) {
                            $updates['initial_responsible_person_id'] = $newAdded;
                            $this->logChange('initial_responsible_person_id backfilled (was broken)', [
                                'lead_id' => $lead->id, 'lead_name' => $lead->lead_name,
                                'bitrix24_lead_id' => $bId, 'old' => $lead->initial_responsible_person_id, 'new' => $newAdded, 'dry_run' => $dryRun,
                            ]);
                            $this->counts['initial_backfilled']++;
                            $this->pushEvent('updated', "“{$lead->lead_name}” · initial_responsible_person_id backfilled → {$newAdded}");
                        }
                    }

                    if (empty($updates)) {
                        continue;
                    }

                    if (! $dryRun) {
                        Lead::withoutEvents(fn () => $lead->update($updates));
                        foreach ($historyEntries as $entry) {
                            LeadHistoryHelper::log($lead->id, $entry);
                        }
                    }
                }

                $this->counts['no_local'] += count(array_diff($wantedBitrixLeadIds, array_keys($found)));
            }

            if ($this->bar) {
                $this->bar->advance($this->counts['scanned'] - $scannedBeforePage);
                $this->bar->setMessage("updated: {$this->counts['updated']} responsible, {$this->counts['added_by_updated']} added_by, {$this->counts['initial_backfilled']} initial backfilled");
            }
            $this->publish('running');
            $cursor = $next ?? $cursor;
        } while ($next !== null && ! $stop);

        if ($this->bar) {
            $this->bar->finish();
        }

        $this->pushEvent('info', 'Finished');
        $this->publish('done');
        $this->logChange('Sync finished', array_merge($this->counts, ['dry_run' => $dryRun]));

        $this->newLine();
        $verb = $dryRun ? 'Would update' : 'Updated';
        $this->info("{$verb} responsible_person_id on {$this->counts['updated']} lead(s), "
            ."added_by on {$this->counts['added_by_updated']} lead(s), "
            ."backfilled initial_responsible_person_id on {$this->counts['initial_backfilled']} lead(s). "
            ."(scanned {$this->counts['scanned']}, unmapped: {$this->counts['unmapped']}, not in local DB: {$this->counts['no_local']})");
        $this->line('Detailed per-lead changes: storage/logs/sync-responsible.log');

        if (! empty($this->unmappedUsers)) {
            arsort($this->unmappedUsers);
            $distinct = count($this->unmappedUsers);
            $this->newLine();
            $this->warn("{$distinct} distinct Bitrix24 user(s) are not in your DB (provision them with `bitrix24:provision-users`):");
            foreach ($this->unmappedUsers as $b24UserId => $leadCount) {
                $this->line("  Bitrix24 user #{$b24UserId} → {$leadCount} lead(s)");
            }
        }

        return self::SUCCESS;
    }

    private function pushEvent(string $type, string $message): void
    {
        $this->events[] = ['at' => now()->toIso8601String(), 'type' => $type, 'message' => $message];
        if (count($this->events) > 40) {
            array_shift($this->events);
        }
    }

    private function publish(string $status): void
    {
        $progress = $this->total > 0
            ? (int) min(100, floor(($this->counts['scanned'] / $this->total) * 100))
            : ($status === 'done' ? 100 : 0);

        Cache::put(self::PROGRESS_KEY, array_merge($this->counts, [
            'status'      => $status,
            'total'       => $this->total,
            'progress'    => $progress,
            'last_error'  => $this->lastError,
            'started_at'  => $this->startedAt,
            'updated_at'  => now()->toIso8601String(),
            'finished_at' => in_array($status, ['done', 'failed', 'cancelled'], true) ? now()->toIso8601String() : null,
            'events'      => array_reverse($this->events),
        ]), now()->addHours(6));
    }
}
