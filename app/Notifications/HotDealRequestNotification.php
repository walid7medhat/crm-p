<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Listing;

class HotDealRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $listing;
    protected $requestedBy;
    protected $action; // 'requested' is the default action for this notification

    public function __construct(Listing $listing, $requestedBy, string $action = 'requested')
    {
        $this->listing = $listing;
        $this->requestedBy = $requestedBy;
        $this->action = $action;
    }

    public function via($notifiable)
    {
        return ['database', 'mail', 'broadcast'];
    }

    public function toBroadcast($notifiable)
    {
        return [
            'id' => $this->id,
            'type' => 'App\\Notifications\\HotDealRequestNotification',
            'data' => $this->toArray($notifiable),
            'read_at' => null,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toMail($notifiable)
    {
        // $url = url('/properties/' . $this->listing->id . '/edit');
        $url=url('/hotDeal-requests');
        $name = collect(explode(' ', $this->requestedBy?->name))
                        ->take(2)
                        ->implode(' ');
        
        $mail = (new MailMessage)
            ->subject('Hot Deal Request: ' . $this->listing->area?->title)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line($name . ' has requested to mark a property as a Hot Deal.')
            ->line('**Property Details:**')
            ->line('Property: ' . $this->listing->area?->title)
            ->line('Reference: ' . $this->listing->reference_number)
            ->line('Price: ' . number_format($this->listing->price, 2))
            ->action('Review Property', $url)
            ->line('Please review and approve or reject this request.');
        
        return $mail;
    }

    public function toArray($notifiable)
    {
        $name = collect(explode(' ', $this->requestedBy?->name))
                        ->take(2)
                        ->implode(' ');
        
        return [
            'type' => 'hot_deal_request',
            'notification_type' => $this->action,
            'listing_id' => $this->listing->id,
            'listing_title' => $this->listing->area?->title,
            'reference_number' => $this->listing->reference_number,
            'price' => $this->listing->price,
            'requested_by_id' => $this->requestedBy->id,
            'requested_by_name' => $name,
            'message' => $this->getMessage(),
            'timestamp' => now()->toISOString(),
        ];
    }

    public function toDatabase($notifiable)
    {
        return $this->toArray($notifiable);
    }

    private function getMessage(): string
    {
        $name = collect(explode(' ', $this->requestedBy?->name))
                        ->take(2)
                        ->implode(' ');
        
        return $name . ' has requested to mark "' . $this->listing->area?->title . '" as a Hot Deal.';
    }
}