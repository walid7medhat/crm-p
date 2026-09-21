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
 * Manually create an already-approved viewing.
 *
 * Only team_lead / manager (and admin / super_admin) may use this. Both the
 * requester (`requested_by`) and the listing's agent must be inside the
 * current user's hierarchy.
 */
public function storeApprovedViewing(Request $request): JsonResponse
{
    try {
        $user = Auth::user();

        if (! $user->hasAnyRole(['super_admin', 'admin', 'manager', 'team_lead'])) {
            return ApiResponse::error('Only managers or team leads can add approved viewings', 403);
        }

        $validated = $request->validate([
            'listing_id'    => 'required|exists:listings,id',
            'requested_by'  => 'required|exists:users,id',
            'viewing_date'  => 'required|date',
            'viewing_time'  => 'required|date_format:H:i',
            'reason'        => 'nullable|string|max:1000',
        ]);

        $listing       = Listing::findOrFail($validated['listing_id']);
        $requestedById = (int) $validated['requested_by'];

        if (! $user->hasAnyRole(['super_admin'])) {
            $allowedAgentIds = $user->getAllSubordinatesIds(); // self + descendants
            if (! in_array((int) $listing->agent_id, $allowedAgentIds, true)) {
                return ApiResponse::error('Listing must belong to an agent in your hierarchy', 403);
            }
            if (! in_array($requestedById, $allowedAgentIds, true)) {
                return ApiResponse::error('Requester must be an agent in your hierarchy', 403);
            }
        }

        DB::beginTransaction();

        $accessRequest = ListingAccessRequest::create([
            'listing_id'    => $listing->id,
            'requested_by'  => $requestedById,
            'owner_id'      => $listing->owner_id,
            'request_type'  => 'viewing',
            'reason'        => $validated['reason'] ?? ('Manually added by ' . $user->name),
            'status'        => 'approved',
            'viewing_date'  => $validated['viewing_date'],
            'viewing_time'  => $validated['viewing_time'],
            'handled_by'    => $user->id,
            'responded_at'  => now(),
        ]);

        DB::commit();

        return ApiResponse::success(
            new ListingAccessRequestResource($accessRequest),
            'Approved viewing added successfully',
            201
        );
    } catch (\Exception $e) {
        DB::rollBack();
        return ApiResponse::error('Failed to add approved viewing: ' . $e->getMessage());
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
     * Outbound access requests (my orders).
     *
     * When `page` is present: server-side pagination + status/search/sort filters.
     * When `page` is omitted: legacy full collection (e.g. my_viewings calendar).
     */
    public function myOrders(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $baseQuery = $this->buildMyOrdersQuery($user);

            return $this->respondAccessRequestList(
                $request,
                $baseQuery,
                $user,
                'My access requests retrieved successfully',
                includePendingReviews: true
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve my access requests: ' . $e->getMessage());
        }
    }

    /**
     * Inbound access requests (my requests / requests on my listings).
     *
     * When `page` is present: server-side pagination + status/search/sort filters.
     * When `page` is omitted: legacy full collection (e.g. my_viewings calendar).
     */
    public function myRequests(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $baseQuery = $this->buildMyRequestsQuery($user);

            return $this->respondAccessRequestList(
                $request,
                $baseQuery,
                $user,
                'My access orders retrieved successfully',
                includePendingReviews: false
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

    /**
     * Approved viewings for a listing (property show sidebar).
     */
    public function listingApprovedViewings(Listing $listing): JsonResponse
    {
        try {
            $user = Auth::user();

            if (! $this->canViewListingViewings($user, $listing)) {
                return ApiResponse::error('Unauthorized', 403);
            }

            $viewings = ListingAccessRequest::with('requestedBy')
                ->where('listing_id', $listing->id)
                ->where('request_type', 'viewing')
                ->where('status', 'approved')
                ->orderByDesc('viewing_date')
                ->orderByDesc('viewing_time')
                ->get()
                ->map(function (ListingAccessRequest $request) {
                    return [
                        'id' => $request->id,
                        'viewing_date' => $request->viewing_date?->format('Y-m-d'),
                        'viewing_time' => $request->viewing_time
                            ? $request->viewing_time->format('H:i')
                            : null,
                        'formatted_date' => $request->viewing_date?->format('d M Y'),
                        'formatted_time' => $request->viewing_time?->format('h:i A'),
                        'responded_at' => $request->responded_at?->format('Y-m-d H:i:s'),
                        'requested_by' => [
                            'id' => $request->requestedBy?->id,
                            'name' => $request->requestedBy
                                ? User::resolveDisplayName($request->requestedBy)
                                : 'Unknown',
                            'avatar' => $request->requestedBy && $request->requestedBy->avatar
                                ? asset('storage/' . $request->requestedBy->avatar)
                                : null,
                        ],
                    ];
                })
                ->values();

            return ApiResponse::success($viewings, 'Approved viewings retrieved');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to fetch approved viewings: ' . $e->getMessage());
        }
    }

    private function canViewListingViewings($user, Listing $listing): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole('super_admin')
            || $user->hasRole('admin')
            || ($user->hasRole('manager') && (int) $user->listing_team === 1)
            || (int) $listing->agent_id === (int) $user->id
            || (int) $user->id === 30;
    }


    private function canRequestAgain($request)
    {
        if (!$request) {
            return true;
        }
            if($request->request_type=='viewing' ){
                return true;
            }

        // if ($request->status === 'pending' || $request->status === 'approved') {
            // if($request->status === 'approved' && $request->request_type=='viewing' && $request->review != null){
            //     return true;
            // }
        //     return false;
        // }

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

        $request->validate([
            'request_type' => 'required|in:unit_number,owner_data,viewing'
        ]);

        $isManagerOrTeamLead = $user->hasRole('manager') || $user->hasRole('team_lead');
        
        $listing = Listing::findOrFail($listingId);
        
        $isManagerOfListingAgent = false;
        if ($isManagerOrTeamLead && $listing->agent) {
           
            $subordinatesIds = $user->getAllSubordinatesIds();
            $isManagerOfListingAgent = in_array($listing->agent->id, $subordinatesIds);
        }
        $query = ListingAccessRequest::where('listing_id', $listingId)
            ->where('request_type', $request->request_type);

        $query->where(function ($q) use ($user, $isManagerOrTeamLead, $isManagerOfListingAgent, $request) {
            $q->where('requested_by', $user->id);
            
            if ($isManagerOrTeamLead && $isManagerOfListingAgent && $request->request_type === 'viewing') {
                $q->orWhereHas('listing', function ($subQuery) use ($user) {
                    $subQuery->whereIn('agent_id', $user->getAllSubordinatesIds());
                });
            }
        });

        // Approved viewings can only be cancelled by admin / super_admin
        // or by a manager whose listing_team flag is set. Everyone else
        // (sales, regular managers, team_leads) is limited to pending/in_progress.
        $isPrivilegedCanceller = $user->hasAnyRole(['super_admin', 'admin'])
            || ($user->hasRole('manager') && (int) $user->listing_team === 1);

        if ($request->request_type === 'viewing' && $isPrivilegedCanceller) {
            $query->whereIn('status', ['pending', 'in_progress', 'approved']);
        } else {
            $query->whereIn('status', ['pending', 'in_progress']);
        }

        $accessRequest = $query->first();

        if (!$accessRequest) {
            return response()->json([
                'status' => false,
                'message' => 'Request not found or not allowed to cancel'
            ], 404);
        }

        

        broadcast(new AccessRequestStatusUpdated($accessRequest, 'cancelled'));

        $property = Listing::with(['owner', 'agent'])->find($listingId);

        if (!$property) {
            return response()->json([
                'status' => false,
                'message' => 'Property not found'
            ], 404);
        }

        $accessRequest->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => $user->id,
            'cancellation_reason' => $request->cancellation_reason
        ]);

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

    if ($request->active) {
        $this->transferListingsForVacation($user, (int) $request->delegate_id);
    } else {
        $this->restoreListingsFromVacation($user);

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

/**
 * When vacation activates: copy the absent agent's listings to the delegate.
 * Stores the original agent in vacation_holder_id so we can revert later.
 * Idempotent — skips listings already delegated for the same holder.
 */
private function transferListingsForVacation(User $holder, int $delegateId): void
{
    Listing::where('agent_id', $holder->id)
        ->whereNull('vacation_holder_id')
        ->update([
            'vacation_holder_id' => $holder->id,
            'agent_id'           => $delegateId,
        ]);

    // Bulk update bypasses model events — invalidate any cached listing pages
    // for both the holder (their "my listings" was full) and the delegate
    // (their "my listings" now includes new rows).
    ListingController::clearListingsCacheFor($holder->id);
    ListingController::clearListingsCacheFor($delegateId);
}

/**
 * When vacation deactivates: restore the listings that were temporarily delegated
 * for this holder back to them.
 */
private function restoreListingsFromVacation(User $holder): void
{
    // Capture the delegate ids that currently hold these rows so we can also
    // bust their cache after we move the listings back.
    $delegateIds = Listing::where('vacation_holder_id', $holder->id)
        ->pluck('agent_id')
        ->unique()
        ->all();

    Listing::where('vacation_holder_id', $holder->id)
        ->update([
            'agent_id'           => $holder->id,
            'vacation_holder_id' => null,
        ]);

    ListingController::clearListingsCacheFor($holder->id);
    foreach ($delegateIds as $did) {
        if ($did) {
            ListingController::clearListingsCacheFor((int) $did);
        }
    }
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

/**
 * Authorization helper for the manager-managed vacation endpoints below.
 * Allowed: admin / super_admin, OR manager with listing_team=1 whose hierarchy
 * contains the target user. Returns the target User or null if not allowed.
 */
private function authorizeUserVacationAccess(User $target): bool
{
    $current = auth()->user();
    if (!$current) return false;
    if ($current->hasAnyRole(['super_admin', 'admin'])) return true;
    if (!$current->hasRole('manager')) return false;
    if ((int) $current->listing_team !== 1) return false;
    return in_array((int) $target->id, $current->getAllSubordinatesIds(), true);
}

/** Manager (listing_team=1) reads vacation status of a team member. */
public function getUserVacationMode(User $user)
{
    if (!$this->authorizeUserVacationAccess($user)) {
        return ApiResponse::error('Not allowed to view this user\'s vacation', 403);
    }
    return ApiResponse::success([
        'id' => $user->id,
        'name' => $user->name,
        'on_vacation' => (bool) $user->on_vacation,
        'delegate_agent_id' => $user->delegate_agent_id,
    ], 'User vacation mode fetched successfully');
}

/** Manager (listing_team=1) sets vacation for a team member. */
public function setUserVacationMode(Request $request, User $user)
{
    if (!$this->authorizeUserVacationAccess($user)) {
        return ApiResponse::error('Not allowed to manage this user\'s vacation', 403);
    }

    $request->validate([
        'active' => 'required|boolean',
        'delegate_id' => 'nullable|exists:users,id',
    ]);

    if ($request->active && !$request->delegate_id) {
        return ApiResponse::error('Delegate agent is required');
    }
    if ($request->active && (int) $request->delegate_id === (int) $user->id) {
        return ApiResponse::error('Delegate cannot be the same user');
    }

    $oldDelegate = $user->delegate_agent_id;
    $user->update([
        'on_vacation' => $request->active,
        'delegate_agent_id' => $request->active ? $request->delegate_id : null,
    ]);

    // Transfer listings to the delegate while on vacation; restore on return.
    if ($request->active) {
        $this->transferListingsForVacation($user, (int) $request->delegate_id);
    } else {
        $this->restoreListingsFromVacation($user);

        if ($oldDelegate) {
            $requests = ListingAccessRequest::where('handled_by', $oldDelegate)
                ->whereHas('listing', function ($q) use ($user) {
                    $q->where('agent_id', $user->id);
                })->get();
            foreach ($requests as $req) {
                $req->update(['handled_by' => null]);
            }
        }
    }

    return ApiResponse::success(
        $user->only(['id', 'name', 'on_vacation', 'delegate_agent_id']),
        'User vacation mode updated successfully'
    );
}

    /**
     * Visibility-scoped query for outbound requests (my-orders / AllRequests).
     * Preserves existing hierarchy rules for non-admin users.
     */
    private function buildMyOrdersQuery(User $user)
    {
        $currentUser = $user;
        $userHierarchy = User::where(function ($q) use ($currentUser) {
            $q->where('id', $currentUser->id)
                ->orWhere('parent_id', $currentUser->id)
                ->orWhereHas('parent', function ($parentQuery) use ($currentUser) {
                    $parentQuery->where('parent_id', $currentUser->id);
                });
        })->pluck('id')->toArray();

        return ListingAccessRequest::query()
            ->when(!($user->hasRole('admin') || $user->hasRole('super_admin')), function ($q) use ($userHierarchy) {
                $q->whereIn('requested_by', $userHierarchy);
            });
    }

    /**
     * Visibility-scoped query for inbound requests (my-requests).
     * Preserves existing listing-agent hierarchy + handled_by rules.
     */
    private function buildMyRequestsQuery(User $user)
    {
        $currentUser = $user;
        $userHierarchy = User::where(function ($q) use ($currentUser) {
            $q->where('id', $currentUser->id)
                ->orWhere('parent_id', $currentUser->id)
                ->orWhereHas('parent', function ($parentQuery) use ($currentUser) {
                    $parentQuery->where('parent_id', $currentUser->id);
                });
        })->pluck('id')->toArray();

        return ListingAccessRequest::query()
            ->where(function ($mainQuery) use ($user, $userHierarchy) {
                $mainQuery->whereHas('listing', function ($query) use ($user, $userHierarchy) {
                    if ($user->hasRole('super_admin') || $user->hasRole('admin')) {
                        return;
                    }
                    $query->whereIn('agent_id', $userHierarchy);
                })->orWhere('handled_by', $user->id);
            });
    }

    /**
     * Shared list response: paginate when `page` is present, otherwise full dump.
     */
    private function respondAccessRequestList(
        Request $request,
        $baseQuery,
        User $user,
        string $message,
        bool $includePendingReviews
    ): JsonResponse {
        $eager = $this->accessRequestListEagerLoads();

        // Status tab counts (full visible set, before search/status filters) — matches prior UI.
        $statusCounts = $this->accessRequestStatusCounts(clone $baseQuery);

        $showAllColumn = $user->hasRole('super_admin')
            || $user->hasRole('admin')
            || $user->hasRole('team_lead')
            || $user->hasRole('manager');

        $pendingReviewsPayload = null;
        if ($includePendingReviews) {
            $pendingReviewsPayload = $this->resolvePendingReviewsPayload(clone $baseQuery, $user, $eager);
        }

        // Legacy: no `page` → return entire visible set (my_viewings etc.).
        if (!$request->has('page')) {
            $requests = (clone $baseQuery)
                ->with($eager)
                ->orderBy('created_at', 'desc')
                ->get();

            ListingAccessRequestResource::primeForCollection($user);
            try {
                $data = ListingAccessRequestResource::collection($requests)->resolve();
            } finally {
                ListingAccessRequestResource::clearCollectionPrime();
            }

            $meta = [
                'status_counts' => $statusCounts,
                'show_all_column' => $showAllColumn,
                'paginated' => false,
            ];
            if ($includePendingReviews) {
                $meta['pending_reviews'] = $pendingReviewsPayload;
                $meta['pending_reviews_count'] = count($pendingReviewsPayload);
            }

            return ApiResponse::success($data, $message, 200, $meta);
        }

        $perPage = (int) $request->input('per_page', 10);
        $perPage = max(1, min($perPage, 100));
        $page = max(1, (int) $request->input('page', 1));

        $buildListQuery = function () use ($baseQuery, $request) {
            $listQuery = clone $baseQuery;
            $this->applyAccessRequestSearch($listQuery, $request->input('search'));
            $this->applyAccessRequestStatusFilter($listQuery, $request->input('status'));
            $this->applyAccessRequestSort(
                $listQuery,
                $request->input('sort_by'),
                $request->input('sort_dir', 'desc')
            );

            return $listQuery;
        };

        $paginator = $buildListQuery()->with($eager)->paginate($perPage, ['listing_access_requests.*'], 'page', $page);

        // Empty / past last page: clamp to last page when possible.
        if ($paginator->total() > 0 && $page > $paginator->lastPage()) {
            $page = $paginator->lastPage();
            $paginator = $buildListQuery()->with($eager)->paginate($perPage, ['listing_access_requests.*'], 'page', $page);
        }

        ListingAccessRequestResource::primeForCollection($user);
        try {
            $data = ListingAccessRequestResource::collection($paginator->getCollection())->resolve();
        } finally {
            ListingAccessRequestResource::clearCollectionPrime();
        }

        $meta = [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'status_counts' => $statusCounts,
            'show_all_column' => $showAllColumn,
            'paginated' => true,
        ];
        if ($includePendingReviews) {
            $meta['pending_reviews'] = $pendingReviewsPayload;
            $meta['pending_reviews_count'] = count($pendingReviewsPayload);
        }

        return ApiResponse::success($data, $message, 200, $meta);
    }

    private function accessRequestListEagerLoads(): array
    {
        return [
            'listing.area',
            'listing.agent',
            'requestedBy',
            'convertedBy',
            'handledBy',
            'reviewer',
        ];
    }

    private function accessRequestStatusCounts($query): array
    {
        $rows = (clone $query)
            ->reorder()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $counts = [
            'all' => (int) $rows->sum(),
            'pending' => (int) ($rows['pending'] ?? 0),
            'approved' => (int) ($rows['approved'] ?? 0),
            'converted' => (int) ($rows['converted'] ?? 0),
            'rejected' => (int) ($rows['rejected'] ?? 0),
            'cancelled' => (int) ($rows['cancelled'] ?? 0),
            'in_progress' => (int) ($rows['in_progress'] ?? 0),
        ];

        return $counts;
    }

    private function applyAccessRequestStatusFilter($query, $status): void
    {
        if ($status === null || $status === '' || $status === 'all') {
            return;
        }

        $allowed = ['pending', 'approved', 'converted', 'rejected', 'cancelled', 'in_progress'];
        if (in_array($status, $allowed, true)) {
            $query->where('listing_access_requests.status', $status);
        }
    }

    private function applyAccessRequestSearch($query, $search): void
    {
        $keyword = is_string($search) ? trim($search) : '';
        if ($keyword === '') {
            return;
        }

        $like = '%' . $keyword . '%';

        $query->where(function ($q) use ($like) {
            $q->where('listing_access_requests.reference_number', 'like', $like)
                ->orWhere('listing_access_requests.request_type', 'like', $like)
                ->orWhere('listing_access_requests.status', 'like', $like)
                ->orWhere('listing_access_requests.reason', 'like', $like)
                ->orWhereHas('requestedBy', function ($userQuery) use ($like) {
                    $userQuery->where('name', 'like', $like)
                        ->orWhere('display_name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                })
                ->orWhereHas('listing.area', function ($areaQuery) use ($like) {
                    $areaQuery->where('name', 'like', $like);
                })
                ->orWhereHas('listing.agent', function ($agentQuery) use ($like) {
                    $agentQuery->where('name', 'like', $like)
                        ->orWhere('display_name', 'like', $like);
                });
        });
    }

    private function applyAccessRequestSort($query, $sortBy, $sortDir): void
    {
        $dir = strtolower((string) $sortDir) === 'asc' ? 'asc' : 'desc';
        $table = 'listing_access_requests';

        switch ($sortBy) {
            case 'reference_number':
                $query->orderBy("{$table}.reference_number", $dir);
                break;
            case 'request_type':
                $query->orderBy("{$table}.request_type", $dir);
                break;
            case 'status':
                $query->orderBy("{$table}.status", $dir);
                break;
            case 'created_at':
                $query->orderBy("{$table}.created_at", $dir);
                break;
            case 'responded_at':
                $query->orderBy("{$table}.responded_at", $dir);
                break;
            case 'converted_at':
                $query->orderBy("{$table}.converted_at", $dir);
                break;
            case 'property_title':
                $query->leftJoin('listings as lar_listings', 'lar_listings.id', '=', "{$table}.listing_id")
                    ->leftJoin('areas as lar_areas', 'lar_areas.id', '=', 'lar_listings.area_id')
                    ->orderBy('lar_areas.name', $dir)
                    ->select("{$table}.*");
                break;
            case 'requester_name':
                $query->leftJoin('users as lar_requesters', 'lar_requesters.id', '=', "{$table}.requested_by")
                    ->orderByRaw('COALESCE(lar_requesters.display_name, lar_requesters.name) ' . $dir)
                    ->select("{$table}.*");
                break;
            default:
                $query->orderBy("{$table}.created_at", 'desc');
                break;
        }
    }

    private function resolvePendingReviewsPayload($baseQuery, User $user, array $eager): array
    {
        $pending = (clone $baseQuery)
            ->with($eager)
            ->where('request_type', 'viewing')
            ->where('status', 'approved')
            ->where('requested_by', $user->id)
            ->where(function ($q) {
                $q->whereNull('review')->orWhere('review', '');
            })
            ->orderBy('created_at', 'desc')
            ->get();

        ListingAccessRequestResource::primeForCollection($user);
        try {
            return ListingAccessRequestResource::collection($pending)->resolve();
        } finally {
            ListingAccessRequestResource::clearCollectionPrime();
        }
    }

}