<?php

namespace App\Notifications;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ListingRejected extends Notification
{
    use Queueable;

    protected $listing;
    protected $reason;

    public function __construct(Listing $listing, $reason = null)
    {
        $this->listing = $listing;
        $this->reason = $reason;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'Listing Requires Changes',
            'message' => 'Your property "' . ($this->listing->title ?? $this->listing->reference_number) . '" needs modifications before approval.' . ($this->reason ? ' Reason: ' . $this->reason : ''),
            'listing_id' => $this->listing->id,
            'type' => 'listing_rejected',
            'status' => 'warning',
            'url' => '/property-details/' . $this->listing->id ,
        ];
    }
}