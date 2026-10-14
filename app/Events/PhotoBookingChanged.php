<?php

namespace App\Events;

use App\Models\MarketingBooking;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Live sync for photoshoot requests: the requester's "Photo Shoots" page and every open
 * Marketing → Bookings tab refresh without a reload. Carries ids only — pages re-fetch.
 *
 * action: created | updated | cancelled | deleted
 */
class PhotoBookingChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @param list<int> $recipientIds */
    public function __construct(
        public int $bookingId,
        public string $status,
        public string $action,
        public array $recipientIds,
    ) {
    }

    public static function fromBooking(MarketingBooking $booking, string $action): self
    {
        $ids = User::permission('marketing')->where('status', 'active')->pluck('id')
            ->merge(User::role('super_admin')->where('status', 'active')->pluck('id'))
            ->push($booking->user_id)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return new self($booking->id, (string) $booking->status, $action, $ids);
    }

    /** Fire-and-forget: a broadcasting problem must never break saving a booking. */
    public static function send(MarketingBooking $booking, string $action): void
    {
        try {
            event(self::fromBooking($booking, $action));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('PhotoBookingChanged broadcast failed: '.$e->getMessage());
        }
    }

    public function broadcastOn(): array
    {
        return array_map(fn ($id) => new PrivateChannel('user.'.$id), $this->recipientIds);
    }

    public function broadcastAs(): string
    {
        return 'photo-booking.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'booking_id' => $this->bookingId,
            'status' => $this->status,
            'action' => $this->action,
        ];
    }
}
