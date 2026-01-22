<?php

namespace App\Notifications;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PropertyUnassignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $property;
    public $unassignedBy;

    public function __construct(Listing $property, $unassignedBy)
    {
        $this->property = $property;
        $this->unassignedBy = $unassignedBy;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toBroadcast(object $notifiable)
    {
        return [
            'id' => $this->id,
            'type' => 'App\\Notifications\\PropertyUnassignedNotification',
            'notifiable_id' => $notifiable->id,
            'data' => $this->toArray($notifiable),
            'read_at' => null,
            'created_at' => now()->toISOString(),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => "You have been unassigned from property: {$this->property->area->name}",
            'property_id' => $this->property->id,
            'property_title' => $this->property->area->name,
            'property_price' => $this->property->price,
            'property_location' => $this->property->area->name,
            'unassigned_by_id' => $this->unassignedBy->id,
            'unassigned_by_name' => $this->unassignedBy->name,
            'notification_type' => 'property_unassigned',
            'timestamp' => now()->toISOString(),
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}