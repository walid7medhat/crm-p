<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class LiveWatchPresenceChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public string $status,
        public int $userId,
        public ?string $transitionId,
        public ?string $onlineSince,
        public ?int $monitoredUserId = null,
        public ?string $userName = null,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('live-watch')];
    }

    public function broadcastAs(): string
    {
        return 'presence.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'status' => $this->status,
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'transition_id' => $this->transitionId,
            'online_since' => $this->onlineSince,
            'monitored_user_id' => $this->monitoredUserId ?? $this->userId,
        ];
    }
}
