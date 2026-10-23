<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Live Next Actions tour campaign signal for one user.
 */
class NextActionTourCampaignUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public bool $active,
        public string $launchId,
        public string $action,
        public string $feature = 'next_action',
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('crm-campaigns')];
    }

    public function broadcastAs(): string
    {
        return 'next-action-tour';
    }

    public function broadcastWith(): array
    {
        return [
            'active' => $this->active,
            'launch_id' => $this->launchId,
            'action' => $this->action,
            'feature' => $this->feature,
        ];
    }
}
