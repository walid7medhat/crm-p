<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DailyMotivationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private int $number,
        private string $bodyEn,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'daily_motivation',
            'title' => 'Daily Message',
            'message' => $this->bodyEn,
            'number' => $this->number,
            'body_en' => $this->bodyEn,
            'action_url' => null,
        ];
    }
}
