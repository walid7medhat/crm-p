<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BirthdayColleagueNotification extends Notification
{
    use Queueable;

    protected User $birthdayUser;

    public function __construct(User $birthdayUser)
    {
        $this->birthdayUser = $birthdayUser;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $name = $this->birthdayUser->displayName() ?? $this->birthdayUser->name;

        return [
            'type' => 'birthday_colleague',
            'title' => 'Birthday Today',
            'message' => "🎂 Today is {$name}'s birthday! Say hi and wish them well.",
            'birthday_user_id' => $this->birthdayUser->id,
            'birthday_user_name' => $name,
            'birthday_user_avatar' => $this->birthdayUser->avatar_url,
        ];
    }
}
