<?php
namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\LeadPoolMergeService;
use Illuminate\Console\Command;

class MergePoolDuplicates extends Command
{
    protected $signature = 'leads:merge-pool-duplicates';
    protected $description = 'Merge duplicate Lead Pool leads (newest lead becomes the default)';

    public function handle(LeadPoolMergeService $service): int
    {
        $merged = 0;

        Lead::query()
            ->where('stage_id', config('lead_merge.lead_pool_stage_id'))
            ->where('work_phone_digits', '!=', '')
            ->whereNull('converted_to_deal_id')
            ->groupBy('work_phone_digits')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('work_phone_digits')
            ->each(function ($digits) use ($service, &$merged) {
                foreach ($service->mergeDigits($digits) as $ids) {
                    $merged += count($ids);
                }
            });

        $this->info("Merged {$merged} lead(s).");
        return self::SUCCESS;
    }
}