<?php

namespace App\Observers;

use App\Jobs\MatchListingToLeadsJob;
use App\Models\Listing;
use App\Services\Matching\LeadMatcher;

/**
 * Match a listing to clients when it becomes an active unit (created live, or approved,
 * reactivated, unarchived) and again when its price goes down.
 */
class ListingMatchObserver
{
    private const ACTIVE_FIELDS = ['is_active', 'approved', 'status', 'is_archived'];

    private const SHAPE_FIELDS = ['area_id', 'project_id', 'number_of_bedrooms', 'property_type_id', 'listing_status', 'completion_status'];

    public function created(Listing $listing): void
    {
        if ($listing->isActiveUnit()) {
            MatchListingToLeadsJob::dispatch((int) $listing->id)->afterCommit();
        }
    }

    public function updated(Listing $listing): void
    {
        if (! $listing->isActiveUnit()) {
            if ($listing->wasChanged(self::ACTIVE_FIELDS)) {
                app(LeadMatcher::class)->forgetListing((int) $listing->id);
            }

            return;
        }

        $oldPrice = (float) $listing->getOriginal('price');
        $newPrice = (float) $listing->price;
        if ($listing->wasChanged('price') && $oldPrice > 0 && $newPrice > 0 && $newPrice < $oldPrice) {
            MatchListingToLeadsJob::dispatch((int) $listing->id, true)->afterCommit();

            return;
        }

        if ($listing->wasChanged(self::ACTIVE_FIELDS) || $listing->wasChanged(self::SHAPE_FIELDS) || $listing->wasChanged('price')) {
            MatchListingToLeadsJob::dispatch((int) $listing->id)->afterCommit();
        }
    }
}
