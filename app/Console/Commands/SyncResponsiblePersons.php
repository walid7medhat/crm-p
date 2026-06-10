<?php

namespace App\Console\Commands;

use App\Helpers\LeadHistoryHelper;
use App\Models\Lead;
use App\Models\User;
use App\Services\Bitrix24\Bitrix24Client;
use Illuminate\Console\Command;

/**
 * Update responsible_person_id on local leads from the CURRENT Bitrix24 assignee.
 *
 * Fetches the live ASSIGNED_BY_ID from Bitrix24 (crm.lead.list, paged) — so it
 * reflects re-assignments done in Bitrix — and maps it to the local user via
 * users.bitrix24_id (run bitrix24:provision-users first). Matches local leads
 * by bitrix24_id.
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

    public function handle(): int
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);

        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $cursor = (int) $this->option('start');

        // bitrix24 user id => local user id (provisioned users).
        $userMap = User::whereNotNull('bitrix24_id')->pluck('id', 'bitrix24_id');
        if ($userMap->isEmpty()) {
            $this->warn('No users have bitrix24_id yet — run `php artisan bitrix24:provision-users` first.');
            return self::SUCCESS;
        }

        try {
            $client = new Bitrix24Client();
        } catch (\Throwable $e) {
            $this->error('Bitrix24 is not configured: '.$e->getMessage());
            return self::FAILURE;
        }

        $scanned = 0;
        $updated = 0;
        $unmapped = 0;
        $noLocal = 0;
        $stop = false;
        $next = null;

        do {
            try {
                $page = $client->call('crm.lead.list', [
                    'start'  => $cursor,
                    'order'  => ['ID' => 'ASC'],
                    'select' => ['ID', 'ASSIGNED_BY_ID'],
                ]);
            } catch (\Throwable $e) {
                $this->error('Failed to fetch leads from Bitrix24: '.$e->getMessage());
                return self::FAILURE;
            }

            $rows = $page['result'] ?? [];
            $next = $page['next'] ?? null;

            // bitrix lead id => intended local responsible user id
            $wantByBitrixLead = [];
            foreach ($rows as $row) {
                if ($limit > 0 && $scanned >= $limit) {
                    $stop = true;
                    break;
                }
                $scanned++;

                $bLeadId = (int) ($row['ID'] ?? 0);
                $bUserId = (int) ($row['ASSIGNED_BY_ID'] ?? 0);
                if ($bLeadId <= 0 || $bUserId <= 0) {
                    continue;
                }

                $local = (int) ($userMap[$bUserId] ?? 0);
                if (! $local) {
                    $unmapped++;
                    continue;
                }
                $wantByBitrixLead[$bLeadId] = $local;
            }

            if (! empty($wantByBitrixLead)) {
                $localLeads = Lead::whereIn('bitrix24_id', array_keys($wantByBitrixLead))
                    ->get(['id', 'bitrix24_id', 'responsible_person_id', 'lead_name']);

                $foundBitrixIds = [];
                foreach ($localLeads as $lead) {
                    $foundBitrixIds[(int) $lead->bitrix24_id] = true;
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
                    $updated++;
                }

                $noLocal += count(array_diff_key($wantByBitrixLead, $foundBitrixIds));
            }

            $this->info("Scanned {$scanned}… updated {$updated}");
            $cursor = $next ?? $cursor;
        } while ($next !== null && ! $stop);

        $this->newLine();
        $this->info(($dryRun ? 'Would update' : 'Updated')." {$updated} lead(s). "
            ."(scanned {$scanned}, unmapped Bitrix users: {$unmapped}, not in local DB: {$noLocal})");

        if ($unmapped > 0) {
            $this->warn("{$unmapped} lead(s) point to Bitrix users not in your DB — run `bitrix24:provision-users`.");
        }

        return self::SUCCESS;
    }
}
