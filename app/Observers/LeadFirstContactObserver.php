<?php

namespace App\Observers;

use App\Models\LeadActivity;

class LeadFirstContactObserver
{
    public function created(LeadActivity $activity): void
    {
        if (!$activity->lead_id) {
            return;
        }

        try {
            \App\Models\Lead::query()->whereKey($activity->lead_id)
                ->whereNull('first_contacted_at')
                ->update(['first_contacted_at' => now()]);
        } catch (\Throwable) {
            // column may be missing in very old DBs
        }
    }
}
