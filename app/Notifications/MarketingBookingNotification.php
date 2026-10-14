<?php

namespace App\Notifications;

use App\Models\MarketingBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/** A new photoshoot request from a colleague — bell + live pop-up for the marketing team. */
class MarketingBookingNotification extends Notification
{
    use Queueable;

    public function __construct(public MarketingBooking $booking)
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
        $type = MarketingBooking::TYPE_LABELS[$b->shoot_type] ?? $b->shoot_type;
        $when = $b->booking_date->format('D j M').' at '.\Carbon\Carbon::parse($b->booking_time)->format('g:i A');
        $where = trim(($b->location_label ?? '').($b->unit_number ? ' · Unit '.$b->unit_number : ''), ' ·');

        return [
            'type' => 'marketing_booking',
            'booking_id' => $b->id,
            'reference' => $b->reference,
            'name' => $b->name,
            'message' => "New photoshoot request from {$b->name}: {$type}, {$when}".($where ? " — {$where}" : ''),
            'timestamp' => now()->toISOString(),
        ];
    }
}
