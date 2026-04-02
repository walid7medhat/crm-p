<?php

namespace App\Http\Controllers\Api\Listing;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Http\Resources\Listing\ListingAccessRequestResource;
use App\Models\Listing;
use App\Models\ListingAccessRequest;
use App\Models\Owner;
use App\Notifications\ListingAccessRequestNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Notifications\RequestCancelledNotification;
use Notification;
use Carbon\Carbon;
use App\Events\AccessRequestStatusUpdated;
use App\Models\User;
class ListingAccessRequestController extends Controller
{
    /**
     * Request access to owner data or phone number
     */
   public function store(Request $request, Listing $listing): JsonResponse
{
    try {
        $user = Auth::user();
        
        $validated = $request->validate([
            'request_type' => 'required|in:owner_data,unit_number,viewing',
            'reason' => 'required|string|max:1000',
            'viewing_date' => 'nullable|date|after:today',
            'viewing_time' => 'nullable|date_format:H:i',
           
        ]);

        if ($request->request_type === 'viewing') {
            if (!$request->viewing_date || !$request->viewing_time) {
                return ApiResponse::error('Viewing date and time are required for viewing requests', 422);
            }
            
            $conflictingViewing = ListingAccessRequest::where([
                'listing_id' => $listing->id,
                'request_type' => 'viewing',
                'status' => 'pending'
            ])->where(function($query) use ($request) {
                $query->where('viewing_date', $request->viewing_date)
                      ->where('viewing_time', $request->viewing_time);
            })->exists();
            
            if ($conflictingViewing) {
                return ApiResponse::error('A viewing request already exists for this time slot', 422);
            }
        }

        // Check if already has an active request of the same type
        $existingRequest = ListingAccessRequest::where([
            'listing_id' => $listing->id,
            'requested_by' => $user->id,
            'request_type' => $request->request_type,
            'status' => 'pending'
        ])->exists();

        if ($existingRequest) {
            return ApiResponse::error('You already have a pending request of this type for this listing', 422);
        }

        DB::beginTransaction();
$originalAgent = $listing->agent;
$activeAgent = $originalAgent->activeAgent();
        $accessRequest = ListingAccessRequest::create([
            'listing_id' => $listing->id,
            'requested_by' => $user->id,
            'owner_id' => $listing->owner_id,
            'request_type' => $request->request_type,
            'reason' => $request->reason,
            'status' => 'pending',
            'viewing_date' => $request->viewing_date,
            'viewing_time' => $request->viewing_time,
          'handled_by' => $activeAgent->id,
        ]);

        // Send notification to the listing agent
        try {
            // $listing->agent->notify(new ListingAccessRequestNotification($accessRequest, 'request'));
            $activeAgent->notify(
                new ListingAccessRequestNotification($accessRequest, 'request')
            );
            broadcast(new AccessRequestStatusUpdated($accessRequest, 'requested'));
        } catch (\Exception $e) {
            \Log::error('Notification failed: ' . $e->getMessage());
        }

        DB::commit();

        $message = match($request->request_type) {
            'viewing' => 'Viewing request submitted successfully!',
            default => ucfirst(str_replace('_', ' ', $request->request_type)) . ' access request submitted successfully.',
        };

        return ApiResponse::success(
            new ListingAccessRequestResource($accessRequest),
            $message . ' Notification sent to agent.',
            201
        );

    } catch (\Exception $e) {
        DB::rollBack();
        return ApiResponse::error('Failed to submit request: ' . $e->getMessage());
    }
}
/**
 * Update viewing time for a request
 */
public function updateViewingTime(Request $request, ListingAccessRequest $accessRequest): JsonResponse
{
    try {
        $user = Auth::user();
        
        // Check permissions
  if ($user->id !== $accessRequest->listing->added_by || $user->id !== $accessRequest->listing->agent_id ||  $user->id !== $accessRequest->handled_by ) {
            return ApiResponse::error('Access denied', 403);
        }
        
        $request->validate([
            'viewing_date' => 'nullable|date|after:today',
            'viewing_time' => 'nullable|date_format:H:i',
        ]);
        
       
        DB::beginTransaction();
        
        $oldTime = [
            'date' => $accessRequest->viewing_date,
            'time' => $accessRequest->viewing_time
        ];
        
        $accessRequest->update([
            'viewing_date' => $request->viewing_date,
            'viewing_time' => $request->viewing_time,
            'updated_at' => now()
        ]);
        
        // Send notification to requester about time change
        try {
            $accessRequest->requestedBy->notify(new ListingAccessRequestNotification($accessRequest, 'time_updated', [
                'old_date' => $oldTime['date'],
                'old_time' => $oldTime['time'],
                'new_date' => $request->viewing_date,
                'new_time' => $request->viewing_time
            ]));
        } catch (\Exception $e) {
            \Log::error('Time update notification failed: ' . $e->getMessage());
        }
        
        DB::commit();
        
        return ApiResponse::success(
            new ListingAccessRequestResource($accessRequest),
            'Viewing time updated successfully'
        );
        
    } catch (\Exception $e) {
        DB::rollBack();
        return ApiResponse::error('Failed to update viewing time: ' . $e->getMessage());
    }
}
    /**
     * Check if user has access to specific owner data
     */
    public function checkAccess(Listing $listing): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $hasFullAccess = ListingAccessRequest::where([
                'listing_id' => $listing->id,
                'requested_by' => $user->id,
                'request_type' => 'owner_data',
                'status' => 'approved'
            ])->exists();

