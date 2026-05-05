<?php

namespace App\Http\Controllers\Api\Listing;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\User;

use App\Models\HotDealRequest;
use App\Notifications\HotDealStatusNotification;
use App\Traits\HotDealNotifiable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Helpers\ApiResponse;

class HotDealApprovalController extends Controller
{
    use HotDealNotifiable;
    
    public function __construct()
    {
        $this->middleware('jwt.auth');
    }
    
    /**
     * Get pending hot deal requests
     */
    public function pendingRequests()
    {
        try {
            $user = Auth::user();
            
            $query = HotDealRequest::with([
                        'listing:id,area_id,price,number_of_bedrooms',
                        'listing.area:id,name,parent_id',
                        'listing.area.parent',
                        'requester:id,name'
                    ]);
                // ->where('status', 'pending');
            
            // If user is manager/team lead, only show requests from their hierarchy
            if ($user->hasRole('manager') || $user->hasRole('team_lead')) {
                $userIdsUnder = User::where('parent_id', $user->id)
                     ->orWhereHas('parent', function($parentQuery) use ($user) {
                            $parentQuery->where('parent_id', $user->id);
                        })
                    ->pluck('id');
                $userIdsUnder[] = $user->id;
                
                $query->whereHas('requester', function($q) use ($userIdsUnder) {
                    $q->whereIn('id', $userIdsUnder);
                });
            }elseif($user->hasRole('sales')){
                  return ApiResponse::error('Access Denied' );
            }
            
            $requests = $query->orderByRaw("status = 'pending' DESC")->orderBy('created_at', 'desc')->paginate(20);
            
            return ApiResponse::success($requests, 'Pending hot deal requests retrieved');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve requests: ' . $e->getMessage());
        }
    }
    
    /**
     * Approve or reject hot deal request
     */
    public function processRequest(Request $request, $requestId)
    {
        try {
            $user = Auth::user();
            $hotDealRequest = HotDealRequest::with(['listing', 'requester'])->findOrFail($requestId);
            $listing = $hotDealRequest->listing;
            
            // Check if user can approve this request
            if (!$this->canApproveHotDeal($listing)) {
                return ApiResponse::error('You are not authorized to approve hot deals for this property', 403);
            }
            
            // Check if already processed
            if ($hotDealRequest->status !== 'pending') {
                return ApiResponse::error('This request has already been processed', 422);
            }
            
            $request->validate([
                'action' => 'required|in:approve,reject',
                'comments' => 'nullable|string|max:1000'
            ]);
            
            $isApproved = $request->action === 'approve';
            $comments = $request->comments;
            
            // Update the hot deal request
            $hotDealRequest->update([
                'status' => $isApproved ? 'approved' : 'rejected',
                'approved_by' => $user->id,
                'comments' => $comments,
                'approved_at' => $isApproved ? now() : null,
                'rejected_at' => !$isApproved ? now() : null
            ]);
            
            // If approved, update the listing
            if ($isApproved) {
                $listing->update([
                    'is_hot_deal' => 'Yes',
                    'hot_deal_approved_by' => $user->id,
                    'hot_deal_approved_at' => now()
                ]);
            } else {
                // If rejected, ensure it's not a hot deal
                $listing->update(['is_hot_deal' => 'No']);
            }
            
            // Notify the requester
            try {
                $requester = $hotDealRequest->requester;
                $requester->notify(new HotDealStatusNotification($listing, $isApproved, $user, $comments));
            } catch (\Exception $e) {
                \Log::error('Failed to send hot deal status notification', [
                    'requester_id' => $hotDealRequest->requester->id,
                    'error' => $e->getMessage()
                ]);
            }
            
            // Clear cache
            $this->clearCache();
            
            return ApiResponse::success([
                'request' => $hotDealRequest,
                'listing' => $listing
            ], 'Hot deal request ' . ($isApproved ? 'approved' : 'rejected') . ' successfully');
            
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to process request: ' . $e->getMessage());
        }
    }
    
    private function clearCache()
    {
        try {
            if (method_exists(\Illuminate\Support\Facades\Cache::getStore(), 'tags')) {
                \Illuminate\Support\Facades\Cache::tags(['listings'])->flush();
            }
        } catch (\Exception $e) {
            \Log::warning('Cache clear error: ' . $e->getMessage());
        }
    }
}