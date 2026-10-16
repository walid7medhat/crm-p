<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Desired animation for one user. Delivered only on that user's private channel.
 */
class UserAnimationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public bool $enabled,
        public ?string $animationId,
        public int $version,
        public ?string $kind = null,
        public ?string $src = null,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'user.animation';
    }

    public function broadcastWith(): array
    {
        return [
            'enabled' => $this->enabled,
            'animation_id' => $this->enabled ? $this->animationId : null,
            'version' => $this->version,
            'kind' => $this->enabled ? $this->kind : null,
            'src' => $this->enabled ? $this->src : null,
        ];
    }
}
