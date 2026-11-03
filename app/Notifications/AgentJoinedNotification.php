<?php

namespace App\Notifications;

use App\Http\Resources\User\UserResource;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * "<name> joined the CRM" — sent to super admins and to the new agent's admins and
 * branch admins (see NewAgentJoinedNotifier). Clicking it opens /users/{agent_id}.
 */
class AgentJoinedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $agent;

    public function __construct(User $agent)
    {
        $this->agent = $agent;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toBroadcast(object $notifiable)
    {
        return [
            'id' => $this->id,
            'type' => 'App\\Notifications\\AgentJoinedNotification',
            'data' => $this->toArray($notifiable),
            'read_at' => null,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toArray(object $notifiable): array
    {
        $agent = $this->agent;
        $name = User::resolveDisplayName($agent) ?: $agent->name;
        $role = UserResource::formatRoleLabel($agent->roles->first()?->name);
        $reportsTo = $agent->parent ? (User::resolveDisplayName($agent->parent) ?: $agent->parent->name) : null;

        $message = "{$name} joined the CRM";
        if ($role) {
            $message .= " as {$role}";
        }
        if ($reportsTo) {
            $message .= " (reports to {$reportsTo})";
        }

        return [
            'message' => $message,
            'agent_id' => $agent->id,
            'agent_name' => $name,
            'agent_email' => $agent->email,
            'agent_role' => $role,
            'reports_to' => $reportsTo,
            'notification_type' => 'agent_joined',
            'timestamp' => now()->toISOString(),
        ];
    }
}
