<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Services\Matching\LeadMatcher;
use App\Services\Matching\MatchNotifier;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * A lead was created or its requirements changed: match it against current stock.
 * $notify is off for bulk imports and syncs, so old leads coming in don't flood agents.
 * Also used afterResponse() from GET /leads/{id}/matches so cold open never blocks on scoring.
 */
class RefreshLeadMatchesJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $uniqueFor = 60;

    /** Must match LeadMatchController::FRESH_MINUTES / freshKey(). */
    private const FRESH_MINUTES = 30;

    public function __construct(public int $leadId, public bool $notify = true)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->leadId;
    }

    public function handle(LeadMatcher $matcher, MatchNotifier $notifier): void
    {
        $lead = Lead::query()->find($this->leadId);
        if (! $lead || $lead->archived_at || ! in_array((int) $lead->stage_id, $matcher->workingStageIds(), true)) {
            return;
        }

        $rows = $matcher->refreshLead($lead);
        Cache::put('lead-matches:fresh:'.$this->leadId, true, now()->addMinutes(self::FRESH_MINUTES));
        if ($this->notify) {
            $notifier->leadMatches($lead, $rows);
        }
    }
}
