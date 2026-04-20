<?php

namespace App\Events;

use App\Http\Resources\Mobile\MobileLeadCardResource;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
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
            'lead'        => new \App\Http\Resources\Lead\PusherLeadResource($this->lead),
            'action_type' => $this->actionType,
            'user_id'     => $this->userId,
            'user_name'   => $this->getActorName(),
            'changes'     => $this->changes,
            'message'     => $this->getMessage(),
            'timestamp'   => now()->toISOString(),
            /** Semantic type for mobile / Echo clients (Pusher event name stays `lead.updated` for BC). */
            'canonical_event' => $this->resolveCanonicalEvent(),
            /** Flat card DTO — avoids deep nesting on mobile. */
            'lead_mobile' => (new MobileLeadCardResource($this->lead))->resolve(new Request()),
        ];
    }

    /**
     * Maps legacy action_type to stable lifecycle names (REST-style).
     */
    private function resolveCanonicalEvent(): string
    {
        return match ($this->actionType) {
            'created' => 'lead.created',
            'deleted' => 'lead.deleted',
            'stage_changed', 'revert' => 'lead.moved',
            default => 'lead.updated',
        };
    }

   
    private function getActorName(): string
    {
        if ($this->userId) {
            return User::find($this->userId)?->name ?? 'System';
        }

        return '';
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

    if ($this->actionType == 'assigned' && $this->changes['old_person_id']) {
        $this->addChannelIfNotSales($channels, $this->changes['old_person_id']);
    }

    $this->addChannelIfNotSales($channels, $this->lead->responsible_person_id);
    $this->addChannelIfNotSales($channels, $this->lead->added_by);

    foreach ($this->lead->participants ?? [] as $participant) {
        $this->addChannelIfNotSales($channels, $participant->user_id);
    }

    foreach ($this->lead->observers ?? [] as $observer) {
        $this->addChannelIfNotSales($channels, $observer->user_id);
    }

    // hierarchy managers
    if ($this->lead->responsible_person_id) {
        $responsibleUser = User::find($this->lead->responsible_person_id);

        if ($responsibleUser) {
            foreach ($this->getManagersHierarchy($responsibleUser) as $managerId) {
                $this->addChannelIfNotSales($channels, $managerId);
            }
        }
    }

    // Admins
    $admins = User::whereHas('roles', function ($q) {
        $q->whereIn('name', ['super_admin', 'admin']);
    })->pluck('id');

    foreach ($admins as $adminId) {
        $this->addChannelIfNotSales($channels, $adminId);
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
    private function addChannelIfNotSales(&$channels, $userId)
{
    if (!$userId) return;

    $user = User::find($userId);

    if (!$user) {
        return;
    }

    // Admins / super admins must always receive broadcasts (many also have `sales`; Kanban relies on Echo).
    if ($user->hasRole('super_admin') || $user->hasRole('admin')) {
        $channels[] = new PrivateChannel('user.'.$userId);

        return;
    }

    if (!$user->hasRole('sales')) {
        $channels[] = new PrivateChannel('user.'.$userId);
    }
}
}