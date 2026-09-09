<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Lead;
use App\Services\Bitrix24\Bitrix24Client;
use App\Services\Bitrix24\Bitrix24LeadImporter;

class CompareBitrixLeads extends Command
{
    protected $signature = 'bitrix:compare-leads
        {--import : Actually import the leads found in Bitrix24 but missing locally}';
    protected $description = 'Compare Bitrix leads with local DB and log missing ones';

    public function handle(Bitrix24Client $client)
    {
        $this->info('Fetching leads from Bitrix24...');

        $doImport = (bool) $this->option('import');
        $bitrixWebhook = config('bitrix24.webhook_url');

        // 📌 Local IDs
        $localIds = Lead::whereNotNull('bitrix24_id')
            ->pluck('bitrix24_id')
            ->toArray();

        $localIds = array_flip($localIds);

        $start = 0;
        $missingCount = 0;
        $missingIds = [];

        do {
            $response = Http::get($bitrixWebhook . 'crm.lead.list', [
                'start' => $start,
                'select' => ['ID', 'TITLE'],
            ]);

            $data = $response->json();

            if (!isset($data['result'])) {
                $this->error('Error fetching Bitrix data');
                return Command::FAILURE;
            }

            foreach ($data['result'] as $lead) {
                if (!isset($localIds[$lead['ID']])) {

                    $missingCount++;
                    $missingIds[] = (int) $lead['ID'];

                    Log::channel('bitrix_missing')->info('Lead Missing', [
                        'bitrix_id' => $lead['ID'],
                        'title' => $lead['TITLE'] ?? null,
                    ]);
                }
            }

            $start = $data['next'] ?? null;

        } while ($start);

        // ✅ Final summary log
        Log::channel('bitrix_missing')->info('Missing Leads Count: ' . $missingCount);

        $this->info('Missing leads: ' . $missingCount);
        $this->info('Logged to storage/logs/bitrix_missing.log');

        if (!$doImport || empty($missingIds)) {
            if (!$doImport && $missingCount > 0) {
                $this->info('Re-run with --import to pull these into the local DB.');
            }
            return Command::SUCCESS;
        }

        $this->info('Importing ' . count($missingIds) . ' missing lead(s)...');

        $importer = new Bitrix24LeadImporter($client, 1);
        $bar = $this->output->createProgressBar(count($missingIds));
        $bar->start();

        $imported = 0;
        $errors = 0;

        foreach ($missingIds as $bitrixId) {
            try {
                $full = $client->call('crm.lead.get', ['id' => $bitrixId]);
                $b24Lead = $full['result'] ?? null;

                if (!$b24Lead) {
                    $errors++;
                    Log::channel('bitrix_missing')->warning('crm.lead.get returned no result during import', [
                        'bitrix_id' => $bitrixId,
                    ]);
                    $bar->advance();
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

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Imported: {$imported}");
        $this->info("Errors: {$errors}");

        return Command::SUCCESS;
    }
}