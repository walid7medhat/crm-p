<?php

namespace App\Notifications;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class ListingNeedsApproval extends Notification implements ShouldQueue
{
    use Queueable;

    protected $listing;

    public function __construct(Listing $listing)
    {
        $this->listing = $listing;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'New Listing Needs Approval',
            'message' => 'A new property "' . ($this->listing->title ?? $this->listing->reference_number) . '" has been added and requires your approval.',
            'listing_id' => $this->listing->id,
            'type' => 'listing_approval',
            'url' => '/property-details/' . $this->listing->id, 
        ];
    }

    public function toMail($notifiable)
    {
        return (new \Illuminate\Notifications\Messages\MailMessage)
                    ->subject('New Listing Needs Approval')
                    ->line('A new property has been added.')
                    ->action('Review Listing', url('/listings/properties/' . $this->listing->id))
                    ->line('Please review and approve this listing.');
    }
}