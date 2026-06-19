<?php

namespace App\Http\Resources\Listing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Helpers\ImageHelper;
use App\Models\ListingAccessRequest;
use App\Models\User;

class ListingGridResource extends JsonResource
{
    /** Per-request memo: "{listingId}:{userId}" => approved request_type list. */
    protected static array $accessRequestCache = [];

    protected function resolveCanEditPaymentBreakdown(Request $request): bool
    {
        $user = $request->user() ?? auth()->user();
        if (! $user) {
            return false;
        }
        if ($user->hasRole('super_admin') || $user->hasRole('admin')) {
            return true;
        }

        return (int) $this->agent_id === (int) $user->id;
    }

    protected function hasApprovedAccess(?int $userId, string $requestType): bool
    {
        if (! $userId) return false;
        $key = $this->id . ':' . $userId;
        if (! array_key_exists($key, self::$accessRequestCache)) {
            self::$accessRequestCache[$key] = $this->relationLoaded('accessRequests')
                ? $this->accessRequests
                    ->where('requested_by', $userId)
                    ->where('status', 'approved')
                    ->pluck('request_type')
                    ->all()
                : ListingAccessRequest::query()
                    ->where('listing_id', $this->id)
                    ->where('requested_by', $userId)
                    ->where('status', 'approved')
                    ->pluck('request_type')
                    ->all();
        }
        return in_array($requestType, self::$accessRequestCache[$key], true);
    }

