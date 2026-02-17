<?php

namespace App\Events;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeadUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Lead $lead;
    public string $actionType;
    public ?int $userId;
    public ?array $changes;

    /**
     * @param Lead $lead
     * @param string $actionType
     * @param int|null $userId
     * @param array|null $changes
     */
    public function __construct(
        Lead $lead,
        string $actionType = 'updated',
        ?int $userId = null,
        ?array $changes = null
    ) {
        $this->lead = $lead;
        $this->actionType = $actionType;
        $this->userId = $userId;
        $this->changes = $changes;

        $this->lead->loadMissing([
            'stage',
            'responsiblePerson',
            'addedBy',
            'participants',
            'observers.user',
        ]);
    }

 
    public function broadcastOn()
    {
        return $this->getUserChannels();
    }

 
    public function broadcastAs()
    {
        return 'lead.updated';
    }

  
    public function broadcastWith(): array
    {
        return [
            'lead'        => new \App\Http\Resources\Lead\LeadResource($this->lead),
            'action_type' => $this->actionType,
            'user_id'     => $this->userId,
            'user_name'   => $this->getActorName(),
            'changes'     => $this->changes,
            'message'     => $this->getMessage(),
            'timestamp'   => now()->toISOString(),
        ];
    }

   
    private function getActorName(): string
    {
        if ($this->userId) {
            return User::find($this->userId)?->name ?? 'System';
        }

        return 'Integration';
    }

    
    private function getMessage(): string
    {
        $userName = $this->getActorName();
        $leadName = $this->lead->lead_name ?: "Lead #{$this->lead->lead_number}";
\Log::info($this->actionType);
        switch ($this->actionType) {
            case 'created':
                return "{$userName} created a new lead: {$leadName}";

            case 'updated':
                return "{$userName} updated lead: {$leadName}";

            case 'deleted':
                return "{$userName} deleted lead: {$leadName}";

            case 'stage_changed':
                $oldStage = $this->changes['old_stage'] ?? 'Previous Stage';
                $newStage = $this->lead->stage?->name ?? 'New Stage';
                return "{$userName} moved lead {$leadName} from {$oldStage} to {$newStage}";
                
           case 'revert':
               $oldStage = $this->changes['old_stage'] ?? 'Previous Stage';
               $newStage = $this->lead->stage?->name ?? 'New Stage';
               $newPerson=$this->changes['new_person']?? $this->lead->responsiblePerson?->name;
               return "lead #{$leadName} reverted to {$newStage} ";

            case 'assigned':
                $oldPerson = $this->changes['old_person'] ?? 'Previous Person';
                $newPerson = $this->lead->responsiblePerson?->name ?? 'Unassigned';
                return "{$userName} assigned lead {$leadName} from {$oldPerson} to {$newPerson}";

            default:
                return "Lead {$leadName} has been updated";
        }
    }

   
    private function getUserChannels(): array
    {
        $channels = [];
        if($this->actionType=='assigned'){
              $channels[] = new PrivateChannel('user.' . $this->changes['old_person_id']);
        }
        if ($this->lead->responsible_person_id) {
            $channels[] = new PrivateChannel('user.' . $this->lead->responsible_person_id);
        }

        if ($this->lead->added_by) {
            $channels[] = new PrivateChannel('user.' . $this->lead->added_by);
        }

        foreach ($this->lead->participants ?? [] as $participant) {
            if ($participant->user_id) {
                $channels[] = new PrivateChannel('user.' . $participant->user_id);
            }
        }

        foreach ($this->lead->observers ?? [] as $observer) {
            if ($observer->user_id) {
                $channels[] = new PrivateChannel('user.' . $observer->user_id);
            }
        }

        // // hierarchy managers
        if ($this->lead->responsible_person_id) {
            $responsibleUser = User::find($this->lead->responsible_person_id);

            if ($responsibleUser) {
                foreach ($this->getManagersHierarchy($responsibleUser) as $managerId) {
                    $channels[] = new PrivateChannel('user.' . $managerId);
                }
            }
        }
    // ✅ Admin & Super Admin
// only super admin
    $admins = User::whereHas('roles', function ($q) {
        $q->whereIn('name', [ 'super_admin','admin']);
    })->pluck('id');

    foreach ($admins as $adminId) {
        $channels[] = new PrivateChannel('user.' . $adminId);
    }
        return collect($channels)
            ->unique(fn ($channel) => $channel->name)
            ->values()
            ->all();
    }

    /**
     * المدراء الأعلى في الهيكل الإداري
     */
    private function getManagersHierarchy(User $user): array
    {
        $managerIds = [];
        $currentUser = $user;

        while ($currentUser->parent_id) {
            $parent = User::find($currentUser->parent_id);

            if (!$parent) {
                break;
            }

            $managerIds[] = $parent->id;
            $currentUser = $parent;
        }

        return array_unique($managerIds);
    }
}
