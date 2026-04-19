<?php

namespace App\Events;

use App\Models\Lead;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Real-time assignment updates for admins and the involved sales users (Echo).
 */
class LeadAssignmentBroadcast implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public Lead $lead,
        public int $assignedUserId,
        public ?int $previousUserId,
        public array $payload,
    ) {}

    public function broadcastAs(): string
    {
        return 'lead.assignment.updated';
    }

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('lead-assignment')];

        if ($this->assignedUserId) {
            $channels[] = new PrivateChannel('user.'.$this->assignedUserId);
        }
        if ($this->previousUserId) {
            $channels[] = new PrivateChannel('user.'.$this->previousUserId);
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'lead_id' => $this->lead->id,
            'lead_number' => $this->lead->lead_number,
            'assigned_user_id' => $this->assignedUserId,
            'previous_user_id' => $this->previousUserId,
            'detail' => $this->payload,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
