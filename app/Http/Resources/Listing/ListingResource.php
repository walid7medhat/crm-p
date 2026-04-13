<?php

namespace App\Http\Resources\Listing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\User;
class ListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = auth()->user();
    
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
         $canAssignAgent = false;

    if ($user) {
$roleAllowed = $user->hasAnyRole(['admin','super_admin','team_lead','manager']);
$allowedAgentIds = [];

        if ($roleAllowed) {
            if ($user->hasAnyRole(['super_admin','admin'])) {
                $canAssignAgent = true;
            } else {
              $allowedAgentIds = $user->getAllSubordinatesIds();

                if ($this->agent_id && in_array($this->agent_id, $allowedAgentIds)) {
                    $canAssignAgent = true;
                }
            }
        }
    }

        return [
            'id' => $this->id,
            'reference_number'=>$this->reference_number,
            'is_active' => (bool)$this->is_active,
            'is_archived' => (bool)$this->is_archived,
            'title' => $this->area?->name,
            'status' => $this->status, // draft, published, etc.
            'unit_number' => $this->unit_number,
            'size_sqft' => $this->size_sqft,
            'size_sqmt' => $this->size_sqmt,
            'number_of_bedrooms' => $this->number_of_bedrooms,
            'number_of_bathrooms' => $this->number_of_bathrooms,
            'price' => $this->price,
            'furnished_status' => $this->furnished_status,
            'ownership_type' => $this->ownership_type,
            'listing_status' => $this->listing_status,
            'completion_status' => $this->completion_status,
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
            'project' => $this->whenLoaded('project', function () {
                return [
                    'id' => $this->project->id,
                    'title' => $this->project->title,
                    'name' => $this->project->title,
                    'about' => $this->project->about,
                    'area_id' => $this->project->area_id,
                    'area' => $this->project->area ? [
                        'id' => $this->project->area->id,
                        'name' => $this->project->area->name,
                        'area_parents_title' => $this->project->area->area_parents_title
                    ] : null,
                    'project_id' => $this->project->id,
                    'features' => $this->project->features->pluck('name'),

                    'developer'=>$this->project->developer_id,
                     'developer_name'=>$this->project->developer?->name,
                        'image' => $this->project->mainImage ? asset('storage/' . $this->project->mainImage->image_path) : null,
                    'floor_plan_images' => FloorPlanImageResource::collection($this->project->floorPlanImages),
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
                    'name' => $this->convertedBy->name,
                ];
            }),
            'assignment_notes' => $this->assignment_notes,
            'assigned_at' => $this->assigned_at?->format('Y-m-d H:i:s'),
            'assigned_by' => $this->whenLoaded('assignedBy', function () {
                return [
                    'id' => $this->assignedBy->id,
                    'name' => $this->assignedBy->name,
                ];
            }),

            // User permissions
            'user_permissions' => [
                'can_edit' => $canEdit,
                'can_delete' => $canDelete,
                'is_owner' => $this->isOwner($user) || ($canAssignAgent && $user->hasRole('manager') && $user->listing_team == 1),
                   'can_assign_agent' => $canAssignAgent && $user->hasPermissionTo('listings-assign'), 
                 'showDocuments'=>$showDocuments,
                  'show_offers'=>$user->hasRole('super_admin'),
            ],
            'canShowOwner' => $user && ($user->hasRole('super_admin') || $this->agent_id == $user->id),

// $this->isOwner($user) || ($canAssignAgent && $user->hasRole('manager') && $user->listing_team == 1)
            'is_owner' => $this->isOwner($user) ,

            // Gallery Images
            'gallery_images' => $this->galleryImages->map(function ($galleryImage) {
                return [
                    'id' => $galleryImage->id,
                    'name' => $galleryImage->name,
                    'image_url' => $galleryImage->image_path ? asset('storage/' . $galleryImage->image_path) : null,
                    'order' => $galleryImage->order,
                    'created_at' => $galleryImage->created_at,
                ];
            }),
            'main_image'=>$this->hero_image_path ? asset('storage/' . $this->hero_image_path) : null,

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
                    'name' => $this->agent->name,
                    'email' => $this->agent->email,
                    'avatar' => $this->agent->avatar ? asset('storage/'. $this->agent->avatar)   : null, 
                ];
            }),
            
            'owner' => $this->whenLoaded('owner', new OwnerResource($this->owner)),
            
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
                    'name' => $this->addedBy->name,
                ];
            }),
            'drive_link' => $this->drive_link,
            'is_hot_deal'=>$this->is_hot_deal =='Yes' && $this->hot_deal_approved_by && $this->hot_deal_approved_at ? $this->is_hot_deal :'No',
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
             'additional_features' => [
                'maid' => (bool) ($this->additional_features['maid'] ?? false),
                'storage' => (bool) ($this->additional_features['storage'] ?? false),
                'study' => (bool) ($this->additional_features['study'] ?? false),
                'store' => (bool) ($this->additional_features['store'] ?? false),
                'laundry' => (bool) ($this->additional_features['laundry'] ?? false),
                'driver' => (bool) ($this->additional_features['driver'] ?? false),
            ],
        ];
    }
}