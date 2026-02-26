<?php

namespace App\Listeners;

use App\Events\LeadUpdated;
use App\Models\User;
use App\Notifications\LeadUpdatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendLeadUpdateNotification
{
    public function handle(LeadUpdated $event)
    {
        $lead = $event->lead;
        $user = User::find($event->userId);
        
        $usersToNotify = $this->getUsersToNotify($lead);
        
        foreach ($usersToNotify as $notifyUser) {
            if (!$user || $notifyUser->id !== $user->id ) {
                $notifyUser->notify(new LeadUpdatedNotification(
                    $lead, 
                    $event->actionType, 
                    $user,
                    $event->changes
                ));
            }
        }
    }
    
private function getUsersToNotify($lead)
{
    $users = collect();

    $authId = auth()->id(); 

    // 1. Responsible person
    if ($lead->responsible_person_id && $lead->responsible_person_id != $authId) {
        $users->push(User::find($lead->responsible_person_id));
    }

    // 2. Added by
    if ($lead->added_by && $lead->added_by != $authId) {
        $users->push(User::find($lead->added_by));
    }

    // 3. Participants
    foreach ($lead->participants as $participant) {
        if ($participant->user_id && $participant->user_id != $authId) {
            $users->push(User::find($participant->user_id));
        }
    }

    // 4. Observers
    foreach ($lead->observers as $observer) {
        if ($observer->user_id && $observer->user_id != $authId) {
            $users->push(User::find($observer->user_id));
        }
    }

    // 5. Super Admin
    $admins = User::whereHas('roles', function ($q) {
        $q->whereIn('name', ['super_admin']);
    })->get();

    $users = $users->merge($admins);

    return $users->filter()->unique('id');
}
}