<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class LeadRevertWarningNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $lead;
    public $targetStage;
    public $minutesLeft;
    public $customMessage;

    public function __construct(Lead $lead, string $targetStage, int $minutesLeft, string $customMessage = null)
    {
        $this->lead = $lead;
        $this->targetStage = $targetStage;
        $this->minutesLeft = $minutesLeft;
        $this->customMessage = $customMessage;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function broadcastAs(): string
    {
        return 'notification.revert.warning';
    }

    public function toDatabase($notifiable)
    {
        return [
            'lead_id' => $this->lead->id,
            'lead_name' => $this->lead->lead_name,
            'lead_number' => $this->lead->lead_number,
            'action_type' => 'revert_warning',
            'message' => $this->getMessage(),
            'minutes_left' => $this->minutesLeft,
            'target_stage' => $this->targetStage,
            'timestamp' => now()->toISOString(),
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'lead' => [
                'id' => $this->lead->id,
                'lead_name' => $this->lead->lead_name,
                'lead_number' => $this->lead->lead_number,
                'stage_id' => $this->lead->stage_id,
            ],
            'action_type' => 'revert_warning',
            'message' => $this->getMessage(),
            'minutes_left' => $this->minutesLeft,
            'target_stage' => $this->targetStage,
            'timestamp' => now()->toISOString(),
        ]);
    }

    protected function getMessage()
    {
        if ($this->customMessage) {
            return $this->customMessage;
        }

        $leadName = $this->lead->lead_name ?: "Lead #{$this->lead->lead_number}";
        return "Lead {$leadName} will be reverted to {$this->targetStage} in {$this->minutesLeft} minutes";
    }
}