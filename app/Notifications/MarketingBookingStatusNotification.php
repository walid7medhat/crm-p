<?php

namespace App\Notifications;

use App\Models\MarketingBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the person who requested a photoshoot that its status changed
 * (contacted / confirmed / cancelled) — bell + live pop-up.
 */
class MarketingBookingStatusNotification extends Notification
{
    use Queueable;

    public function __construct(public MarketingBooking $booking, public ?string $byName = null)
    {
    }

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    private function payload(): array
    {
        $b = $this->booking;
        $when = $b->booking_date->format('D j M').' at '.\Carbon\Carbon::parse($b->booking_time)->format('g:i A');
        $by = $this->byName ? " by {$this->byName}" : '';

        $message = match ($b->status) {
            'contacted' => "Your photoshoot request {$b->reference} ({$when}) is being handled{$by} — the marketing team will be in touch.",
            'confirmed' => "Your photoshoot on {$when} is confirmed{$by}.",
            'cancelled' => "Your photoshoot request {$b->reference} ({$when}) was cancelled{$by}.",
            default => "Your photoshoot request {$b->reference} was updated{$by}.",
        };

        return [
            'type' => 'photo_booking_status',
            'booking_id' => $b->id,
            'reference' => $b->reference,
            'status' => $b->status,
            'message' => $message,
            'timestamp' => now()->toISOString(),
        ];
    }
}
