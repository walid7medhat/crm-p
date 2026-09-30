<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Proof-of-concept toast for one configured user.
 * The class name must not match Lead*Notification: the CRM hides those toasts for super admins.
 */
class AssignmentPocNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['broadcast'];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage([
            'title' => 'New Lead Assigned',
            // Existing Echo listener renders `message` as the single toast line.
            'message' => 'New Lead Assigned — You have a new lead assigned to you.',
        ]))->onConnection('sync');
    }
}
