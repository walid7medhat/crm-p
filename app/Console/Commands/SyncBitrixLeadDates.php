<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\Bitrix24\Bitrix24Client;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncBitrixLeadDates extends Command
{
    protected $signature = 'bitrix24:sync-lead-dates
        {--dry-run : Show what would change without writing}
        {--fresh : Ignore saved progress and start from the first lead}';

    protected $description = 'Backfill created_at and bitrix24_last_activity_at for leads imported from Bitrix24, from DATE_CREATE / LAST_ACTIVITY_TIME';

    private const BATCH_SIZE = 50;
    private const CACHE_KEY = 'bitrix24_sync_lead_dates_last_id';

    public function handle(Bitrix24Client $client): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($this->option('fresh')) {
            Cache::forget(self::CACHE_KEY);
        }

        $lastId = $this->option('fresh') ? 0 : (int) Cache::get(self::CACHE_KEY, 0);

        if ($lastId > 0) {
            $this->info("Resuming after local lead id {$lastId}");
        }

        // Only select id/bitrix24_id — without this, chunkById hydrates every
        // column per row, including bitrix24_data (the full raw Bitrix payload
        // as JSON), which is a lot of unneeded DB I/O and memory at scale.
        // Also skip the upfront count(): a COUNT(*) is its own slow query over
        // a large table — just show a running counter instead of a percentage.
        $query = Lead::select(['id', 'bitrix24_id'])
            ->whereNotNull('bitrix24_id')
            ->where('id', '>', $lastId);

        $this->info('Scanning Bitrix-imported leads...');
        $bar = $this->output->createProgressBar();
        $bar->start();

        $updated = 0;
        $historyFixed = 0;
        $skipped = 0;
        $errors = 0;

        $query->orderBy('id')->chunkById(self::BATCH_SIZE, function ($leads) use (
            $client, $dryRun, $bar, &$updated, &$historyFixed, &$skipped, &$errors
        ) {
            $idMap = $leads->keyBy('bitrix24_id');
            $bitrixIds = $idMap->keys()->all();

            try {
                $response = $client->call('crm.lead.list', [
                    'filter' => ['ID' => $bitrixIds],
                    'select' => ['ID', 'DATE_CREATE', 'LAST_ACTIVITY_TIME'],
                ]);

                $createdValues = [];
                $activityValues = [];

                foreach ($response['result'] ?? [] as $b24Lead) {
                    $b24Id = (int) ($b24Lead['ID'] ?? 0);
                    $lead = $idMap->get($b24Id);

                    if (!$lead) {
                        $skipped++;
                        continue;
                    }

                    $createdAt = $this->parseBitrixDate($b24Lead['DATE_CREATE'] ?? null);
                    $activityAt = $this->parseBitrixDate($b24Lead['LAST_ACTIVITY_TIME'] ?? null);

                    if (!$createdAt && !$activityAt) {
                        $skipped++;
                        continue;
                    }

                    if ($createdAt) {
                        $createdValues[$lead->id] = $createdAt->format('Y-m-d H:i:s');
                    }
                    if ($activityAt) {
                        $activityValues[$lead->id] = $activityAt->format('Y-m-d H:i:s');
                    }

                    $updated++;
                }

                if (!$dryRun) {
                    $this->bulkUpdateColumn('created_at', $createdValues);
                    $this->bulkUpdateColumn('bitrix24_last_activity_at', $activityValues);
                    // Keep the "created" history entry's timestamp in sync — it's
                    // stamped at import time by LeadHistoryHelper::log() (no explicit
                    // created_at), so it drifts from the real Bitrix creation date
                    // we just backfilled above.
                    $historyFixed += $this->bulkUpdateHistoryCreatedAt($createdValues);
                }
            } catch (\Throwable $e) {
                $errors++;
                Log::error('bitrix24:sync-lead-dates batch failed: ' . $e->getMessage());
            }

            Cache::put(self::CACHE_KEY, $leads->last()->id, now()->addDays(7));
            $bar->advance($leads->count());
        });

        $bar->finish();
        $this->newLine(2);

        Cache::forget(self::CACHE_KEY);

        $this->info("Updated: {$updated}");
        $this->info("History 'created' rows fixed: {$historyFixed}");
        $this->info("Skipped: {$skipped}");
        $this->info("Errors: {$errors}");

        if ($dryRun) {
            $this->info('Dry run — no changes written. Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }

    private function parseBitrixDate(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Bulk update one column for many rows in a single parameterized query
     * using CASE WHEN, instead of one UPDATE per row.
     *
     * @param array<int,string> $values local_lead_id => date string
     */
    private function bulkUpdateColumn(string $column, array $values): void
    {
        if (empty($values)) {
            return;
        }

        $ids = array_keys($values);

        $case = 'CASE id ';
        $bindings = [];
        foreach ($values as $id => $value) {
            $case .= 'WHEN ? THEN ? ';
            $bindings[] = $id;
            $bindings[] = $value;
        }
        $case .= 'END';

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $bindings = array_merge($bindings, $ids);

        DB::update(
            "UPDATE leads SET {$column} = {$case} WHERE id IN ({$placeholders})",
            $bindings
        );
    }

    /**
     * Re-stamp each lead's "created" LeadHistory row (created_at/updated_at) to
     * match the real Bitrix creation date, instead of whenever it was imported.
     *
     * @param array<int,string> $values local_lead_id => date string
     * @return int rows actually updated
     */
    private function bulkUpdateHistoryCreatedAt(array $values): int
    {
        if (empty($values)) {
            return 0;
        }

        $leadIds = array_keys($values);
        $bindings = [];

        $buildCase = function () use ($values, &$bindings) {
            $case = 'CASE lead_id ';
            foreach ($values as $leadId => $date) {
                $case .= 'WHEN ? THEN ? ';
                $bindings[] = $leadId;
                $bindings[] = $date;
            }
            return $case . 'END';
        };

        $createdCase = $buildCase();
        $updatedCase = $buildCase();

        $bindings = array_merge($bindings, $leadIds);
        $idPlaceholders = implode(',', array_fill(0, count($leadIds), '?'));

        return DB::update(
            "UPDATE lead_histories
             SET created_at = {$createdCase}, updated_at = {$updatedCase}
             WHERE lead_id IN ({$idPlaceholders})
               AND JSON_UNQUOTE(JSON_EXTRACT(changes, '$.action')) = 'created'",
            $bindings
        );
    }
}
