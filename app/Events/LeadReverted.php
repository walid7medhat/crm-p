<?php

namespace App\Events;

use App\Models\Lead;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeadReverted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Lead $lead;

    public int $minutesLeft;

    public string $targetStage;

    public ?string $message;

    /** @var list<int> */
    public array $userIds;

    /**
     * @param  list<int>  $userIds  Users who should receive the warning (responsible + observers).
     */
    public function __construct(
        Lead $lead,
        int $minutesLeft,
        string $targetStage,
        ?string $message = null,
        array $userIds = [],
    ) {
        $this->lead = $lead;
        $this->minutesLeft = $minutesLeft;
        $this->targetStage = $targetStage;
        $this->message = $message ?? "Lead will be reverted to {$targetStage} in {$minutesLeft} minutes";
        $this->userIds = $userIds;
    }

    /**
     * @return array<int, \Illuminate\Broadcasting\PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $ids = $this->userIds !== []
            ? $this->userIds
            : array_filter([(int) $this->lead->responsible_person_id]);

        return collect($ids)
            ->filter()
            ->unique()
            ->values()
            ->map(fn (int $id) => new PrivateChannel('user.'.$id))
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'lead.revert.warning';
    }

    public function broadcastWith(): array
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
