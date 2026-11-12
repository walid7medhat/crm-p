<?php

namespace App\Events;

use App\Models\LiveWatchEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveWatchTimelineEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public LiveWatchEvent $event)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('live-watch')];
    }

    public function broadcastAs(): string
    {
        return 'timeline.event';
    }

    public function broadcastWith(): array
    {
        $e = $this->event;

        return [
            'id' => $e->id,
            'user_id' => $e->user_id,
            'event_kind' => $e->event_kind,
            'event_type' => $e->event_type,
            'module' => $e->module,
            'action' => $e->action,
            'entity_type' => $e->entity_type,
            'entity_id' => $e->entity_id,
            'metadata' => $e->metadata,
            'session_id' => $e->session_id,
            'occurred_at' => $e->occurred_at?->toIso8601String(),
        ];
    }
}