            $hasPhoneAccess = ListingAccessRequest::where([
                'listing_id' => $listing->id,
                'requested_by' => $user->id,
                'request_type' => 'unit_number',
                'status' => 'approved'
            ])->exists();

            return ApiResponse::success([
                'has_full_access' => $hasFullAccess,
                'has_phone_access' => $hasPhoneAccess,
                'listing_id' => $listing->id,
                'access_details' => [
                    'owner_data' => $hasFullAccess ? 'full' : ($hasPhoneAccess ? 'limited' : 'none'),
                    'owner_phone' => $hasPhoneAccess || $hasFullAccess
                ]
            ], 'Access check completed');

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to check access: ' . $e->getMessage());
        }
    }

    /**
     * Get owner data based on access level
     */
    public function getOwnerData(Listing $listing): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Check access permissions
            $hasFullAccess = ListingAccessRequest::where([
                'listing_id' => $listing->id,
                'requested_by' => $user->id,
                'request_type' => 'owner_data',
                'status' => 'approved'
            ])->exists();

            $hasPhoneAccess = ListingAccessRequest::where([
                'listing_id' => $listing->id,
                'requested_by' => $user->id,
                'request_type' => 'unit_number',
                'status' => 'approved'
            ])->exists();

            if (!$hasFullAccess && !$hasPhoneAccess) {
                return ApiResponse::error('You do not have access to owner data', 403);
            }

            $owner = $listing->owner;
            $data = [
                'id' => $owner->id,
                'name' => $owner->first_name . ' ' . $owner->last_name,
            ];

            // Include phone if has phone access or full access
            if ($hasPhoneAccess || $hasFullAccess) {
                $data['phone'] = $owner->phone_number;
            }

            // Include full data only if has full access
            if ($hasFullAccess) {
                $data['email'] = $owner->email;
                $data['residency_status'] = $owner->residency_status;
                $data['nationality'] = $owner->nationality;
                $data['address'] = $owner->address;
                // Add any other owner fields you want to include
            }

            return ApiResponse::success(
                $data,
                'Owner data retrieved successfully'
            );

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve owner data: ' . $e->getMessage());
        }
    }

    private function getRequestTypeLabel(string $type): string
    {
        return match($type) {
            'owner_data' => 'owner data',
            'unit_number' => 'unit number',
            default => 'data'
        };
    }

  /**
 * Owner responds to access request
 */
public function respond(Request $request, ListingAccessRequest $accessRequest): JsonResponse
{
    try {
        $user = Auth::user();
        
        // Check if user is the one who added the listing (agent/owner)
        // if ($user->id !== $accessRequest->listing->added_by || $user->id !== $accessRequest->listing->agent_id) {
        //     return ApiResponse::error('Access denied', 403);
        // }
  if (!$user->canRespondToAccessRequest($accessRequest)) {
                return ApiResponse::error('Access denied', 403);
            }

        $request->validate([
            'status' => 'required|in:approved,rejected,in_progress',
            'response' => 'nullable|string|max:1000'
        ]);

        DB::beginTransaction();


            if ($accessRequest && ($accessRequest->status != 'pending'  && $accessRequest->status != 'in_progress')) {
                return response()->json([
                    'status' => false,
                    'message' => 'Request already processed'
                ], 404);
            }
            $oldStatus=$accessRequest->status;
        $accessRequest->update([
            'status' => $request->status,
            'owner_response' => $request->response,
            'responded_at' => now()
        ]);

        // Send notification to sales person
        try {
            $accessRequest->requestedBy->notify(
                new ListingAccessRequestNotification($accessRequest, $request->status)
            );
        } catch (\Exception $e) {
            \Log::error('Response notification failed: ' . $e->getMessage());
        }
  if ($oldStatus !== $request->status) {
                broadcast(new AccessRequestStatusUpdated($accessRequest, 'responded'));
            }

        DB::commit();

        return ApiResponse::success(
            new ListingAccessRequestResource($accessRequest),
            "Access request {$request->status} successfully"
        );

    } catch (\Exception $e) {
        DB::rollBack();
        return ApiResponse::error('Failed to respond to access request: ' . $e->getMessage());
    }
}

