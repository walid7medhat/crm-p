<?php

namespace App\Observers;

use App\Models\LeadActivity;

class ActivityObserver
{
    /**
     * Handle the LeadActivity "created" event.
     */
    public function created(LeadActivity $leadActivity): void
    {
        //
         if ($leadActivity->lead) {
                $leadActivity->lead->updateQuietly([
                    'bitrix24_last_activity_by_id' => auth()->id(),
                    'bitrix24_last_activity_at' => now(),
                ]);
            }
    }

    /**
     * Handle the LeadActivity "updated" event.
     */
    public function updated(LeadActivity $leadActivity): void
    {
        //
         if ($leadActivity->lead) {
                $leadActivity->lead->updateQuietly([
                    'bitrix24_last_activity_by_id' => auth()->id(),
                    'bitrix24_last_activity_at' => now(),
                ]);
            }
    }

    /**
     * Handle the LeadActivity "deleted" event.
     */
    public function deleted(LeadActivity $leadActivity): void
    {
        //
         if ($leadActivity->lead) {
                $leadActivity->lead->updateQuietly([
                    'bitrix24_last_activity_by_id' => auth()->id(),
                    'bitrix24_last_activity_at' => now(),
                ]);
            }
    }

    /**
     * Handle the LeadActivity "restored" event.
     */
    public function restored(LeadActivity $leadActivity): void
    {
        //
    }

    /**
     * Handle the LeadActivity "force deleted" event.
     */
    public function forceDeleted(LeadActivity $leadActivity): void
    {
        //
    }
}
