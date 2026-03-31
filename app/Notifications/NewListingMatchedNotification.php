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
    return ['database', 'broadcast', 'mail'];
}

public function toMail(object $notifiable): \Illuminate\Notifications\Messages\MailMessage
{
    $listingUrl = url("/property-details/{$this->listing->id}");
    $userName = $notifiable->name ?? null;

    $bodyLines = [
        "A new property matches your saved search.",
        "Title: {$this->listing->area?->title}",
        // "Unit: {$this->listing->unit_number}",
        "Type: {$this->listing->propertyType?->name}",
        "Price: {$this->listing->price}"
    ];

    return (new \Illuminate\Notifications\Messages\MailMessage)
        ->subject("New Property Matched Your Search")
        ->view('emails.new_listing_matched', [
            'userName' => $userName,
            'subtitle' => 'New Listing Alert',
            'headline' => $this->listing->title ?? 'Property Listing',
            'bodyLines' => $bodyLines,
            'ctaText' => 'View Property',
            'ctaUrl' => $listingUrl,
            'fallbackUrl' => $listingUrl,
        ]);
}

    public function toArray(object $notifiable): array
    {
        return [
            'message' => "A new property matches your saved search",
            'listing_id' => $this->listing->id,
            'listing_title' => $this->listing?->area->title,
            // 'listing_unit_number' => $this->listing->unit_number,
            'listing_type' => $this->listing->propertyType?->name,
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

}