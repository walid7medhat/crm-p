<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notificationData;

    public function __construct($notificationData)
    {
        $this->notificationData = $notificationData;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('App.Models.User.' . $this->notificationData['user_id']);
    }

    // ⚠️ غير اسم الـ event إلى الـ default
    public function broadcastAs()
    {
        return 'BroadcastNotificationCreated'; // استخدم الاسم الافتراضي
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->notificationData['id'],
            'type' => get_class($this),
            'data' => $this->notificationData,
            'read_at' => null,
            'created_at' => now()->toISOString(),
        ];
    }
}