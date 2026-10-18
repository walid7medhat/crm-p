<?php

namespace App\Jobs;

use App\Models\LeadIntelligenceScan;
use App\Services\LeadScan\LeadScanRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunLeadIntelligenceScan implements ShouldQueue
{
    use Queueable;

    public int $timeout = 90;

    public int $tries = 2;

    public function __construct(public int $scanId)
    {
    }

    public function handle(LeadScanRunner $runner): void
    {
        $runner->run($this->scanId);
    }

    public function failed(\Throwable $exception): void
    {
        LeadIntelligenceScan::query()
            ->whereKey($this->scanId)
            ->whereIn('status', ['queued', 'processing'])
            ->update([
                'status' => 'failed',
                'progress' => 100,
                'status_message' => 'The scan could not be completed. Try again.',
                'finished_at' => now(),
            ]);
    }
}
