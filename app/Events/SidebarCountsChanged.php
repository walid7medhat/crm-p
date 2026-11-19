<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * "Sidebar badge counts may have changed" — no data, because counts are per user/role.
 * Open tabs refetch /sidebar/counts on receipt (with jitter) instead of polling every minute.
 */
class SidebarCountsChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $version)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('crm-campaigns')];
    }

    public function broadcastAs(): string
    {
        return 'sidebar.counts.changed';
    }

    public function broadcastWith(): array
    {
        return ['v' => $this->version];
    }
}
