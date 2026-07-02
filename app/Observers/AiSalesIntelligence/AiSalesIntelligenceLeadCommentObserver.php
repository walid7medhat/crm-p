<?php

namespace App\Observers\AiSalesIntelligence;

use App\Models\LeadComment;

class AiSalesIntelligenceLeadCommentObserver
{
    public function created(LeadComment $comment): void
    {
        if (!AiSalesIntelligenceDispatcher::commentEnabled()) {
            return;
        }

        AiSalesIntelligenceDispatcher::dispatchForUser((int) $comment->user_id);

        $lead = $comment->lead;
        if ($lead?->responsible_person_id) {
            AiSalesIntelligenceDispatcher::dispatchForUser((int) $lead->responsible_person_id, 30);
        }
    }
}
