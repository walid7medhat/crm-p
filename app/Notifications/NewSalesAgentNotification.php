<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewSalesAgentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $newSales;

    public function __construct(User $newSales)
    {
        $this->newSales = $newSales;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toBroadcast(object $notifiable)
    {
        return [
            'id' => $this->id,
            'type' => 'App\\Notifications\\NewSalesAgentNotification',
            'data' => $this->toArray($notifiable),
            'read_at' => null,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => "New sales agent registered: {$this->newSales->name}",
            'sales_agent_id' => $this->newSales->id,
            'sales_agent_name' => $this->newSales->name,
            'sales_agent_email' => $this->newSales->email,
            'notification_type' => 'new_sales_agent',
            'timestamp' => now()->toISOString(),
        ];
    }
}