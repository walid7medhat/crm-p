<?php

namespace App\Console\Commands;

use App\Services\DailyMotivation\DailyMotivationService;
use App\Services\DailyMotivation\MotivationWorkbookImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportDailyMotivationMessages extends Command
{
    protected $signature = 'motivation:import {path? : Path to OIA_Daily_Sales_Motivation_120.xlsx}';

    protected $description = 'Import the 120 bilingual Daily Motivation messages. Safe to run again; message numbers are updated in place and never deleted.';

    public function handle(MotivationWorkbookImporter $importer, DailyMotivationService $service): int
    {
        $path = $this->argument('path');
        $resolved = $path ? $this->resolveExplicit($path) : $service->findWorkbook();

        if (! $resolved) {
            $this->error('Workbook not found. Place OIA_Daily_Sales_Motivation_120.xlsx in the project root, or pass a path.');
            foreach ($service->workbookCandidates() as $candidate) {
                $this->line('  - '.$candidate);
            }

            return self::FAILURE;
        }

        try {
            $result = $importer->import($resolved);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $service->forgetCaches();
        $this->info("Imported {$result['total']} messages from {$resolved} ({$result['created']} new, {$result['updated']} updated).");

        return self::SUCCESS;
    }

    private function resolveExplicit(string $path): ?string
    {
        if (is_file($path)) {
            return $path;
        }

        $fromBase = base_path($path);

        return is_file($fromBase) ? $fromBase : null;
    }
}
