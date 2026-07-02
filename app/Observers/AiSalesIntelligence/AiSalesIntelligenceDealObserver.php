<?php

namespace App\Observers\AiSalesIntelligence;

use App\Models\Deal;

class AiSalesIntelligenceDealObserver
{
    public function updated(Deal $deal): void
    {
        if (!AiSalesIntelligenceDispatcher::dealEnabled()) {
            return;
        }

        if (!$deal->wasChanged('status')) {
            return;
        }

        if (!in_array($deal->status, ['completed', 'cancelled'], true)) {
            return;
        }

        if ($deal->responsible_person_id) {
            AiSalesIntelligenceDispatcher::dispatchForUser((int) $deal->responsible_person_id);
        }
    }
}
