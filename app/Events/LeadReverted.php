<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Lead;

class LeadReverted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $lead;
    public $minutesLeft;
    public $targetStage;
    public $message;

    public function __construct(Lead $lead, $minutesLeft, $targetStage, $message = null)
    {
        $this->lead = $lead;
        $this->minutesLeft = $minutesLeft;
        $this->targetStage = $targetStage;
        $this->message = $message ?? "Lead will be reverted to {$targetStage} in {$minutesLeft} minutes";
    }

    public function broadcastOn()
    {
        return new PrivateChannel('user.' . $this->lead->responsible_person_id);
    }

    public function broadcastWith()
    {
        return [
            'lead' => [
                'id' => $this->lead->id,
                'lead_name' => $this->lead->lead_name,
                'lead_number' => $this->lead->lead_number,
                'stage_id' => $this->lead->stage_id,
                'stage' => $this->lead->stage?->name,
            ],
            'action_type' => 'revert_warning',
            'minutes_left' => $this->minutesLeft,
            'target_stage' => $this->targetStage,
            'message' => $this->message,
            'timestamp' => now()->toISOString(),
        ];
    }
}