<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Pushed immediately when global maintenance is toggled/updated so open CRM
 * sessions react without a manual refresh. Clients subscribe to crm-campaigns.
 */
class MaintenanceModeUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array{enabled: bool, scope?: string, branch_user_ids?: list<int>, title: ?string, message: ?string, estimated_return: ?string}  $payload
     */
    public function __construct(public array $payload)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('crm-campaigns')];
    }

    public function broadcastAs(): string
    {
        return 'maintenance.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'maintenance' => true,
            'enabled' => (bool) ($this->payload['enabled'] ?? false),
            'scope' => $this->payload['scope'] ?? 'all',
            'branch_user_ids' => array_values($this->payload['branch_user_ids'] ?? []),
            'title' => $this->payload['title'] ?? null,
            'message' => $this->payload['message'] ?? null,
            'estimated_return' => $this->payload['estimated_return'] ?? null,
        ];
    }
}
