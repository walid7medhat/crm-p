<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BirthdayCelebrationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user)
    {
    }

    public function build()
    {
        $name = $this->user->displayName() ?? $this->user->name;

        return $this->subject("🎉 Happy Birthday, {$name}!")
            ->view('emails.birthday-celebration')
            ->with([
                'userName' => $name,
            ]);
    }
}
