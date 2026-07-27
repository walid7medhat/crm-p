<?php

namespace App\Observers;

use App\Models\LeadComment;

class LeadCommentObserver
{
    /**
     * Handle the LeadComment "created" event.
     */
    public function created(LeadComment $leadComment): void
    {
        //
         if ($leadComment->lead) {
            $leadComment->lead->updateQuietly([
                'bitrix24_last_activity_by_id' => auth()->id(),
                'bitrix24_last_activity_at' => now(),
            ]);
        }
    }

    /**
     * Handle the LeadComment "updated" event.
     */
    public function updated(LeadComment $leadComment): void
    {
        //
        if ($leadComment->lead) {
            $leadComment->lead->updateQuietly([
                'bitrix24_last_activity_by_id' => auth()->id(),
                'bitrix24_last_activity_at' => now(),
            ]);
        }
    }

    /**
     * Handle the LeadComment "deleted" event.
     */
    public function deleted(LeadComment $leadComment): void
    {
        //
        if ($leadComment->lead) {
            $leadComment->lead->updateQuietly([
                'bitrix24_last_activity_by_id' => auth()->id(),
                'bitrix24_last_activity_at' => now(),
            ]);
        }
    }

    /**
     * Handle the LeadComment "restored" event.
     */
    public function restored(LeadComment $leadComment): void
    {
        //
    }

    /**
     * Handle the LeadComment "force deleted" event.
     */
    public function forceDeleted(LeadComment $leadComment): void
    {
        //
    }
}
