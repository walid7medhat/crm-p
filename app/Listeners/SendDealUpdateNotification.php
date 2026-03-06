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

   

    // 5. Super Admin
    $admins = User::whereHas('roles', function ($q) {
        $q->whereIn('name', ['super_admin']);
    })->get();

    $users = $users->merge($admins);

    return $users->filter()->unique('id');
}
}
