<?php

namespace App\Events;

use App\Models\ForeignAccessEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Real-time foreign access alert for super_admin only (private channel gate).
 * Payload is intentionally compact — full detail stays behind the SA API.
 */
class ForeignAccessDetected implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public ForeignAccessEvent $event,
        public string $userName,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('foreign-access')];
    }

    public function broadcastAs(): string
    {
        return 'foreign.access';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->event->id,
            'user_id' => $this->event->user_id,
            'user_name' => $this->userName,
            'country' => $this->event->country,
            'country_code' => $this->event->country_code,
            'city' => $this->event->city,
            'ip' => $this->event->ip,
            'detection_reason' => $this->event->detection_reason,
            'occurred_at' => $this->event->occurred_at?->toIso8601String(),
            'status' => $this->event->status,
        ];
    }
}
