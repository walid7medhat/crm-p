<?php

namespace App\Console\Commands;

use App\Services\Bitrix24\Bitrix24Client;
use App\Services\Bitrix24\Bitrix24LeadImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ImportBitrixLeadsById extends Command
{
    protected $signature = 'bitrix:import-leads-by-id
        {ids?* : Bitrix24 lead IDs to import}
        {--file= : Path to a text file with one Bitrix24 lead ID per line}';

    protected $description = 'Import specific Bitrix24 leads by ID directly via crm.lead.get (no full list scan)';

    public function handle(Bitrix24Client $client): int
    {
        $ids = collect($this->argument('ids'))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        if ($file = $this->option('file')) {
            if (! file_exists($file)) {
                $this->error("File not found: {$file}");
                return self::FAILURE;
            }

            $fromFile = collect(file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
                ->map(fn ($line) => (int) trim($line))
                ->filter();

            $ids = $ids->merge($fromFile);
        }

        $ids = $ids->unique()->values();

        if ($ids->isEmpty()) {
            $this->error('No Bitrix24 IDs given. Pass them as arguments or via --file=path.');
            return self::FAILURE;
        }

        $this->info("Importing {$ids->count()} Bitrix24 lead(s) by ID...");

        $importer = new Bitrix24LeadImporter($client, 1);

        $bar = $this->output->createProgressBar($ids->count());
        $bar->start();

        $imported = 0;
        $errors = 0;

        foreach ($ids as $bitrixId) {
            try {
                $full = $client->call('crm.lead.get', ['id' => $bitrixId]);
                $b24Lead = $full['result'] ?? null;

                if (! $b24Lead) {
                    $errors++;
                    $this->newLine();
                    $this->warn("Bitrix24 lead {$bitrixId} not found (crm.lead.get returned no result).");
                } else {
                    $importer->importOne($b24Lead);
                    $imported++;
                }
            } catch (\Throwable $e) {
                $errors++;
                Log::channel('bitrix_missing')->error('Import by ID failed', [
                    'bitrix_id' => $bitrixId,
                    'error' => $e->getMessage(),
                ]);
                $this->newLine();
                $this->warn("Failed for {$bitrixId}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Imported: {$imported}");
        $this->info("Errors: {$errors}");

        return self::SUCCESS;
    }
}
