<?php

namespace App\Http\Resources\Listing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\User;
use App\Models\ListingAccessRequest;
use App\Helpers\ImageHelper;
class ListingResource extends JsonResource
{
    /**
     * Central list of listing amenities / features (key => human label).
     * Keep this in sync with resources/js/config/listingFeatures.js.
     * Used to surface every known feature on the API as a boolean map, so the
     * frontend (PropertyDetails / search / forms) can iterate over them without
     * hardcoding the keys.
     */
public const FEATURE_LABELS = [

    // Layout / Position
    'corner_unit'        => 'Corner Unit',
    'end_unit'           => 'End Unit',
    'mid_unit'           => 'Mid Unit',
    'double_row'         => 'Double Row',
    'single_row'         => 'Single Row',

    // Floor
    'mid_floor'          => 'Mid Floor',
    'high_floor'         => 'High Floor',
    'low_floor'          => 'Low Floor',
    'ground_floor'       => 'Ground Floor',

    // Views
    'sea_view'           => 'Sea View',
    'partial_sea_view'   => 'Partial Sea View',
    'canal_view'         => 'Canal View',
    'partial_canal_view' => 'Partial Canal View',
    'museum_view'        => 'Museum View',
    'park_view'          => 'Park View',
    'partial_park_view'  => 'Partial Park View',
    'city_view'          => 'City View',
    'community_view'     => 'Community View',
    'road_view'          => 'Road View',
    'mall_view'          => 'Mall View',
    'mangrove_view'      => 'Mangrove View',
    'university_view'    => 'University View',
    'pool_view'          => 'Pool View',
    'fountain_view'      => 'Fountain View',

    // Rooms
    'maid_room'          => 'Maid Room',
    'guest_room'         => 'Guest Room',
    'laundry_room'       => 'Laundry Room',
    'study_room'         => 'Study Room',
    'utility_room'       => 'Utility Room',
    'storage_room'       => 'Storage Room',
    'powder_room'        => 'Powder Room',
    'driver_room'        => 'Driver Room',
    'majles'             => 'Majles',
    'dressing_room'      => 'Dressing Room',

    // Outdoor / Spaces
    'balcony'            => 'Balcony',
    'terrace'            => 'Terrace',
    'basement'           => 'Basement',
    'pod'                => 'Pod',

    // Kitchen
    'open_kitchen'           => 'Open Kitchen',
    'semi_closed_kitchen'    => 'Semi Closed Kitchen',
    'closed_kitchen'         => 'Closed Kitchen',
    'pantry'                 => 'Pantry',
    'kitchen_appliances'     => 'Kitchen Appliances',

    // Furnishing
    'furnished'          => 'Furnished',
    'fully_furnished'    => 'Fully Furnished',
    'semi_furnished'     => 'Semi Furnished',

    // Extras
    'private_pool'       => 'Private Pool',
    'private_gym'        => 'Private Gym',
];

    /** Per-request memo: "{listingId}:{userId}" => list of approved request_type strings. */
    protected static array $accessRequestCache = [];

