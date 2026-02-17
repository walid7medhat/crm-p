<?php

namespace App\Notifications;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class LeadUpdatedNotification extends Notification
{
    use Queueable;

    public $lead;
    public $actionType;
    public $user;
    public $changes;

public function __construct(Lead $lead, string $actionType, ?User $user = null, ?array $changes = null)
    {
        $this->lead = $lead;
        $this->actionType = $actionType;
        $this->user = $user;
        $this->changes = $changes;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'lead_id' => $this->lead->id,
            'lead_name' => $this->lead->lead_name,
            'lead_number' => $this->lead->lead_number,
            'action_type' => $this->actionType,
            'user_id' => $this->user?->id,
            'user_name' => $this->user?->name,
            'changes' => $this->changes,
            'message' => $this->getMessage(),
            'timestamp' => now()->toISOString()
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
                'responsible_person_id' => $this->lead->responsible_person_id,
            ],
            'action_type' => $this->actionType,
            'user_id' => $this->user?->id,
            'user_name' => $this->user?->name,
            'changes' => $this->changes,
            'message' => $this->getMessage(),
            'timestamp' => now()->toISOString()
        ]);
    }

    public function getMessage()
    {
        $leadName = $this->lead->lead_name ?: "Lead #{$this->lead->lead_number}";
$userName = $this->user?->name ?? 'Integration';

        switch ($this->actionType) {
            case 'created':
                return "{$userName} created a new lead: {$leadName}";
            case 'updated':
                return "{$userName} updated lead: {$leadName}";
            case 'deleted':
                return "{$userName} deleted lead: {$leadName}";
            case 'stage_changed':
                $oldStage = $this->changes['old_stage'] ?? 'Previous Stage';
                $newStage = $this->changes['new_stage'] ?? $this->lead->stage->name;
                return "{$userName} moved lead {$leadName} from {$oldStage} to {$newStage}";
            case 'revert':
               $oldStage = $this->changes['old_stage'] ?? 'Previous Stage';
               $newStage = $this->lead->stage?->name ?? 'New Stage';
               $newPerson=$this->changes['new_person']?? $this->lead->responsiblePerson?->name;
               return "lead #{$leadName} reverted to {$newStage}";

            case 'assigned':
                $oldPerson = $this->changes['old_person'] ?? 'Previous Person';
                $newPerson = $this->changes['new_person'] ?? $this->lead->responsiblePerson->name;
                return "{$userName} assigned lead {$leadName} from {$oldPerson} to {$newPerson}";
            default:
                return "Lead {$leadName} has been updated";
        }
    }
}