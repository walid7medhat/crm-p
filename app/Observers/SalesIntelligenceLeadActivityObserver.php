<?php

namespace App\Observers;

use App\Jobs\RecalculateSalesAgentScoreJob;
use App\Models\LeadActivity;
use App\Models\SalesIntelligenceSetting;

class SalesIntelligenceLeadActivityObserver
{
    public function saved(LeadActivity $activity): void
    {
        if (!$this->automationEnabled('recalculate_on_activity')) {
            return;
        }

        if ($activity->user_id) {
            RecalculateSalesAgentScoreJob::dispatch((int) $activity->user_id)->delay(now()->addSeconds(20));
        }

        $lead = $activity->lead;
        if ($lead && $lead->responsible_person_id) {
            RecalculateSalesAgentScoreJob::dispatch((int) $lead->responsible_person_id)->delay(now()->addSeconds(25));
        }
    }

    protected function automationEnabled(string $key): bool
    {
        try {
            $flags = SalesIntelligenceSetting::current()->automation_flags ?? [];

            return ($flags[$key] ?? true) === true;
        } catch (\Throwable) {
            return true;
        }
    }
}
