<?php

namespace App\Jobs;

use App\Services\LeadAssignmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecoverStuckLeadsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use \App\Support\Brand\Concerns\BrandAwareJob;

    /**
     * Scheduled/global dispatch defaults to trusted BrandContext at construct time
     * (typically OIA on CLI). HTTP-dispatched instances capture the host brand.
     */
    public function __construct()
    {
        $this->initializeBrandContext();
    }

    public function handle(LeadAssignmentService $service): void
    {
        $this->runWithBrandContext(function () use ($service) {
            $service->recoverStuckLeads();
        });
    }
}
