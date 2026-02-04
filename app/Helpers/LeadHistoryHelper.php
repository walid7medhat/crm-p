<?php

namespace App\Helpers;

use App\Models\LeadHistory;

class LeadHistoryHelper
{
    
    public static function log(
        int $leadId,
        array $changes = [],
        ?int $userId = null
    ): void {
        if (empty($changes)) {
            return;
        }

        LeadHistory::create([
            'lead_id' => $leadId,
            'user_id' => $userId ?? auth()->id(),
            'changes' => $changes,
        ]);
    }
}
