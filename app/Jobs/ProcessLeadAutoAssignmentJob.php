<?php

namespace App\Jobs;

use App\Models\LeadAssignmentSetting;
use App\Services\LeadAssignmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessLeadAutoAssignmentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $leadId) {}

    public function handle(LeadAssignmentService $service): void
    {
        $settings = LeadAssignmentSetting::current();
        if ($settings->system_disabled || !$settings->auto_assign) {
            return;
        }

        if ($settings->simple_mode_enabled) {
            $service->assignSingleLeadSimple($this->leadId);

            return;
        }

        if ($settings->mode !== 'realtime') {
            return;
        }

        $service->assignLeadById($this->leadId, 'auto', $settings, null, null, false);
    }
}
