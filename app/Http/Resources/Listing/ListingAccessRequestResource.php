<?php

namespace App\Http\Resources\Listing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListingAccessRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
           $user = auth()->user();
// Calculate permissions based on user role and hierarchy
        $canManageAccessRequests = $user->canManageAccessRequests();
        if($this->request_type=='viewing'){
            \Log::info('viewing', [
                'listing_id' => $this->listing->id,
                'user_id' => $user->id,
                'can_manage' => $canManageAccessRequests,
                'is_owner' => $this->listing->isOwner($user),
                'is_handler' => $user->id == $this->handled_by,
            ]);
            $canRespond =$canManageAccessRequests || $this->listing->isOwner($user) || $user->id==$this->handled_by;
        }else{
        $canRespond = $canManageAccessRequests && ($this->listing->isOwnedBy($user) || $user->id==$this->handled_by);
        }
        $canConvert = $user->canConvertAccessRequest($this);
        return [
            'id' => $this->id,
            'reference_number'=>$this->reference_number,
            'status' => $this->status,
            'listing_id' => $this->listing_id,
            'request_type' => $this->request_type,
            'request_type_label' => $this->getRequestTypeLabel(),
            'reason' => $this->reason,
            'owner_response' => $this->owner_response,
            'responded_at' => $this->responded_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'converted_at' => $this->converted_at,
            'converted_by' => $this->convertedBy?$this->convertedBy->name:'oia',
            'conversion_notes' => $this->conversion_notes,
            'show_all_column'=>$user->hasRole('super_admin') || $user->hasRole('admin') || $user->hasRole('team_lead') || $user->hasRole('manager'),
             // Listing details
            'listing' => $this->whenLoaded('listing', function () {
                return [
                    'id' => $this->listing->id,
                    'title' => $this->listing->area?->name,
                    'unit_number' => $this->listing->unit_number,
                    'price' => $this->listing->price,
                    'size_sqft' => $this->listing->size_sqft,
                    'size_sqmt' => $this->listing->size_sqmt,
                    'agent' => collect(explode(' ', $this->listing?->agent?->name))
                        ->take(2)
                        ->implode(' '),
                      'agent_avatar' => $this->listing->agent && $this->listing->agent->avatar?asset('storage/'. $this->listing->agent->avatar):'',
                ];
            }),
            
            // Sales person who requested
            'requested_by' =>
                 [
                    'id' => $this->requestedBy?->id,
                    'name' => collect(explode(' ', $this->requestedBy?->name))
                        ->take(2)
                        ->implode(' '),
                    'email' => $this->requestedBy?->email,
                    'phone' => $this->requestedBy?->phone,
                    'avatar' => $this->requestedBy && $this->requestedBy->avatar ?  asset('storage/'. $this->requestedBy->avatar) : null,

                ]
            ,            
            // Owner details (with conditional contact info)
            'owner' => $this->whenLoaded('owner', function () {
                $baseInfo = [
                    'id' => $this->owner->id,
                    'name' => $this->owner->first_name . ' ' . $this->owner->last_name,
                ];
                
                // Only show contact info if access is approved
                if ($this->status === 'approved') {
                    $baseInfo['phone'] = $this->owner->phone_number;
                    $baseInfo['email'] = $this->owner->email;
                    $baseInfo['residency_status'] = $this->owner->residency_status;
                    $baseInfo['nationality'] = $this->owner->nationality;
                }
                
                return $baseInfo;
            }),
            
            // Additional computed fields
            'is_pending' => $this->status === 'pending',
            'is_in_progress' => $this->status === 'in_progress',
            'is_approved' => $this->status === 'approved',
            'is_rejected' => $this->status === 'rejected',
            'can_respond' => $this->status === 'pending' && auth()->check() 
                ? $this->listing->isOwner($user)
                : false,
                'permissions' => [
                'can_approve' => $canRespond && ($this->status === 'pending' || $this->status === 'in_progress'),
                'can_in_progress' => $canRespond && $this->status === 'pending',
                'can_reject' => $canRespond && ($this->status === 'pending' || $this->status === 'in_progress'),
                'can_convert' => $canConvert && ($this->status === 'approved' || $this->status === 'pending'),
                'can_cancel' => $this->requested_by === $user->id && $this->status === 'pending',
                'can_view_property' => true,
            ],
            'can_review'=>  $user && $this->requested_by  == $user->id,
            'user_id'=>$user->id,
            'request_by'=>$this->requested_by,
            'can_cancel'=>auth()->user()->id==$this->request_by,
            'formatted_date' => $this->viewing_date?->format('d M Y'),
            'formatted_time' => $this->viewing_time?->format('h:i A'),
            'full_datetime' => $this->viewing_date && $this->viewing_time 
                ? $this->viewing_date->format('Y-m-d') . ' ' . $this->viewing_time
                : null,
             'review' => $this->review,
            'reviewed_at' => $this->reviewed_at,
            'has_review' => !empty($this->review),
            'reviewed_by' => $this->reviewer ? [
                'id' => $this->reviewer->id,
                'name' =>  collect(explode(' ', $this->reviewer?->name))
                        ->take(2)
                        ->implode(' '),
                'avatar' => $this->reviewer->avatar
            ] : null,
            'handled_by' => $this->handledBy ? [
                'id' => $this->handledBy->id,
                'name' => collect(explode(' ', $this->handledBy?->name))
                        ->take(2)
                        ->implode(' '),
                'avatar' => $this->handledBy->avatar
                    ? asset('storage/' . $this->handledBy->avatar)
                    : null,
            ] : null,
            
            'is_delegated' => $this->handled_by !== $this->listing->agent_id,
            'cancellation_reason'=>$this->cancellation_reason
        ];
    }

    /**
     * Add additional meta data to the resource response.
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'version' => '1.0',
                'api_version' => 'v1',
            ],
        ];
    }
}