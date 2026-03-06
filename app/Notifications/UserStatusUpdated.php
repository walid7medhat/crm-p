<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class UserStatusUpdated extends Notification
{
    use Queueable;

    protected $status;

    public function __construct($status)
    {
        $this->status = $status;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

   public function toMail($notifiable)
        {
            return (new MailMessage)
                        ->subject('Your Account is Now Active')
                        ->view('emails.user_status_active', ['user' => $notifiable]);
        }
}