/**
 * Get my access requests 
 */
    public function myOrders(): JsonResponse
    {
        try {
            $user = Auth::user();
            $currentUser = $user;
            
            $user_hierarchy = User::where(function($q) use ($currentUser) {
                $q->where('id', $currentUser->id)
                ->orWhere('parent_id', $currentUser->id)
                ->orWhereHas('parent', function($parentQuery) use ($currentUser) {
                    $parentQuery->where('parent_id', $currentUser->id);
                });
            })->pluck('id')->toArray();

            $requests = ListingAccessRequest::with(['listing', 'requestedBy','convertedBy'])
            
                ->when(!($user->hasRole('admin') || $user->hasRole('super_admin')), function($q) use ($user_hierarchy) {
                    $q->whereIn('requested_by', $user_hierarchy);
                })
                ->orderBy('created_at', 'desc')
                ->get();

            return ApiResponse::success(
                ListingAccessRequestResource::collection($requests),
                'My access requests retrieved successfully'
            );

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve my access requests: ' . $e->getMessage());
        }
    }

    /**
     * Get requests for my listings 
     */
    public function myRequests(): JsonResponse
    {
        try {
            $user = Auth::user();
            $currentUser = $user;
            
            $user_hierarchy = User::where(function($q) use ($currentUser) {
                $q->where('id', $currentUser->id)
                ->orWhere('parent_id', $currentUser->id)
                ->orWhereHas('parent', function($parentQuery) use ($currentUser) {
                    $parentQuery->where('parent_id', $currentUser->id);
                });
            })->pluck('id')->toArray();

            $requests = ListingAccessRequest::with(['listing', 'requestedBy','convertedBy','handledBy'])
                        ->where(function ($mainQuery) use ($user, $user_hierarchy) {
                    
                            $mainQuery->whereHas('listing', function ($query) use ($user, $user_hierarchy) {
                    
                                if ($user->hasRole('super_admin') || $user->hasRole('admin')) {
                                    return;
                                }
                    
                                $query->whereIn('agent_id', $user_hierarchy);
                            })
                    
                            ->orWhere('handled_by', $user->id);
                    
                        })
                        ->orderBy('created_at', 'desc')
                        ->get();

            return ApiResponse::success(
                ListingAccessRequestResource::collection($requests),
                'My access orders retrieved successfully'
            );

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve my access orders: ' . $e->getMessage());
        }
    }

  public function getStatus($listingId): JsonResponse
{
    try {
        $user = Auth::user();
        $listing = Listing::find($listingId);
        
        if (!$listing) {
            return response()->json([
                'status' => false,
                'message' => 'Listing not found'
            ], 404);
        }
        
        // Get requests by type
        $unitNumberRequest = ListingAccessRequest::where([
            'requested_by' => $user->id,
            'listing_id' => $listing->id,
            'request_type' => 'unit_number'
        ])->latest()->first(); 
        
        $ownerInfoRequest = ListingAccessRequest::where([
            'requested_by' => $user->id,
            'listing_id' => $listing->id, 
            'request_type' => 'owner_data'
        ])->latest()->first();
        
        $viewingRequest = ListingAccessRequest::where([
            'requested_by' => $user->id,
            'listing_id' => $listing->id, 
            'request_type' => 'viewing'
        ])->latest()->first();
        if($viewingRequest){
           $viewingRequest=  $this->canRequestAgain($viewingRequest)?null:$viewingRequest;
        }
        
        $responseData = [
            'unit_number_requested' => !is_null($unitNumberRequest),
            'unit_number_status' => $unitNumberRequest->status ?? null,
            'unit_number_requested_at' => $unitNumberRequest->created_at ?? null,
            
            'owner_info_requested' => !is_null($ownerInfoRequest),
            'owner_info_status' => $ownerInfoRequest->status ?? null,
            'owner_info_requested_at' => $ownerInfoRequest->created_at ?? null,
            
            'viewing_requested' => !is_null($viewingRequest),
            'viewing_status' => $viewingRequest->status ?? null,
            'viewing_requested_at' => $viewingRequest->created_at ?? null,
            'viewing_details' => $viewingRequest ? [
                'date' => $viewingRequest->viewing_date,
                'time' => $viewingRequest->viewing_time,
                'type' => $viewingRequest->viewing_type,
                'notes' => $viewingRequest->viewing_notes,
            ] : null,
            
            'can_request_unit_number' => $this->canRequestAgain($unitNumberRequest),
            'can_request_owner_info' => $this->canRequestAgain($ownerInfoRequest),
            'can_request_viewing' => $this->canRequestAgain($viewingRequest),
        ];

        return response()->json([
            'status' => true,
            'data' => $responseData
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to fetch request status: ' . $e->getMessage()
        ], 500);
    }
}


    private function canRequestAgain($request)
    {
        if (!$request) {
            return true;
        }

        if ($request->status === 'pending' || $request->status === 'approved') {
            if($request->status === 'approved' && $request->request_type=='viewing' && $request->review != null){
                return true;
            }
            return false;
        }

        if ($request->status === 'cancelled') {
            // if ($request->cancelled_at) {
            //     $cancelledAt = Carbon::parse($request->cancelled_at);
            //     if ($cancelledAt->gte(now()->subHours(24))) {
            //         return false;
            //     }
            // }
            return true;
        }

        if ($request->status === 'rejected') {
            return true;
        }

        return true;
    }

     public function cancelRequest(Request $request, $listingId)
    {
        try {
            $user = Auth::user();
            
            // Validate request
            $request->validate([
                'request_type' => 'required|in:unit_number,owner_data,viewing'
            ]);

            // Find the pending request
            $accessRequest = ListingAccessRequest::where('listing_id', $listingId)
                ->where('requested_by', $user->id)
                ->where('request_type', $request->request_type)
                ->whereIn('status', ['pending','in_progress'])
                ->first();

            if (!$accessRequest) {
                return response()->json([
                    'status' => false,
                    'message' => 'Request not found or already processed'
                ], 404);
            }
              broadcast(new AccessRequestStatusUpdated($accessRequest, 'cancelled'));


            // Get property and owner details
            $property = Listing::with(['owner', 'agent'])->find($listingId);
            
            if (!$property) {
                return response()->json([
                    'status' => false,
                    'message' => 'Property not found'
                ], 404);
            }

            // Update status to cancelled
            $accessRequest->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancellation_reason'=>$request->cancellation_reason
            ]);

            // Send notifications
            $this->sendCancellationNotifications($accessRequest, $property, $user);

            return response()->json([
                'status' => true,
                'message' => 'Request cancelled successfully',
                'data' => $accessRequest
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to cancel request: ' . $e->getMessage()
            ], 500);
        }
    }

    private function sendCancellationNotifications($accessRequest, $property, $cancelledByUser)
    {
        try {
            $requestTypeText = $accessRequest->request_type === 'unit_number' ? 'Unit Number' : 'Owner Information';
            
            // Notification data
            $notificationData = [
                'request_id' => $accessRequest->id,
                'property_id' => $property->id,
                'property_title' => $property->title,
                'request_type' => $accessRequest->request_type,
                'request_type_text' => $requestTypeText,
                'cancelled_by_name' => $cancelledByUser->name,
                'cancelled_by_id' => $cancelledByUser->id,
                'cancelled_at' => now(),
            ];

            // Get users to notify (property owner, agent, and admins)
            $usersToNotify = collect();

           
            // Notify agent if exists
            if ($property->agent) {
                $usersToNotify->push($property->agent);
            }


            // Remove duplicates and the user who cancelled the request
            $usersToNotify = $usersToNotify->unique('id')
                ->reject(function ($user) use ($cancelledByUser) {
                    return $user->id === $cancelledByUser->id;
                });

            // Send notifications
            if ($usersToNotify->isNotEmpty()) {
                Notification::send($usersToNotify, new RequestCancelledNotification($notificationData));
            }

            // Also send to the user who cancelled (optional - for confirmation)
            $cancelledByUser->notify(new RequestCancelledNotification($notificationData));

        } catch (\Exception $e) {
            \Log::error('Failed to send cancellation notifications: ' . $e->getMessage());
        }
    }
    /**
 * Mark request as converted
 */
