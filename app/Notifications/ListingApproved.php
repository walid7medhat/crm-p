<?php

namespace App\Notifications;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ListingApproved extends Notification
{
    use Queueable;

    protected $listing;
    protected $approvedBy;

    public function __construct(Listing $listing, $approvedBy)
    {
        $this->listing = $listing;
        $this->approvedBy = $approvedBy;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'Listing Approved ✓',
            'message' => 'Your property "' . ($this->listing->title ?? $this->listing->reference_number) . '" has been approved by ' . $this->approvedBy->name,
            'listing_id' => $this->listing->id,
            'type' => 'listing_approved',
            'status' => 'success',
            'url' => '/property-details/' . $this->listing->id,
        ];
    }

    public function toBroadcast($notifiable)
    {
        return [
            'title' => 'Listing Approved ✓',
            'message' => 'Your property has been approved',
            'listing_id' => $this->listing->id,
            'type' => 'listing_approved',
        ];
    }
}