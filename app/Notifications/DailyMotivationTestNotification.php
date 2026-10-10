<?php

namespace App\Notifications;

use App\Models\MotivationMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DailyMotivationTestNotification extends Notification
{
    use Queueable;

    public function __construct(private MotivationMessage $message) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $preview = mb_substr(trim($this->message->body_en), 0, 140);

        return [
            'type' => 'daily_motivation_test',
            'title' => 'Daily Message test',
            'message' => 'Private test · message '.$this->message->number.'. '.$preview,
            'number' => $this->message->number,
            'body_en' => $this->message->body_en,
            'body_ar' => $this->message->body_ar,
            'subtitle_en' => $this->message->subtitle_en,
            'subtitle_ar' => $this->message->subtitle_ar,
            'action_url' => '/daily-motivation',
        ];
    }
}
