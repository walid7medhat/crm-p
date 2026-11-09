<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * "Your CRM account" email: login email + sign-in link + a link to set/reset the password.
 * Sent by `php artisan users:send-account-email`.
 */
class AccountAccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userEmail,
        public ?string $userName,
        public string $loginUrl,
        public string $resetUrl,
        public int $expiresInDays,
    ) {
    }

    public function build()
    {
        return $this->subject('Your OIA Properties CRM Account')
            ->view('emails.account-access');
    }
}
