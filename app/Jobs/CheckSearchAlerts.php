<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\SearchAlert;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\Listing\ListingController;
use App\Notifications\NewListingMatchedNotification;
use App\Models\Listing;
class CheckSearchAlerts implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */


    public function handle()
    {
          $alerts = SearchAlert::where('is_active', true)->get();

            foreach ($alerts as $alert) {
        
                $filters = $alert->filters;
        
                $request = new Request($filters);
        
                $controller = app(ListingController::class);
        
                $result = $controller->getListingsData($request);
                if ($result['pagination']['total'] > 0) {
        
                    $user = User::find($alert->user_id);
        
                    // ناخد أول listing من النتائج
                    $listingData = $result['listings'][0]; // array
                 \Log::info($listingData);
                    $listing = Listing::find($listingData['id']); // احنا محتاجين الـ model كامل
        
                    if ($listing) {
                        $user->notify(new NewListingMatchedNotification($listing));
                    }
        
                    $alert->update([
                        'is_active' => false
                    ]);
                }
            }
    }
}
