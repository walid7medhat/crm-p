<?php

namespace App\Console\Commands;

use App\Helpers\LeadHistoryHelper;
use App\Models\Lead;
use App\Models\User;
use App\Services\Bitrix24\Bitrix24Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Update responsible_person_id on local leads from the CURRENT Bitrix24 assignee.
 *
 * Fetches the live ASSIGNED_BY_ID from Bitrix24 (crm.lead.list, paged) — so it
 * reflects re-assignments done in Bitrix — and maps it to the local user via
 * users.bitrix24_id (run bitrix24:provision-users first). Matches local leads
 * by bitrix24_id. Publishes progress for the Vue dashboard to poll.
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

    protected $description = 'Update responsible_person_id on local leads from the current Bitrix24 assignee (ASSIGNED_BY_ID)';

    /** Cache keys the Vue dashboard polls / cancels with. */
    public const PROGRESS_KEY = 'bitrix24_responsible_progress';
    public const CANCEL_KEY = 'bitrix24_responsible_cancel';

    private int $total = 0;
    private ?string $startedAt = null;
    private ?string $lastError = null;

    /** @var array<int, array{at: string, type: string, message: string}> */
    private array $events = [];

    /** @var array<string, int> */
    private array $counts = ['scanned' => 0, 'updated' => 0, 'unmapped' => 0, 'no_local' => 0];

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
        $this->publish('running');

        $stop = false;
        $next = null;

        do {
            if (Cache::get(self::CANCEL_KEY)) {
                Cache::forget(self::CANCEL_KEY);
                $this->pushEvent('info', 'Cancelled by user');
                $this->publish('cancelled');
                $this->warn('Cancelled.');
                return self::SUCCESS;
            }

            try {
                $page = $client->call('crm.lead.list', [
                    'start'  => $cursor,
                    'order'  => ['ID' => 'ASC'],
                    'select' => ['ID', 'ASSIGNED_BY_ID'],
                ]);
            } catch (\Throwable $e) {
                $this->lastError = $e->getMessage();
                $this->publish('failed');
                $this->error('Failed to fetch leads from Bitrix24: '.$e->getMessage());
                return self::FAILURE;
            }

            $rows = $page['result'] ?? [];
            $next = $page['next'] ?? null;
            if ($this->total === 0) {
                $this->total = (int) ($page['total'] ?? 0);
            }

            $wantByBitrixLead = [];
            foreach ($rows as $row) {
                if ($limit > 0 && $this->counts['scanned'] >= $limit) {
                    $stop = true;
                    break;
                }
                $this->counts['scanned']++;

                $bLeadId = (int) ($row['ID'] ?? 0);
                $bUserId = (int) ($row['ASSIGNED_BY_ID'] ?? 0);
                if ($bLeadId <= 0 || $bUserId <= 0) {
                    continue;
                }
                $local = (int) ($userMap[$bUserId] ?? 0);
                if (! $local) {
                    $this->counts['unmapped']++;
                    continue;
                }
                $wantByBitrixLead[$bLeadId] = $local;
            }

            if (! empty($wantByBitrixLead)) {
                $localLeads = Lead::whereIn('bitrix24_id', array_keys($wantByBitrixLead))
                    ->get(['id', 'bitrix24_id', 'responsible_person_id', 'lead_name']);

                $found = [];
                foreach ($localLeads as $lead) {
                    $found[(int) $lead->bitrix24_id] = true;
                    $newResp = (int) $wantByBitrixLead[(int) $lead->bitrix24_id];
                    if ((int) $lead->responsible_person_id === $newResp) {
                        continue;
                    }

                    $old = (int) $lead->responsible_person_id;
                    $this->line("  #{$lead->id} \"{$lead->lead_name}\"  responsible {$old} → {$newResp}");

                    if (! $dryRun) {
                        Lead::withoutEvents(fn () => $lead->update(['responsible_person_id' => $newResp]));
                        LeadHistoryHelper::log($lead->id, [
                            'action'        => 'assigned',
                            'old_person_id' => $old,
                            'new_person_id' => $newResp,
                            'source'        => 'sync-responsible',
                        ]);
                    }
                    $this->counts['updated']++;
                    $this->pushEvent('updated', "“{$lead->lead_name}” · responsible {$old} → {$newResp}");
                }

                $this->counts['no_local'] += count(array_diff_key($wantByBitrixLead, $found));
            }

            $this->info("Scanned {$this->counts['scanned']}… updated {$this->counts['updated']}");
            $this->publish('running');
            $cursor = $next ?? $cursor;
        } while ($next !== null && ! $stop);

        $this->pushEvent('info', 'Finished');
        $this->publish('done');

        $this->newLine();
        $this->info(($dryRun ? 'Would update' : 'Updated')." {$this->counts['updated']} lead(s). "
            ."(scanned {$this->counts['scanned']}, unmapped: {$this->counts['unmapped']}, not in local DB: {$this->counts['no_local']})");

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