public function markAsConverted(Request $request, ListingAccessRequest $accessRequest): JsonResponse
{
    try {
        $user = Auth::user();

         if (!$user->canConvertAccessRequest($accessRequest)) {
                return ApiResponse::error('Access denied', 403);
            }
        // if (!($user->id !== $accessRequest->requested_by || $user->id !== $accessRequest->listing->added_by || $user->id !== $accessRequest->listing->agent_id || $user->hasRole('super_admin'))) {
        //     return ApiResponse::error('Access denied', 403);
        // }

        // Only approved requests can be marked as converted
        if ($accessRequest->status !== 'approved' && !($user->id == $accessRequest->listing->added_by && $accessRequest->status == 'pending' )) {
            return ApiResponse::error('Only approved requests can be marked as converted', 422);
        }

        $request->validate([
            'conversion_notes' => 'nullable|string|max:1000'
        ]);

        DB::beginTransaction();

        $accessRequest->update([
            'status' => 'converted',
            'converted_at' => now(),
            'converted_by' => $user->id,
            'conversion_notes' => $request->conversion_notes
        ]);
      $listing= $accessRequest->listing;
      $listing->update([
        'status'=>'converted'
      ]);
        // Send notification to listing owner/agent
        try {
            $accessRequest->listing->agent->notify(
                new ListingAccessRequestNotification($accessRequest, 'converted')
            );
            broadcast(new AccessRequestStatusUpdated($accessRequest, 'converted'));
        } catch (\Exception $e) {
            \Log::error('Conversion notification failed: ' . $e->getMessage());
        }

        DB::commit();

        return ApiResponse::success(
            new ListingAccessRequestResource($accessRequest),
            "Access request marked as converted successfully"
        );

    } catch (\Exception $e) {
        DB::rollBack();
        return ApiResponse::error('Failed to mark request as converted: ' . $e->getMessage());
    }
}

 public function submitReview(Request $request, ListingAccessRequest $accessRequest): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->id !== $accessRequest->requested_by) {
                return ApiResponse::error('You can only review your own viewing requests', 403);
            }
            
            if ($accessRequest->request_type !== 'viewing' || $accessRequest->status !== 'approved') {
                return ApiResponse::error('Only approved viewing requests can be reviewed', 422);
            }
            
            // if ($accessRequest->review) {
            //     return ApiResponse::error('You have already reviewed this viewing', 422);
            // }
            
            $validated = $request->validate([
                'review' => 'required|string|max:500'
            ]);
            
            DB::beginTransaction();
            
            // Update the review directly in the same table
            $accessRequest->update([
                'review' => $validated['review'],
                'reviewed_at' => now(),
                'reviewed_by' => $user->id
            ]);
            
            DB::commit();
            
            return ApiResponse::success(
                new ListingAccessRequestResource($accessRequest),
                'Review submitted successfully'
            );
            
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to submit review: ' . $e->getMessage());
        }
    }
    public function setVacationMode(Request $request)
{
    $user = auth()->user();

    // if (!$user->hasRole('agent')) {
    //     return ApiResponse::error('Only agents can use vacation mode', 403);
    // }

    $request->validate([
        'active' => 'required|boolean',
        'delegate_id' => 'nullable|exists:users,id'
    ]);

    if ($request->active && !$request->delegate_id) {
        return ApiResponse::error('Delegate agent is required');
    }
    $old_delegate=$user->delegate_agent_id;
    $user->update([
        'on_vacation' => $request->active,
        'delegate_agent_id' => $request->active
            ? $request->delegate_id
            : null
    ]);
  if($request->active==false){

      $requests=ListingAccessRequest::where('handled_by',$old_delegate)->whereHas('listing',function($q) use($user){
          $q->where('agent_id',$user->id);
      })->get();
      foreach($requests as  $request){
          $request->update(['handled_by'=>null]);
      }
  }

    return ApiResponse::success(
        $user->only(['on_vacation', 'delegate_agent_id']),
        'Vacation mode updated successfully'
    );
}
public function getVacationMode()
{
    $user = auth()->user();

    return ApiResponse::success([
        'id'=>$user->id,
        'on_vacation' => $user->on_vacation,
        'delegate_agent_id' => $user->delegate_agent_id
    ], 'Vacation mode fetched successfully');
}


}