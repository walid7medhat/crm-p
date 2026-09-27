<?php

namespace App\Listeners;

use App\Events\DealUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\User;
use App\Notifications\DealUpdatedNotification;

class SendDealUpdateNotification
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
     public function handle(DealUpdated $event)
    {
        $deal = $event->deal;
        $user = User::find($event->userId);
        
        $usersToNotify = $this->getUsersToNotify($deal);
        
        foreach ($usersToNotify as $notifyUser) {
            if (!$user || $notifyUser->id !== $user->id ) {
                $notifyUser->notify(new DealUpdatedNotification(
                    $deal, 
                    $event->actionType, 
                    $user,
                    $event->changes
                ));
            }
        }
    }
    
private function getUsersToNotify($deal)
{
    $users = collect();

    $authId = auth()->id(); 

    // 1. Responsible person
    if ($deal->responsible_person_id && $deal->responsible_person_id != $authId) {
        $users->push(User::find($deal->responsible_person_id));
    }

    // 2. Added by
    if ($deal->added_by && $deal->added_by != $authId) {
        $users->push(User::find($deal->added_by));
    }

    // 3. Hierarchy managers (manager/team_lead/admin above the responsible person) +
    // branch_admin for that same branch — matches DealUpdated's live broadcast scope,
    // so the persistent notification inbox doesn't fall behind what Kanban shows live.
    if ($deal->responsible_person_id) {
        $responsibleUser = User::find($deal->responsible_person_id);
        if ($responsibleUser) {
            $users = $users->merge($this->getManagersHierarchy($responsibleUser));
            $users = $users->merge($this->getBranchAdminsForUser($responsibleUser));
        }
    }

    // 4. Admin & Super Admin
    $admins = User::whereHas('roles', function ($q) {
        $q->whereIn('name', ['super_admin', 'admin']);
    })->get();

    $users = $users->merge($admins);

    return $users->filter()->unique('id');
}

/** Same upward parent_id walk as DealUpdated::getManagersHierarchy(). */
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

/** Same office-match resolution as DealUpdated::getBranchAdminIdsForUser(). */
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
