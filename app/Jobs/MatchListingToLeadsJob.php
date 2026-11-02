<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Services\Matching\LeadMatcher;
use App\Services\Matching\MatchNotifier;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/** A listing went live or got cheaper: find the clients it fits and tell their agents. */
class MatchListingToLeadsJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $uniqueFor = 120;

    public int $timeout = 300;

    public function __construct(public int $listingId, public bool $priceDropped = false)
    {
    }

    public function uniqueId(): string
    {
        return $this->listingId.':'.(int) $this->priceDropped;
    }

    public function handle(LeadMatcher $matcher, MatchNotifier $notifier): void
    {
        $listing = Listing::query()->find($this->listingId);
        if (! $listing) {
            return;
        }

        $rows = $matcher->matchListing($listing, $this->priceDropped);
        // Let the next lead-view match load pick up this listing immediately.
        foreach ($rows->pluck('lead_id')->unique()->filter() as $leadId) {
            Cache::forget('lead-matches:fresh:'.(int) $leadId);
        }
        $notifier->listingMatches($listing, $rows, $this->priceDropped);
    }
}