    /** True if the auth user has an approved access request of the given type for this listing. */
    protected function hasApprovedAccess(?int $userId, string $requestType): bool
    {
        if (! $userId) return false;
        $key = $this->id . ':' . $userId;
        if (! array_key_exists($key, self::$accessRequestCache)) {
            // Prefer the eager-loaded relation when available, fall back to a direct query.
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
    {
        $user = auth()->user();
    $isTodayMain = $this->created_at?->isToday();

        // Visibility policy for sensitive fields (unit_number, owner).
        // Privileged = listing agent | super_admin | manager with listing_team=1 | (legacy) user 30.
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

        // Manual permission check
        $canEdit = false;
        $canDelete = false;
        $showDocuments=false;
        
        if ($user) {
            $canEdit = $user->id == $this->added_by || 
                    ($this->agent_id && $user->id == $this->agent_id) || $user->hasRole('super_admin') || $user->canEditListings($this->agent_id);
            $canDelete = auth()->user()->hasRole('super_admin');
            $showDocuments= $user->id == $this->added_by || 
                    ($this->agent_id && $user->id == $this->agent_id) || $user->hasRole('super_admin');
        }
         $canAssignAgent = false;$canViewUpdate=false;

    if ($user) {
$roleAllowed = $user->hasAnyRole(['admin','super_admin','team_lead','manager']);
$allowedAgentIds = [];

        if ($roleAllowed) {
            if ($user->hasAnyRole(['super_admin','admin'])) {
                $canAssignAgent = true;
                $canViewUpdate=true;
            }elseif ($user->hasAnyRole(['manager']) && $user->listing_team==1) {
                $canAssignAgent = true;
                $canViewUpdate=true;
            } else {
              $allowedAgentIds = $user->getAllSubordinatesIds();

                if ($this->agent_id && in_array($this->agent_id, $allowedAgentIds)) {
                    $canAssignAgent = true;
                    $canViewUpdate=true;
                }
            }
        }
    }

      $canViewInternalUpdates = $user && ( $canViewUpdate ||
        $this->agent_id == $user->id ||
        $user->id == 30
    );
        return [
            'id' => $this->id,
            'reference_number'=>$this->reference_number,
            'is_active' => (bool)$this->is_active,
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
            'rejected_at'=>$this->rejected_at,
                'is_archived' => (bool)$this->is_archived,
            'title' => $this->area?->name,
            'status' => $this->status, // draft, published, etc.
            'unit_number' => $canSeeUnitNumber ? $this->unit_number : null,
            'size_sqft' => $this->size_sqft,
            'size_sqmt' => $this->size_sqmt,
            'number_of_bedrooms' => $this->number_of_bedrooms,
            'number_of_bathrooms' => $this->number_of_bathrooms,
            'price' => $this->price,
            'furnished_status' => $this->furnished_status,
            'ownership_type' => $this->ownership_type,
            'listing_status' => $this->listing_status,
            'completion_status' => $this->completion_status,
           'noc_type' => $this->completion_status === 'Completed' ? 'Ready' : 'Off Plan',
            'layout_type_id' => $this->layout_type_id,
            'layout_type' => $this->layout_type?->name,
            'unit_view_id' => $this->unit_view_id,
            'unit_view' => $this->unit_view?->name,
            'mortgage_status' => $this->mortgage_status,
            'mortgage_amount' => $this->mortgage_amount,
            'mortgage_comment' => $this->mortgage_comment,
            'occupancy_status' => $this->occupancy_status,
            'occupancy_comment' => $this->occupancy_comment,
            'hero_image' => $this->hero_image_path ? asset('storage/' . $this->hero_image_path) : null,
            'spa_document' => $this->spa_document_path ? asset('storage/' . $this->spa_document_path) : null,
            'desk_document' => $this->desk_document_path ? asset('storage/' . $this->desk_document_path) : null,
            'other_document' => $this->other_document_path ? asset('storage/' . $this->other_document_path) : null,
            'additional_documents' => $this->whenLoaded('additionalDocuments', function () {
                return $this->additionalDocuments->map(fn ($doc) => [
                    'id' => $doc->id,
                    'url' => asset('storage/' . $doc->path),
                    'name' => $doc->original_name ?? basename($doc->path),
                ]);
            }, []),
            'comment' => $this->comment,
            'my_listing' => $this->added_by == auth()->id(),
            'rent_expiry_date' => $this->rent_expiry_date,
            'rent_amount' => $this->rent_amount,
            'sold_by'=>$this->sold_by,
            'rented_status' => $this->rented_status,
            'rented_until' => $this->rented_until,
             'payment_plan_json' => $this->getRawOriginal('payment_plan'),
            'payment_plan' => $this->payment_plan,
            'original_price' => $this->original_price,
            'selling_price' => $this->selling_price ?? $this->price,
            'payment_breakdown' => $this->payment_breakdown,
            'assignment_expense_lines' => $this->assignment_expense_lines,
            'has_payment_breakdown' => $this->hasPaymentBreakdown(),
            'noc_percentage' => $this->noc_percentage,
            'noc_fixed_amount'=>$this->noc_fixed_amount,
            'handover_date' => $this->handover_date?->format('Y-m-d'),
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
            
            'project_id' => $this->project_id,

            // Floor Plans
            'floor_plans' => $this->floorPlans->map(function ($floorPlan) {
                return [
                    'id' => $floorPlan->id,
                    'name' => $floorPlan->name,
                    'image_url' => $floorPlan->image_path ? asset('storage/' . $floorPlan->image_path) : null,
                    'order' => $floorPlan->order,
                      'created_at' => $floorPlan->created_at,
                    'project_id' => $floorPlan->project_floor_plan_id
                ];
            }),
            'floor_plans_source' => $this->floor_plans_source ?? [
                'from_project' => 0,
                'uploaded' => 0,
                'total' => 0,
                'selected_project_plans' => []
            ],
            'converted_at' => $this->converted_at?->format('Y-m-d H:i:s'),
            'converted_by' => $this->whenLoaded('convertedBy', function () {
                return [
                    'id' => $this->convertedBy->id,
                    'name' => User::resolveDisplayName($this->convertedBy),
                ];
            }),
            'assignment_notes' => $this->assignment_notes,
            'assigned_at' => $this->assigned_at?->format('Y-m-d H:i:s'),
            'assigned_by' => $this->whenLoaded('assignedBy', function () {
                return [
                    'id' => $this->assignedBy->id,
                    'name' => User::resolveDisplayName($this->assignedBy),
                ];
            }),

            // User permissions
            'user_permissions' => [
                'can_edit' => $canEdit,
                'can_delete' => $canDelete,
                'is_owner' => $this->isOwner($user) || ($canAssignAgent && $user->hasRole('manager') && $user->listing_team == 1),
                   'can_assign_agent' => $canAssignAgent && $user->hasPermissionTo('listings-assign'), 
                 'showDocuments'=>$showDocuments,
                  'show_offers'=>$user->hasRole('super_admin')  ,
                  'genertae_offers'=>true ,
            ],
            'canShowOwner' => $canSeeOwnerData,
            'canShowUnitNumber' => $canSeeUnitNumber,

// $this->isOwner($user) || ($canAssignAgent && $user->hasRole('manager') && $user->listing_team == 1)
            'is_owner' =>$this->isOwner($user) || ( $user->hasRole('manager') && $user->listing_team == 1) || $user->id==30,

            // Gallery Images
          'gallery_images' => $this->galleryImages->map(function ($galleryImage) {
            
                $isToday = $galleryImage->created_at?->isToday();
            
                return [
                    'id' => $galleryImage->id,
                    'name' => $galleryImage->name,
            
                    // الأصلية دايمًا
                    'image_url' => $galleryImage->image_path
                        ? asset('storage/' . $galleryImage->image_path)
                        : null,
            
                    // watermark condition
                    // 'image_url_final' => $galleryImage->image_path
                    //     ? ($isToday
                    //         ? ImageHelper::getWatermarkedUrl($galleryImage->image_path)
                    //         : asset('storage/' . $galleryImage->image_path))
                    //     : null,
                        'image_url_final' => $galleryImage->image_path
                            ? route('image.watermark', ['path' => $galleryImage->image_path])
                            : null,
            
                    'order' => $galleryImage->order,
                    'created_at' => $galleryImage->created_at,
                ];
            }),
            'main_image'=>$this->hero_image_path ? asset('storage/' . $this->hero_image_path) : null,
            // 'main_image' =>$this->hero_image_path
            //                     ? route('image.watermark', ['path' => $this->hero_image_path])
            //                     : null,
            // Relationships
            'property_type' => $this->whenLoaded('propertyType', function () {
                return [
                    'id' => $this->propertyType->id,
                    'name' => $this->propertyType->name,
                ];
            }),
            
            'area' => $this->whenLoaded('area', function () {
                return [
                    'id' => $this->area->id,
                    'name' => $this->area->name,
                    'type' => $this->area->type,
                    'area_title' => $this->area->area_title,
                    'parent' => $this->area->parent,
                    'children' => $this->area->child,
                    'hierarchy' => $this->area->full_hierarchy,
                    'title'=>$this->area->title,
                ];
            }),
            'old_area'=>$this->old_area?->area_title,
            
            'agent' => $this->whenLoaded('agent', function () {
                return [
                    'id' => $this->agent->id,
                    'name' => User::resolveDisplayName($this->agent),
                    'email' => $this->agent->email,
                    'avatar' => $this->agent->avatar ? asset('storage/'. $this->agent->avatar)   : null,
                ];
            }),

            // Vacation delegation: while the original agent is on vacation, agent_id points to
            // the delegate and vacation_holder_id holds the original. Surface both so the UI can
            // render a "covering for …" hint and admins can see the real owner.
            'vacation_holder_id' => $this->vacation_holder_id,
            'is_delegated_for_vacation' => (bool) $this->vacation_holder_id,
            'vacation_holder' => $this->whenLoaded('vacationHolder', function () {
                if (!$this->vacationHolder) return null;
                return [
                    'id' => $this->vacationHolder->id,
                    'name' => User::resolveDisplayName($this->vacationHolder),
                    'email' => $this->vacationHolder->email,
                    'avatar' => $this->vacationHolder->avatar ? asset('storage/' . $this->vacationHolder->avatar) : null,
                ];
            }),
            
            'owner' => $this->whenLoaded('owner', fn () => $canSeeOwnerData ? new OwnerResource($this->owner) : null),
            
            'developer' => $this->whenLoaded('developer', function () {
                return [
                    'id' => $this->developer->id,
                    'name' => $this->developer->name,
                    'email' => $this->developer->email,
                ];
            }),
            
            'added_by' => $this->whenLoaded('addedBy', function () {
                return [
                    'id' => $this->addedBy->id,
                    'name' => User::resolveDisplayName($this->addedBy),
                ];
            }),
            'drive_link' => $this->drive_link,
            'is_hot_deal'=>$this->is_hot_deal =='Yes' && $this->hot_deal_approved_by && $this->hot_deal_approved_at ? $this->is_hot_deal :'No',
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            // Boolean map for every known feature; the frontend iterates over keys.
            'additional_features' => collect(self::FEATURE_LABELS)
                ->mapWithKeys(function ($label, $key) {
                    return [
                        $key => (bool) ($this->additional_features[$key] ?? false),
                    ];
                }),
            'additional_features_labels' => self::FEATURE_LABELS,
            'sold_by_company_name' => $this->sold_by_company_name,
            'sold_by_agent_name' => $this->sold_by_agent_name,
            'sold_by_agent_phone' => $this->sold_by_agent_phone,
            'rented_by_agent_email' => $this->rented_by_agent_email,
            'rented_by_company_name' => $this->rented_by_company_name,
            'rented_by_agent_name' => $this->rented_by_agent_name,
            'rented_by_agent_phone' => $this->rented_by_agent_phone,
            'rented_by_agent_email' => $this->rented_by_agent_email,

               'internal_updates' => $this->when($canViewInternalUpdates, function () {
                    return $this->internalUpdates->map(function ($update) {
                        return [
                            'id' => $update->id,
                            'content' => $update->content,
                            'user' => [
                                'id' => $update->user->id,
                                'name' => User::resolveDisplayName($update->user),
                                'avatar' => $update->user->avatar ? asset('storage/' . $update->user->avatar) : null,
                            ],
                            'created_at' => $update->created_at->format('Y-m-d H:i:s'),
                            'created_at_human' => $update->created_at->diffForHumans(),
                        ];
                    });
                }, []),
                
                'can_add_internal_update' => $user && (
                    $this->agent_id == $user->id ||
                    $user->hasRole('super_admin') ||
                    $user->hasRole('admin')
                ),
        ];
    }
}