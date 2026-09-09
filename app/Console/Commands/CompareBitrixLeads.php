<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\Lead;
use App\Services\Bitrix24\Bitrix24Client;
use App\Services\Bitrix24\Bitrix24LeadImporter;

class CompareBitrixLeads extends Command
{
    protected $signature = 'bitrix:compare-leads
        {--import : Actually import the leads found in Bitrix24 but missing locally}
        {--fresh : Ignore saved progress and start over from offset 0}';
    protected $description = 'Compare Bitrix leads with local DB and log/import missing ones';

    protected const CACHE_KEY = 'bitrix_compare_leads_last_offset';

    public function handle(Bitrix24Client $client)
    {
        $this->info('Fetching leads from Bitrix24...');

        $doImport = (bool) $this->option('import');
        $fresh = (bool) $this->option('fresh');

        $importer = $doImport ? new Bitrix24LeadImporter($client, 1) : null;

        // 📌 Local IDs
        $localIds = array_flip(
            Lead::whereNotNull('bitrix24_id')->pluck('bitrix24_id')->toArray()
        );

        $start = $fresh ? 0 : (int) Cache::get(self::CACHE_KEY, 0);
        if ($start > 0) {
            $this->info("↻ Resuming from offset {$start}");
        }

        $missingCount = 0;
        $imported = 0;
        $errors = 0;

        do {
            try {
                $data = $client->call('crm.lead.list', [
                    'start' => $start,
                    'select' => ['ID', 'TITLE'],
                ]);
            } catch (\Throwable $e) {
                Cache::put(self::CACHE_KEY, $start, now()->addDays(7));
                $this->error("Failed at offset {$start}: {$e->getMessage()}");
                $this->warn('Progress saved — rerun the command to resume from here.');
                return Command::FAILURE;
            }

            if (!isset($data['result'])) {
                Cache::put(self::CACHE_KEY, $start, now()->addDays(7));
                $this->error('Error fetching Bitrix data — progress saved, rerun to resume.');
                return Command::FAILURE;
            }

            foreach ($data['result'] as $lead) {
                $bitrixId = (int) ($lead['ID'] ?? 0);
                if (!$bitrixId || isset($localIds[$bitrixId])) {
                    continue;
                }

                $missingCount++;

                Log::channel('bitrix_missing')->info('Lead Missing', [
                    'bitrix_id' => $bitrixId,
                    'title' => $lead['TITLE'] ?? null,
                ]);

                if (!$doImport) {
                    continue;
                }

                try {
                    $full = $client->call('crm.lead.get', ['id' => $bitrixId]);
                    $b24Lead = $full['result'] ?? null;

                    if (!$b24Lead) {
                        $errors++;
                        Log::channel('bitrix_missing')->warning('crm.lead.get returned no result during import', [
                            'bitrix_id' => $bitrixId,
                        ]);
                        continue;
                    }

                    $importer->importOne($b24Lead);
                    $imported++;
                } catch (\Throwable $e) {
                    $errors++;
                    Log::channel('bitrix_missing')->error('Import failed for missing lead', [
                        'bitrix_id' => $bitrixId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $start = $data['next'] ?? null;

            if ($start !== null) {
                Cache::put(self::CACHE_KEY, $start, now()->addDays(7));
            }

        } while ($start !== null);

        Cache::forget(self::CACHE_KEY);

        // ✅ Final summary log
        Log::channel('bitrix_missing')->info('Missing Leads Count: ' . $missingCount);

        $this->info('Missing leads found this run: ' . $missingCount);
        $this->info('Logged to storage/logs/bitrix_missing.log');

        if ($doImport) {
            $this->info("Imported: {$imported}");
            $this->info("Errors: {$errors}");
        } elseif ($missingCount > 0) {
            $this->info('Re-run with --import to pull these into the local DB.');
        }

        return Command::SUCCESS;
    }
}
