<?php

namespace App\Notifications;

use App\Models\Deal;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class DealUpdatedNotification extends Notification
{
    use Queueable;

    public $deal;
    public $actionType;
    public $user;
    public $changes;

public function __construct(Deal $deal, string $actionType, ?User $user = null, ?array $changes = null)
    {
        $this->deal = $deal;
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
            'deal_id' => $this->deal->id,
            'deal_name' => $this->deal->deal_name,
            'deal_number' => $this->deal->deal_number,
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
            'deal' => [
                'id' => $this->deal->id,
                'deal_name' => $this->deal->deal_name,
                'deal_number' => $this->deal->deal_number,
                'stage_id' => $this->deal->stage_id,
                'responsible_person_id' => $this->deal->responsible_person_id,
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
        $dealName = $this->deal->deal_name ?: "Deal #{$this->deal->deal_number}";
$userName = $this->user?->name ?? 'Integration';

        switch ($this->actionType) {
            case 'created':
                return "{$userName} created a new deal: {$dealName}";
            case 'updated':
                return "{$userName} updated deal: {$dealName}";
            case 'deleted':
                return "{$userName} deleted deal: {$dealName}";
            case 'stage_changed':
                $oldStage = $this->changes['old_stage'] ?? 'Previous Stage';
                $newStage = $this->changes['new_stage'] ?? $this->deal->stage->name;
                return "{$userName} moved deal {$dealName} from {$oldStage} to {$newStage}";
            case 'revert':
               $oldStage = $this->changes['old_stage'] ?? 'Previous Stage';
               $newStage = $this->deal->stage?->name ?? 'New Stage';
               $newPerson=$this->changes['new_person']?? $this->deal->responsiblePerson?->name;
               return "Deal #{$dealName} reverted to {$newStage}";

            case 'assigned':
                $oldPerson = $this->changes['old_person'] ?? 'Previous Person';
                $newPerson = $this->changes['new_person'] ?? $this->deal->responsiblePerson->name;
                return "{$userName} assigned deal {$dealName} from {$oldPerson} to {$newPerson}";
            default:
                return "Deal {$dealName} has been updated";
        }
    }
}