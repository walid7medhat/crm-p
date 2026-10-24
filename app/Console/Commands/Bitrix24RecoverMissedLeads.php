<?php

namespace App\Console\Commands;

use App\Events\LeadUpdated;
use App\Models\Lead;
use App\Models\User;
use App\Services\Bitrix24\Bitrix24Client;
use App\Services\Bitrix24\Bitrix24LeadImporter;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Bitrix24RecoverMissedLeads extends Command
{
    protected $signature = 'bitrix24:recover-missed
        {--since= : Start date, e.g. "2026-10-19 00:00:00"}
        {--days=7 : How many days back if --since is not given}
        {--include-abu-dhabi : Do not skip Abu Dhabi leads}
        {--no-broadcast : Do not fire LeadUpdated events to the Kanban board}
        {--dry-run : Only log what would be synced, change nothing}
        {--sleep=250 : Milliseconds to wait between leads}
        {--chunk=50 : Leads fetched per batch}
        {--fresh : Ignore the saved checkpoint and start from the beginning}';

    protected $description = 'Recover Bitrix24 leads created/changed while the webhook was down (resumable)';

    private const LOG = 'bitrix_recovery';

    public function handle(): int
    {
        @ini_set('memory_limit', '512M');
        DB::disableQueryLog();

        $since = $this->option('since')
            ? Carbon::parse($this->option('since'))
            : now()->subDays((int) $this->option('days'));

        $dry       = (bool) $this->option('dry-run');
        $withAD    = (bool) $this->option('include-abu-dhabi');
        $broadcast = ! $this->option('no-broadcast');
        $sleepUs   = max(0, (int) $this->option('sleep')) * 1000;
        $chunkSize = max(1, (int) $this->option('chunk'));

        $log      = Log::channel(self::LOG);
        $client   = new Bitrix24Client();
        $importer = new Bitrix24LeadImporter($client, (int) config('bitrix24.fallback_user_id', 1));

        // ---- checkpoint / resume ----
        $sinceKey = $since->toDateTimeString();
        $stats    = ['created' => 0, 'updated' => 0, 'skipped_ad' => 0, 'errors' => 0];
        $lastId   = 0;

        if (! $this->option('fresh') && ! $dry) {
            $cp = $this->loadCheckpoint();
            if ($cp && ($cp['since'] ?? null) === $sinceKey) {
                $lastId = (int) ($cp['last_id'] ?? 0);
                $stats  = array_merge($stats, $cp['stats'] ?? []);
                $this->warn("Resuming after Bitrix lead ID {$lastId} (use --fresh to restart).");
                $log->info('[RECOVERY] resuming', ['last_id' => $lastId, 'stats' => $stats]);
            }
        }

        $this->info("Collecting lead IDs created or changed since {$sinceKey} ...");
        $log->info('[RECOVERY] started', ['since' => $since->toIso8601String(), 'dry' => $dry, 'resume_from' => $lastId]);

        // ---- collect IDs only (small memory) ----
        $idSet = [];
        foreach (['>=DATE_CREATE', '>=DATE_MODIFY', '>=LAST_ACTIVITY_TIME'] as $filterKey) {
            foreach ($this->fetchLeads($client, [$filterKey => $since->toIso8601String()], ['ID']) as $row) {
                $idSet[(int) $row['ID']] = true;
            }
        }
        $ids = array_keys($idSet);
        unset($idSet);
        sort($ids);

        $allCount = count($ids);
        $ids = array_values(array_filter($ids, fn ($i) => $i > $lastId));
        $total = count($ids);

        $this->info("Found {$allCount} leads in Bitrix24, {$total} left to process.");
        $log->info("[RECOVERY] found {$allCount} leads, {$total} to process");

        $bar = $this->output->createProgressBar($total);
        $processed = 0;

        // ---- process in small chunks ----
        foreach (array_chunk($ids, $chunkSize) as $chunkIds) {
            $rows = $this->fetchLeads($client, ['ID' => $chunkIds]);

            // keep ascending order so the checkpoint is reliable
            usort($rows, fn ($a, $b) => (int) $a['ID'] <=> (int) $b['ID']);

            foreach ($rows as $b24) {
                $b24Id = (int) $b24['ID'];
                $bar->advance();
                $processed++;
                $existing = null;

                try {
                    if (! $withAD) {
                        $existing = Lead::with('responsiblePerson')->where('bitrix24_id', $b24Id)->first();
                        $isAD = $existing
                            ? $this->isAbuDhabiLead($existing)
                            : $this->isAbuDhabiBitrixLead($importer, $b24);

                        if ($isAD) {
                            $stats['skipped_ad']++;
                            $log->info('[RECOVERY] skipped (Abu Dhabi)', [
                                'bitrix24_id'        => $b24Id,
                                'exists_locally'     => (bool) $existing,
                                'branch'             => $existing?->branch,
                                'lead_branch_source' => $existing?->lead_branch_source,
                                'admin_parent_id'    => $existing?->responsiblePerson?->admin_parent?->id,
                                'assigned_by_id'     => $b24['ASSIGNED_BY_ID'] ?? null,
                            ]);
                            $this->saveCheckpoint($sinceKey, $b24Id, $stats, $dry);
                            continue;
                        }
                    }

                    if ($dry) {
                        $log->info('[RECOVERY][DRY] would sync', [
                            'bitrix24_id' => $b24Id,
                            'exists'      => Lead::where('bitrix24_id', $b24Id)->exists(),
                            'status'      => $b24['STATUS_ID'] ?? null,
                            'created'     => $b24['DATE_CREATE'] ?? null,
                            'modified'    => $b24['DATE_MODIFY'] ?? null,
                        ]);
                        continue;
                    }

                    $result = $importer->importOneFast($b24);
                    $lead   = $result['lead'] ?? null;
                    $result['created'] ? $stats['created']++ : $stats['updated']++;

                    $log->info('[RECOVERY] synced', [
                        'bitrix24_id' => $b24Id,
                        'lead_id'     => $lead?->id,
                        'created'     => $result['created'],
                        'status'      => $b24['STATUS_ID'] ?? null,
                    ]);

                    if ($lead && $broadcast) {
                        event(new LeadUpdated($lead, $result['created'] ? 'created' : 'updated', null, null, 'bitrix'));
                    }
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    $log->error('[RECOVERY] failed', ['bitrix24_id' => $b24Id, 'error' => $e->getMessage()]);
                }

                // save progress after every lead (even failed ones, they are logged)
                $this->saveCheckpoint($sinceKey, $b24Id, $stats, $dry);

                unset($result, $lead, $existing);

                if ($sleepUs) {
                    usleep($sleepUs);
                }
            }

            unset($rows);
            DB::flushQueryLog();
            gc_collect_cycles();
        }

        $bar->finish();
        $this->newLine(2);
        $this->table(array_keys($stats), [array_values($stats)]);
        $log->info('[RECOVERY] finished', $stats + ['peak_mb' => round(memory_get_peak_usage(true) / 1048576, 1)]);

        if (! $dry) {
            $this->clearCheckpoint();
        }

        return self::SUCCESS;
    }

    /** Paginate crm.lead.list (50 per page) using the `next` cursor. */
    private function fetchLeads(Bitrix24Client $client, array $filter, array $select = ['*', 'UF_*', 'PHONE', 'EMAIL', 'WEB', 'IM']): array
    {
        $out = [];
        $start = 0;

        do {
            $r = $client->call('crm.lead.list', [
                'filter' => $filter,
                'order'  => ['ID' => 'ASC'],
                'select' => $select,
                'start'  => $start,
            ]);

            foreach ($r['result'] ?? [] as $row) {
                $out[] = $row;
            }

            $start = $r['next'] ?? null;
            usleep(200_000);
        } while ($start !== null);

        return $out;
    }

    // ---------- checkpoint helpers ----------

    private function checkpointPath(): string
    {
        return storage_path('app/bitrix_recovery_checkpoint.json');
    }

    private function loadCheckpoint(): ?array
    {
        $path = $this->checkpointPath();
        if (! is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    private function saveCheckpoint(string $since, int $lastId, array $stats, bool $dry): void
    {
        if ($dry) {
            return;
        }
        file_put_contents(
            $this->checkpointPath(),
            json_encode(['since' => $since, 'last_id' => $lastId, 'stats' => $stats, 'at' => now()->toDateTimeString()])
        );
    }

    private function clearCheckpoint(): void
    {
        @unlink($this->checkpointPath());
    }

    // ---------- branch checks (same as the webhook) ----------

    private function isAbuDhabiLead(Lead $lead): bool
    {
        $isAD = fn ($n) => strtolower(trim((string) $n)) === 'abu dhabi';

        if (trim((string) $lead->branch) !== '') {
            return $isAD($lead->branch);
        }
        if ($isAD($lead->lead_branch_source)) {
            return true;
        }
        return (int) ($lead->responsiblePerson?->admin_parent?->id ?? 0) === 25;
    }

    private function isAbuDhabiBitrixLead(Bitrix24LeadImporter $importer, array $b24): bool
    {
        $localUserId = $importer->mapBitrixUser($b24['ASSIGNED_BY_ID'] ?? null);
        if (! $localUserId) {
            return false;
        }
        $branch = User::find($localUserId)?->getBranchNode();

        return $branch && ((int) $branch->id === 25 || strtolower(trim((string) $branch->name)) === 'abu dhabi');
    }
}