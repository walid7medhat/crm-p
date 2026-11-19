<?php

namespace App\Console\Commands;

use App\Services\Marketing\TrackingSheetImporter;
use Illuminate\Console\Command;

class ImportMarketingTrackingSheets extends Command
{
    protected $signature = 'marketing:import-tracking-sheets {--keep : Keep existing rows instead of wiping}';

    protected $description = 'Import Blog / OIA / All Properties tracking Excel files into marketing_tracking_entries';

    public function handle(TrackingSheetImporter $importer): int
    {
        $this->info('Importing marketing tracking sheets…');

        try {
            $counts = $importer->importAll(wipe: ! $this->option('keep'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($counts as $category => $n) {
            $this->line("  {$category}: {$n} rows");
        }
        $this->info('Done. Total: ' . array_sum($counts));

        return self::SUCCESS;
    }
}
