<?php

namespace App\Notifications;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PropertyAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $property;
    public $assignedBy;

    public function __construct(Listing $property, $assignedBy)
    {
        $this->property = $property;
        $this->assignedBy = $assignedBy;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toBroadcast(object $notifiable)
    {
        return [
            'id' => $this->id,
            'type' => 'App\\Notifications\\PropertyAssignedNotification',
            'data' => $this->toArray($notifiable),
            'read_at' => null,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => "You have been assigned to a new property: {$this->property->area->name}",
            'property_id' => $this->property->id,
            'property_title' => $this->property->area->name,
            'property_price' => $this->property->price,
            'assigned_by_id' => $this->assignedBy->id,
            'assigned_by_name' => $this->assignedBy->name,
            'assignment_notes' => $this->property->assignment_notes,
            'notification_type' => 'property_assigned',
            'timestamp' => now()->toISOString(),
        ];
    }
}