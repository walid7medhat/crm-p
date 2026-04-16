<?php

namespace App\Events;

use App\Models\Deal;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DealUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Deal $deal;
    public string $actionType;
    public ?int $userId;
    public ?array $changes;

    /**
     * @param deal $deal
     * @param string $actionType
     * @param int|null $userId
     * @param array|null $changes
     */
    public function __construct(
        Deal $deal,
        string $actionType = 'updated',
        ?int $userId = null,
        ?array $changes = null
    ) {
        $this->deal = $deal;
        $this->actionType = $actionType;
        $this->userId = $userId;
        $this->changes = $changes;

        $this->deal->loadMissing([
            'stage',
            'responsiblePerson',
            'addedBy',
            
        ]);
    }

 
    public function broadcastOn()
    {
        return $this->getUserChannels();
    }

 
    public function broadcastAs()
    {
        return 'deal.updated';
    }

  
    public function broadcastWith(): array
    {
        return [
            'deal'        => new \App\Http\Resources\Deal\DealPusherResource($this->deal),
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
        $dealName = $this->deal->deal_name ?: "Deal #{$this->deal->deal_number}";
\Log::info($this->actionType);
        switch ($this->actionType) {
            case 'created':
                return "{$userName} created a new deal: {$dealName}";

            case 'updated':
                return "{$userName} updated deal: {$dealName}";

            case 'deleted':
                return "{$userName} deleted deal: {$dealName}";

            case 'stage_changed':
                $oldStage = $this->changes['old_stage'] ?? 'Previous Stage';
                $newStage = $this->deal->stage?->name ?? 'New Stage';
                return "{$userName} moved deal {$dealName} from {$oldStage} to {$newStage}";
                
           case 'revert':
               $oldStage = $this->changes['old_stage'] ?? 'Previous Stage';
               $newStage = $this->deal->stage?->name ?? 'New Stage';
               $newPerson=$this->changes['new_person']?? $this->deal->responsiblePerson?->name;
               return "deal #{$dealName} reverted to {$newStage} ";

            case 'assigned':
                $oldPerson = $this->changes['old_person'] ?? 'Previous Person';
                $newPerson = $this->deal->responsiblePerson?->name ?? 'Unassigned';
                return "{$userName} assigned deal {$dealName} from {$oldPerson} to {$newPerson}";

            default:
                return "Deal {$dealName} has been updated";
        }
    }

   
    private function getUserChannels(): array
    {
        $channels = [];
        if($this->actionType=='assigned' && $this->changes['old_person_id']){
              $channels[] = new PrivateChannel('user.' . $this->changes['old_person_id']);
        }
        if ($this->deal->responsible_person_id) {
            $channels[] = new PrivateChannel('user.' . $this->deal->responsible_person_id);
        }

        if ($this->deal->added_by) {
            $channels[] = new PrivateChannel('user.' . $this->deal->added_by);
        }

      

        // // hierarchy managers
        if ($this->deal->responsible_person_id) {
            $responsibleUser = User::find($this->deal->responsible_person_id);

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
