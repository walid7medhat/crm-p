<?php

namespace App\Console\Commands;

use App\Events\LeadUpdated;
use App\Models\Lead;
use App\Models\User;
use App\Services\Bitrix24\Bitrix24Client;
use App\Services\Bitrix24\Bitrix24LeadImporter;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class Bitrix24RecoverMissedLeads extends Command
{
    protected $signature = 'bitrix24:recover-missed
        {--since= : Start date, e.g. "2026-10-18 00:00:00"}
        {--days=7 : How many days back to look if --since is not given}
        {--include-abu-dhabi : Do not skip Abu Dhabi leads}
        {--no-broadcast : Do not fire LeadUpdated events to the Kanban board}
        {--dry-run : Only list what would be synced, change nothing}
        {--sleep=250 : Milliseconds to wait between leads}';

    protected $description = 'Recover Bitrix24 leads created/changed while the webhook was down (Dubai leads)';

    private const LOG = 'bitrix_recovery';

    public function handle(): int
    {
        $since = $this->option('since')
            ? Carbon::parse($this->option('since'))
            : now()->subDays((int) $this->option('days'));

        $dry       = (bool) $this->option('dry-run');
        $withAD    = (bool) $this->option('include-abu-dhabi');
        $broadcast = ! $this->option('no-broadcast');
        $sleepUs   = max(0, (int) $this->option('sleep')) * 1000;

        $client   = new Bitrix24Client();
        $importer = new Bitrix24LeadImporter($client, (int) config('bitrix24.fallback_user_id', 1));

        $this->info("Fetching Bitrix24 leads created or changed since {$since->toDateTimeString()} ...");
        $log = Log::channel(self::LOG);
        $log->info('[RECOVERY] started', ['since' => $since->toIso8601String(), 'dry' => $dry]);

        // Query by DATE_CREATE (new leads), DATE_MODIFY (edits) and LAST_ACTIVITY_TIME
        // (comments/activities may not touch DATE_MODIFY). Merge and dedupe by lead ID.
        $leads = [];
        foreach (['>=DATE_CREATE', '>=DATE_MODIFY', '>=LAST_ACTIVITY_TIME'] as $filterKey) {
            foreach ($this->fetchLeads($client, [$filterKey => $since->toIso8601String()]) as $l) {
                $leads[(int) $l['ID']] = $l;
            }
        }

        $total = count($leads);
        $this->info("Found {$total} leads in Bitrix24.");
        $log->info("[RECOVERY] found {$total} leads");

        $stats = ['created' => 0, 'updated' => 0, 'skipped_ad' => 0, 'errors' => 0];
        $bar = $this->output->createProgressBar($total);

        foreach ($leads as $b24Id => $b24) {
            $bar->advance();

            try {
                // Same rule as the webhook: Abu Dhabi leads are managed in the CRM only.
                if (! $withAD) {
                    $existing = Lead::with('responsiblePerson')->where('bitrix24_id', $b24Id)->first();
                    $isAD = $existing
                        ? $this->isAbuDhabiLead($existing)
                        : $this->isAbuDhabiBitrixLead($importer, $b24);

                    if ($isAD) {
                        $stats['skipped_ad']++;
                        $log->info('[RECOVERY] skipped (Abu Dhabi)', ['bitrix24_id' => $b24Id]);
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

            if ($sleepUs) {
                usleep($sleepUs);
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->table(array_keys($stats), [array_values($stats)]);
        $log->info('[RECOVERY] finished', $stats);

        return self::SUCCESS;
    }

    /** Paginate crm.lead.list (50 per page) using the `next` cursor. */
    private function fetchLeads(Bitrix24Client $client, array $filter): array
    {
        $out = [];
        $start = 0;

        do {
            $r = $client->call('crm.lead.list', [
                'filter' => $filter,
                'order'  => ['ID' => 'ASC'],
                'select' => ['*', 'UF_*', 'PHONE', 'EMAIL', 'WEB', 'IM'],
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