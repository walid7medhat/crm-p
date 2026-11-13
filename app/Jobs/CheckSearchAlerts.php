<?php

namespace App\Jobs;

use App\Http\Controllers\Api\Listing\ListingController;
use App\Models\Listing;
use App\Models\SearchAlert;
use App\Models\User;
use App\Notifications\NewListingMatchedNotification;
use App\Support\Brand\Concerns\BrandAwareJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;

class CheckSearchAlerts implements ShouldQueue
{
    use BrandAwareJob;
    use Queueable;

    public function __construct()
    {
        $this->initializeBrandContext();
    }

    public function handle()
    {
        $this->runWithBrandContext(function () {
            $alerts = SearchAlert::where('is_active', true)->get();

            foreach ($alerts as $alert) {
                $filters = $alert->filters;

                $request = new Request($filters);

                $controller = app(ListingController::class);

                $result = $controller->getListingsData($request);
                if ($result['pagination']['total'] > 0) {
                    $user = User::find($alert->user_id);

                    $listingData = $result['listings'][0];
                    \Log::info($listingData);
                    $listing = Listing::find($listingData['id']);

                    if ($listing && $user) {
                        $user->notify(new NewListingMatchedNotification($listing));
                    }

                    $alert->update([
                        'is_active' => false,
                    ]);
                }
            }
        });
    }
}
