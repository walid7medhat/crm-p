<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BirthdaySelfNotification extends Notification
{
    use Queueable;

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $name = $notifiable->displayName() ?? $notifiable->name;

        return [
            'type' => 'birthday_self',
            'title' => 'Happy Birthday!',
            'message' => "🎉 Happy Birthday, {$name}! Wishing you a fantastic day.",
            'user_id' => $notifiable->id,
            'avatar' => $notifiable->avatar_url,
        ];
    }
}
