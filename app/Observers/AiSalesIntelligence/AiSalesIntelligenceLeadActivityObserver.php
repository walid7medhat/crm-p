<?php

namespace App\Observers\AiSalesIntelligence;

use App\Models\LeadActivity;

class AiSalesIntelligenceLeadActivityObserver
{
    public function saved(LeadActivity $activity): void
    {
        if (!AiSalesIntelligenceDispatcher::activityEnabled()) {
            return;
        }

        AiSalesIntelligenceDispatcher::dispatchForUser((int) $activity->user_id);

        $lead = $activity->lead;
        if ($lead?->responsible_person_id) {
            AiSalesIntelligenceDispatcher::dispatchForUser((int) $lead->responsible_person_id, 30);
        }
    }
}
