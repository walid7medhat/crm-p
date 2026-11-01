<?php

namespace App\Observers;

use App\Jobs\RefreshLeadMatchesJob;
use App\Models\Lead;
use App\Services\Matching\LeadMatcher;

/**
 * Re-match a lead when it arrives or when what the client wants changes.
 * Only fresh leads and edits made by a signed-in user notify the agent; imports and
 * background syncs still store matches, quietly.
 */
class LeadMatchObserver
{
    public function created(Lead $lead): void
    {
        $fresh = ! $lead->created_at || $lead->created_at->greaterThan(now()->subDays(2));
        RefreshLeadMatchesJob::dispatch((int) $lead->id, $fresh)->afterCommit();
    }

    public function updated(Lead $lead): void
    {
        if ($lead->wasChanged(LeadMatcher::REQUIREMENT_FIELDS) || $lead->wasChanged('stage_id')) {
            RefreshLeadMatchesJob::dispatch((int) $lead->id, auth()->check())->afterCommit();
        }
    }
}
