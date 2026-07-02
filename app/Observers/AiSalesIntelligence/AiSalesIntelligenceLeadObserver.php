<?php

namespace App\Observers\AiSalesIntelligence;

use App\Models\Lead;

class AiSalesIntelligenceLeadObserver
{
    public function updated(Lead $lead): void
    {
        if (!AiSalesIntelligenceDispatcher::automationEnabled()) {
            return;
        }

        $changed = array_keys($lead->getChanges());
        $relevant = array_intersect($changed, [
            'responsible_person_id',
            'stage_id',
            'interaction_result',
            'revert',
            'converted_to_deal_id',
            'first_contacted_at',
        ]);

        if ($relevant === []) {
            return;
        }

        if ($lead->responsible_person_id) {
            AiSalesIntelligenceDispatcher::dispatchForUser((int) $lead->responsible_person_id);
        }

        if ($lead->wasChanged('responsible_person_id')) {
            $old = $lead->getOriginal('responsible_person_id');
            if ($old) {
                AiSalesIntelligenceDispatcher::dispatchForUser((int) $old, 30);
            }
        }
    }
}
