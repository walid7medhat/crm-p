<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Sent to a user's own channel when their account is set inactive / blocked, so any
 * open session logs out right away (main.js listens for `.account.deactivated`).
 * Sent immediately (not queued) — a logout that waits on a queue worker isn't one.
 * JwtAuthMiddleware still rejects every later request from that account as a fallback.
 */
class UserDeactivated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $userId, public string $status)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.' . $this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'account.deactivated';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'status' => $this->status,
            'message' => 'Your account has been deactivated. Please contact your administrator.',
        ];
    }
}
