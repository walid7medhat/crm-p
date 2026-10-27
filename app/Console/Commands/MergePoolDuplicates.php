<?php
namespace App\Console\Commands;

use App\Services\EligibleStagesAutoMergeService;
use Illuminate\Console\Command;

class MergePoolDuplicates extends Command
{
    protected $signature = 'leads:merge-pool-duplicates';
    protected $description = 'Auto-merge duplicate leads across Lead Pool, Unqualified, and Lost (newest → Lead Pool)';

    public function handle(EligibleStagesAutoMergeService $service): int
    {
        $merged = $service->mergeAllEligibleDuplicates();
        $this->info("Merged {$merged} lead(s).");

        return self::SUCCESS;
    }
}
