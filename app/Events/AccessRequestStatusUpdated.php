<?php

namespace App\Events;

use App\Models\ListingAccessRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AccessRequestStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $accessRequest;
    public $listingId;
    public $requestType;
    public $newStatus;
    public $actionType; // 'responded', 'cancelled', 'requested'

    public function __construct(ListingAccessRequest $accessRequest, $actionType = 'responded')
    {
        $this->accessRequest = $accessRequest;
        $this->listingId = $accessRequest->listing_id;
        $this->requestType = $accessRequest->request_type;
        $this->newStatus = $accessRequest->status;
        $this->actionType = $actionType;
    }

    public function broadcastOn()
    {
        return [
            // للـ user اللي عمل الطلب (بيشوف في My Requests و Property Details)
            new PrivateChannel('user.' . $this->accessRequest->requested_by),
            
            // للـ owner/agent اللي عنده الـ property (بيشوف في My Orders)
            new PrivateChannel('user.' . $this->accessRequest->listing->agent_id),
            
           
        ];
    }

    public function broadcastAs()
    {
        return 'access.request.updated';
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->accessRequest->id,
            'listing_id' => $this->listingId,
            'request_type' => $this->requestType,
            'status' => $this->newStatus,
            'action_type' => $this->actionType,
            'requested_by' => $this->accessRequest->requested_by,
            'listing_owner_id' => $this->accessRequest->listing->added_by,
            'responded_at' => $this->accessRequest->responded_at,
            'cancelled_at' => $this->accessRequest->cancelled_at,
            'created_at' => $this->accessRequest->created_at,
            'message' => $this->getMessage(),
            'timestamp' => now()->toISOString()
        ];
    }

    private function getMessage()
    {
        switch ($this->actionType) {
            case 'requested':
                return "New {$this->requestType} request received";
            case 'cancelled':
                return "{$this->requestType} request has been cancelled";
            case 'responded':
                return "{$this->requestType} request has been {$this->newStatus}";
            default:
                return "Request updated";
        }
    }
}