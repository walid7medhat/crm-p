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
        if ($event->source === 'bitrix') {
            return;
        }

        $lead = $event->lead;
        $user = User::find($event->userId);
        
        $usersToNotify = $this->getUsersToNotify($lead);

        foreach ($usersToNotify as $notifyUser) {
            // Only exclude the actor from getting notified about their own action —
            // every relevant user is notified regardless of their role (sales included).
            if (!$user || $notifyUser->id !== $user->id) {
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

    // 5. Hierarchy managers (manager/team_lead/admin above the responsible person) +
    // branch_admin for that same branch — matches LeadUpdated's live broadcast scope,
    // so the persistent notification inbox doesn't fall behind what Kanban shows live.
    if ($lead->responsible_person_id) {
        $responsibleUser = User::find($lead->responsible_person_id);
        if ($responsibleUser) {
            $users = $users->merge($this->getManagersHierarchy($responsibleUser));
            $users = $users->merge($this->getBranchAdminsForUser($responsibleUser));
        }
    }

    // 6. Super Admin
    $admins = User::whereHas('roles', function ($q) {
        $q->whereIn('name', ['super_admin']);
    })->get();

    $users = $users->merge($admins);

    return $users->filter()->unique('id');
}

/** Same upward parent_id walk as LeadUpdated::getManagersHierarchy(). */
private function getManagersHierarchy(User $user)
{
    $managers = collect();
    $current = $user;

    while ($current->parent_id) {
        $parent = User::find($current->parent_id);
        if (!$parent) {
            break;
        }
        $managers->push($parent);
        $current = $parent;
    }

    return $managers;
}

/** Same office-match resolution as LeadUpdated::getBranchAdminIdsForUser(). */
private function getBranchAdminsForUser(User $user)
{
    $officeAdmin = $user->office;
    if (!$officeAdmin) {
        return collect();
    }

    return User::role('branch_admin')
        ->get()
        ->filter(fn (User $candidate) => $candidate->office?->id === $officeAdmin->id);
}
}