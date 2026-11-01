<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Services\Matching\LeadMatcher;
use App\Services\Matching\MatchNotifier;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
        $notifier->listingMatches($listing, $rows, $this->priceDropped);
    }
}
