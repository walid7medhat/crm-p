<?php

namespace App\Helpers;

use App\Models\LeadHistory;
use App\Models\Deal;
class DealHistoryHelper
{
    
    public static function log(
        int $deal_id,
        array $changes = [],
        ?int $userId = null
    ): void {
        if (empty($changes)) {
            return;
        }
           $deal=Deal::find($deal_id);
        LeadHistory::create([
            'deal_id' => $deal_id,
            'lead_id'=>$deal?->lead_id,
            'user_id' => $userId ?? auth()->id(),
            'changes' => $changes,
        ]);
    }
}
