<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Listing;

class HotDealStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $listing;
    protected $approved;
    protected $reviewedBy;
    protected $comments;
    protected $action; // 'approved' or 'rejected'

    public function __construct(Listing $listing, $approved, $reviewedBy, $comments = null)
    {
        $this->listing = $listing;
        $this->approved = $approved;
        $this->reviewedBy = $reviewedBy;
        $this->comments = $comments;
        $this->action = $approved ? 'approved' : 'rejected';
    }

    public function via($notifiable)
    {
        return ['database', 'mail', 'broadcast'];
    }

    public function toBroadcast($notifiable)
    {
        return [
            'id' => $this->id,
            'type' => 'App\\Notifications\\HotDealStatusNotification',
            'data' => $this->toArray($notifiable),
            'read_at' => null,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toMail($notifiable)
    {
        $status = $this->approved ? 'APPROVED' : 'REJECTED';
        $url = url('/property-details/' . $this->listing->id);
        // $url=url('/hotDeal-requests');
        $name = collect(explode(' ', $this->reviewedBy?->name))
                        ->take(2)
                        ->implode(' ');
        
        $mail = (new MailMessage)
            ->subject('Hot Deal Request ' . $status . ': ' . $this->listing?->area->title)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Your hot deal request for property "' . $this->listing->area?->title . '" has been ' . strtolower($status) . '.')
            ->line('**Reviewed by:** ' . $name)
            ->when($this->comments, function ($mail) {
                return $mail->line('**Comments:** ' . $this->comments);
            })
            ->line('**Property Details:**')
            ->line('Reference: ' . $this->listing->reference_number)
            ->line('Price: ' . number_format($this->listing->price, 2))
            ->action('View Property', $url);
        
        return $mail;
    }

    public function toArray($notifiable)
    {
        $name = collect(explode(' ', $this->reviewedBy?->name))
                        ->take(2)
                        ->implode(' ');
        
        return [
            'type' => 'hot_deal_status',
            'notification_type' => $this->action,
            'listing_id' => $this->listing->id,
            'listing_title' => $this->listing->area?->title,
            'reference_number' => $this->listing->reference_number,
            'price' => $this->listing->price,
            'approved' => $this->approved,
            'status' => $this->approved ? 'approved' : 'rejected',
            'reviewed_by_id' => $this->reviewedBy->id,
            'reviewed_by_name' => $name,
            'comments' => $this->comments,
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
        $status = $this->approved ? 'approved' : 'rejected';
        $name = collect(explode(' ', $this->reviewedBy?->name))
                        ->take(2)
                        ->implode(' ');
        
        $message = 'Hot deal request for "' . $this->listing->area?->title . '" has been ' . $status . ' by ' . $name;
        
        if ($this->comments) {
            $message .= '. Comments: ' . $this->comments;
        }
        
        return $message;
    }
}