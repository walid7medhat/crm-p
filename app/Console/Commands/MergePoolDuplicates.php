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
        // Backstop for large tables; the service itself reads contacts in batches.
        ini_set('memory_limit', '512M');
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $merged = $service->mergeAllEligibleDuplicates();
        $this->info("Merged {$merged} lead(s).");

        return self::SUCCESS;
    }
}
