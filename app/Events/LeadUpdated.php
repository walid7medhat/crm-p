<?php

namespace App\Events;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeadUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $lead;
    public $actionType; // 'created', 'updated', 'deleted', 'stage_changed', 'assigned'
    public $userId;
    public $changes;

    public function __construct(Lead $lead, $actionType = 'updated', $userId = null, $changes = null)
    {
        $this->lead = $lead;
        $this->actionType = $actionType;
        $this->userId = $userId ?? auth()->id();
        $this->changes = $changes;
        
        // Load relationships for broadcasting
        $this->lead->load(['stage', 'responsiblePerson', 'addedBy', 'participants', 'observers.user']);
    }

        public function broadcastOn()
        {
            // أعد المستخدمين الذين يمكنهم رؤية هذا الـ lead
            $channels = $this->getUserChannels();
            
            return $channels;
        }

    public function broadcastAs()
    {
            return new Channel('lead.updated');

    }

    public function broadcastWith()
    {
        return [
            'lead' => new \App\Http\Resources\Lead\LeadResource($this->lead),
            'action_type' => $this->actionType,
            'user_id' => $this->userId,
            'user_name' => auth()->user()->name,
            'changes' => $this->changes,
            'message' => $this->getMessage(),
            'timestamp' => now()->toISOString()
        ];
    }

    private function getMessage()
    {
        $userName = auth()->user()->name;
        $leadName = $this->lead->lead_name ?: "Lead #{$this->lead->lead_number}";

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
            case 'assigned':
                $oldPerson = $this->changes['old_person'] ?? 'Previous Person';
                $newPerson = $this->changes['new_person'] ?? $this->lead->responsiblePerson->name;
                return "{$userName} assigned lead {$leadName} from {$oldPerson} to {$newPerson}";
            default:
                return "Lead {$leadName} has been updated";
        }
    }

    /**
     * الحصول على قنوات جميع المستخدمين الذين يمكنهم رؤية الـ lead
     */
    private function getUserChannels()
    {
        $channels = [];
            // $channels[] = new PrivateChannel('user.24' );
        
        // 1. الـ User المسؤول عن الـ lead
        if ($this->lead->responsible_person_id) {
            $channels[] = new PrivateChannel('user.' . $this->lead->responsible_person_id);
        }

        // 2. الـ User الذي أضاف الـ lead
        if ($this->lead->added_by) {
            $channels[] = new PrivateChannel('user.' . $this->lead->added_by);
        }

        // 3. جميع الـ Participants
        foreach ($this->lead->participants as $participant) {
            // إذا كان participant لديه user_id
            if ($participant->user_id) {
                $channels[] = new PrivateChannel('user.' . $participant->user_id);
            }
        }

        // 4. جميع الـ Observers
        foreach ($this->lead->observers as $observer) {
            if ($observer->user_id) {
                $channels[] = new PrivateChannel('user.' . $observer->user_id);
            }
        }

        // 5. الـ Managers والـ Team Leads الذين يشرفون على responsible person
        if ($this->lead->responsible_person_id) {
            $responsibleUser = \App\Models\User::find($this->lead->responsible_person_id);
            if ($responsibleUser) {
                // الحصول على جميع المدراء وفريق leads فوق هذا المستخدم
                $managers = $this->getManagersHierarchy($responsibleUser);
                foreach ($managers as $managerId) {
                    $channels[] = new PrivateChannel('user.' . $managerId);
                }
            }
        }

        // 6. إزالة التكرارات
        $uniqueChannels = collect($channels)->unique(function ($channel) {
            return $channel->name;
        })->values()->all();

        return $uniqueChannels;
    }

    /**
     * الحصول على جميع المدراء في التسلسل الهرمي
     */
    private function getManagersHierarchy(User $user)
    {
        $managerIds = [];
        $currentUser = $user;

        while ($currentUser->parent_id) {
            $parent = \App\Models\User::find($currentUser->parent_id);
            if ($parent) {
                $managerIds[] = $parent->id;
                $currentUser = $parent;
            } else {
                break;
            }
        }

        return array_unique($managerIds);
    }
}