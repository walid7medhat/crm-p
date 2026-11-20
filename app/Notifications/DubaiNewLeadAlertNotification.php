<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Dubai New Lead stage alert for configured watchers.
 * Class name must not match Lead*Notification: the CRM hides those toasts for admins.
 */
class DubaiNewLeadAlertNotification extends Notification
{
    public function __construct(public int $leadId)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'lead_id' => $this->leadId,
            'title' => 'You have a new lead',
            'message' => 'You have a new lead',
            'action_type' => 'dubai_new_lead',
            'timestamp' => now()->toISOString(),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage([
            'title' => 'You have a new lead',
            'message' => 'You have a new lead',
            'lead_id' => $this->leadId,
            'lead' => ['id' => $this->leadId],
            'action_type' => 'dubai_new_lead',
        ]))->onConnection('sync');
    }
}
