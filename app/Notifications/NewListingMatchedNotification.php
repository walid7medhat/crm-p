<?php

namespace App\Notifications;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewListingMatchedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Listing $listing)
    {
        //
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => "A new property matches your saved search",
            'listing_id' => $this->listing->id,
            'listing_title' => $this->listing->title,
            'listing_unit_number' => $this->listing->unit_number,
            'listing_type' => $this->listing->property_type?->name,
            'listing_link' => url("/property-details/{$this->listing->id}"),
            'timestamp' => now()->toISOString(),
        ];
    }

    public function toBroadcast(object $notifiable)
    {
        return [
            'id' => $this->id,
            'type' => 'App\\Notifications\\NewListingMatchedNotification',
            'data' => $this->toArray($notifiable),
            'read_at' => null,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🏠 New Property Matched Your Search')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line("A new property matches your saved search:")
            ->line("Title: {$this->listing->title}")
            ->line("Unit: {$this->listing->unit_number}")
            ->line("Type: {$this->listing->property_type?->name}")
            ->action('View Property', url("/properties/{$this->listing->id}"))
            ->line('Thank you for using our platform!');
    }
}