    public function toArray(Request $request): array
    {$isTodayMain = $this->created_at?->isToday();

        $user = auth()->user();
        $isPrivilegedViewer = $user && (
            $user->hasRole('super_admin')
            || $this->agent_id == $user->id
            || ($user->hasRole('manager') && $user->listing_team == 1)
            || $user->id == 30
        );
        $canSeeUnitNumber = $isPrivilegedViewer
            || ($user && $this->hasApprovedAccess($user->id, ListingAccessRequest::TYPE_UNIT_NUMBER));
        $canSeeOwnerData = $isPrivilegedViewer
            || ($user && $this->hasApprovedAccess($user->id, ListingAccessRequest::TYPE_OWNER_DATA));

        return [
            'id' => $this->id,
            'title' => $this->project?->title,
            // collect([
            //     $this->project?->title,
            //     $this->area?->name,
            // ])->filter()->implode(', '),
              'approved' => (bool) $this->approved,
                'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
                'approved_by' => $this->whenLoaded('approvedBy', function() {
                    return [
                        'id' => $this->approvedBy->id,
                        'name' => User::resolveDisplayName($this->approvedBy),
                    ];
                }),
                'approval_status' => $this->approved ? 'approved' : 'pending',
                   'rejection_reason'=>$this->rejection_reason,
            'rejected_by'=>$this->rejected_by,
            'rejected_by_name'=>User::resolveDisplayName($this->rejectedBy),
            'is_active'=>(bool)$this->is_active,
            'is_archived'=>(bool)$this->is_archived,
            'is_hot_deal'=>$this->is_hot_deal =='Yes' && $this->hot_deal_approved_by && $this->hot_deal_approved_at ? $this->is_hot_deal :'No',
            'sold_by'=>$this->sold_by,
            'property_type_id'=>$this->property_type_id,
            'developer_id'=>$this->project?->developer_id,
            'project_id'=>$this->project_id,
            'project_name'=>$this->project?->name,
            'occupancy_status'=>$this->occupancy_status,
             'reference_number'=>$this->reference_number,
            'status' => $this->status,
            'unit_number' => $canSeeUnitNumber ? $this->unit_number : null,
            'size_sqft' => $this->size_sqft,
            'size_sqmt' => $this->size_sqmt,
            'number_of_bedrooms' => $this->number_of_bedrooms,
            'number_of_bathrooms' => $this->number_of_bathrooms,
           
            'price' => $this->price,
            'furnished_status' => $this->furnished_status,
            'listing_status' => $this->listing_status,
            'completion_status' => $this->completion_status,
            'original_price' => $this->original_price,
            'selling_price' => $this->selling_price ?? $this->price,
            'payment_breakdown' => $this->payment_breakdown,
            'assignment_expense_lines' => $this->assignment_expense_lines,
            'has_payment_breakdown' => $this->hasPaymentBreakdown(),
            'noc_percentage' => $this->noc_percentage,
            'handover_date' => $this->handover_date?->format('Y-m-d'),
            'payment_plan' => $this->payment_plan,
            'can_edit_payment_breakdown' => $this->resolveCanEditPaymentBreakdown($request),
            
            // 'main_image' => $this->galleryImages->first()?->image_url,
            'main_image'=> $this->hero_image_path
                            ? route('image.watermark', ['path' => $this->hero_image_path])
                            : null,
            'total_images' => $this->galleryImages->count()+1,
            
            'property_type' => $this->propertyType?->name,
            'area' => $this->area? $this->area->title: $this->old_area?->title,
          'agent' => $this->whenLoaded('agent', function () {
                return [
                    'id' => $this->agent->id,
                    'name' => User::resolveDisplayName($this->agent),
                    'email' => $this->agent->email,
                'avatar' => $this->avatar ?  $this->avatar : null,
                ];
            }),
            'owner' => $this->whenLoaded('owner', fn () => $canSeeOwnerData ? new OwnerResource($this->owner) : null),
            'canShowOwner' => $canSeeOwnerData,
            'canShowUnitNumber' => $canSeeUnitNumber,
            'created_at' => $this->created_at?->format('M d, Y'),
                  'project' => $this->whenLoaded('project', function () {
                 $projectGalleryImages = $this->project->images->sortBy('sort_order')->values();
                    $projectMainImage = $projectGalleryImages->firstWhere('is_main', true);
                    $projectSecondImage = null;
                    
                    // If we have main image and at least 2 images total, get the second one
                    if ($projectGalleryImages->count() >= 2) {
                        if ($projectMainImage && $projectGalleryImages->count() >= 2) {
                            // Get the next image after main
                            $mainIndex = $projectGalleryImages->search(function($img) use ($projectMainImage) {
                                return $img->id === $projectMainImage->id;
                            });
                            if ($mainIndex !== false && isset($projectGalleryImages[$mainIndex + 1])) {
                                $projectSecondImage = $projectGalleryImages[$mainIndex + 1];
                            }
                        } else {
                            // If no main image, just take first two
                            $projectSecondImage = $projectGalleryImages[1] ?? null;
                        }
                    }
    
                return [
                    'id' => $this->project->id,
                    'title' => $this->project->title,
                    'name' => $this->project->title,
                    'about' => $this->project->about,
                    'area_id' => $this->project->area_id,
                 
                    'area' => $this->project->area ? [
                        'id' => $this->project->area->id,
                        'name' => $this->project->area->name,
                        'area_parents_title' => $this->project->area->area_parents_title,
                        'admin_fee_type' => $this->project->area->getAdminFeeType(),
                            'is_adgm' => $this->project->area->isAdgmArea(),
                            'all_names' => $this->project->area->getAllAreaNames(),
                    ] : null,
                    'project_id' => $this->project->id,
                     'features' => $this->project->features->map(function ($feature) {
                        return [
                            'id' => $feature->id,
                            'name' => $feature->name,
                            'img' => $feature->img ? asset('storage/' . $feature->img) : null,
                            'category' => $feature->category ?? null
                        ];
                    }),

                    'developer'=>$this->project->developer_id,
                    'developer_name'=>$this->project->developer?->name,
                     'developerData' => $this->project->developer ? [
                        'id' => $this->project->developer->id,
                        'name' => $this->project->developer->name,
                        'avatar' => $this->project->developer->avatar_path ? asset('storage/' . $this->project?->developer->avatar_path) : null,
                        'noc_fees_ready' => $this->project->developer?->noc_fees_ready,
                        'noc_fees_off_plan' => $this->project->developer?->noc_fees_off_plan,
                    ] : null,
                        // 'image' => $this->project->mainImage ? asset('storage/' . $this->project->mainImage->image_path) : null,
                    'image' => $projectMainImage ? asset('storage/' . $projectMainImage->image_path) : null,
                    'image2' => $projectSecondImage ? asset('storage/' . $projectSecondImage->image_path) : null,
                    'floor_plan_images' => FloorPlanImageResource::collection($this->project->floorPlanImages),
                    'gallery_images'=>$this->project->images->map(function ($image) {
                        return [
                            'id' => $image->id,
                            'image_url' => asset('storage/' . $image->image_path),
                            'is_main' => $image->is_main,
                            'sort_order' => $image->sort_order
                        ];
                    }),
                ];
            }),
        ];
    }
}