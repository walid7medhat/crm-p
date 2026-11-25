<?php

namespace App\Http\Controllers\Api\Listing;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Listing\ListingGridResource;
use App\Models\Listing;
use App\Models\SavedListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class SavedListingController extends Controller
{
    public function index(): JsonResponse
    {
        $userId = (int) Auth::id();
        $ids = SavedListing::query()
            ->where('user_id', $userId)
            ->latest()
            ->pluck('listing_id');

        $listings = Listing::query()
            ->with([
                'propertyType:id,name',
                'area:id,name,parent_id,type',
                'area.parent:id,name,parent_id,type',
                'area.parent.parent:id,name,parent_id,type',
                'agent:id,name,display_name,email',
                'project:id,title,about,area_id,developer_id',
                'project.developer:id,name,avatar_path,noc_fees_ready,noc_fees_off_plan,dubai_noc_fees_ready,dubai_noc_fees_off_plan',
                'firstGalleryImage:id,imageable_id,imageable_type,image_path,order',
            ])
            ->withCount('galleryImages')
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Listing $listing) => $ids->search($listing->id))
            ->values();

        return ApiResponse::success(ListingGridResource::collection($listings)->resolve());
    }

    public function ids(): JsonResponse
    {
        $ids = SavedListing::query()
            ->where('user_id', Auth::id())
            ->pluck('listing_id');

        return ApiResponse::success($ids);
    }

    public function toggle(Listing $listing): JsonResponse
    {
        $userId = (int) Auth::id();
        $existing = SavedListing::query()
            ->where('user_id', $userId)
            ->where('listing_id', $listing->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return ApiResponse::success(['saved' => false, 'listing_id' => $listing->id]);
        }

        SavedListing::query()->create([
            'user_id' => $userId,
            'listing_id' => $listing->id,
        ]);

        return ApiResponse::success(['saved' => true, 'listing_id' => $listing->id]);
    }
}
