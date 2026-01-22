<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RequestCancelledNotification extends Notification // احذف implements ShouldQueue
{
    use Queueable;

    public $notificationData;

    public function __construct($notificationData)
    {
        $this->notificationData = $notificationData;
        
        \Log::info('🎯 RequestCancelledNotification Constructor', [
            'data' => $notificationData,
            'time' => now()->toISOString()
        ]);
    }

    public function via(object $notifiable): array
    {
        \Log::info('📢 RequestCancelledNotification via method', [
            'user_id' => $notifiable->id,
            'channels' => ['database', 'broadcast']
        ]);
        
        return ['database', 'broadcast'];
    }

    public function toBroadcast(object $notifiable)
    {
        \Log::info('🔊 RequestCancelledNotification toBroadcast', [
            'user_id' => $notifiable->id,
            'notification_id' => $this->id
        ]);
        
        $broadcastData = [
            'id' => $this->id,
            'type' => 'App\\Notifications\\RequestCancelledNotification',
            'data' => $this->toArray($notifiable),
            'read_at' => null,
            'created_at' => now()->toISOString(),
        ];
        
        \Log::info('📤 Broadcast Data', $broadcastData);
        
        return $broadcastData;
    }

    public function toArray(object $notifiable): array
    {
        $arrayData = [
            'message' => "Request for {$this->notificationData['request_type_text']} has been cancelled by {$this->notificationData['cancelled_by_name']}",
            'request_id' => $this->notificationData['request_id'],
            'property_id' => $this->notificationData['property_id'],
            'property_title' => $this->notificationData['property_title'],
            'request_type' => $this->notificationData['request_type'],
            'request_type_text' => $this->notificationData['request_type_text'],
            'cancelled_by_name' => $this->notificationData['cancelled_by_name'],
            'cancelled_by_id' => $this->notificationData['cancelled_by_id'],
            'cancelled_at' => $this->notificationData['cancelled_at']->toISOString(),
            'notification_type' => 'request_cancelled',
            'timestamp' => now()->toISOString(),
        ];
        
        \Log::info('📝 RequestCancelledNotification toArray', $arrayData);
        
        return $arrayData;
    }

    public function toDatabase($notifiable)
    {
        \Log::info('💾 RequestCancelledNotification toDatabase', [
            'user_id' => $notifiable->id
        ]);
        
        return $this->toArray($notifiable);
    }
}