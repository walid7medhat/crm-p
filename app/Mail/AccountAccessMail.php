<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * "Your CRM account" email: login email + sign-in link + a link to set/reset the password.
 * Sent by `php artisan users:send-account-email` and Settings → Account Emails.
 * Uses the shared Oia notification template (emails.saas-notification-dark).
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
        $subject = 'Your Oia Properties CRM Account';

        // Same layout as the admin emails (AdminEmailController) — plain text lines, only the text differs.
        return $this->subject($subject)
            ->view('emails.saas-notification-dark')
            ->with([
                'userName' => $this->userName,
                'subtitle' => 'Account',
                'headline' => $subject,
                'bodyLines' => [
                    'You have an active account on the Oia Properties CRM.',
                    "Login email: {$this->userEmail}",
                    "Sign in at: {$this->loginUrl}",
                    "If you don't know your password, use the button below to set a new one. "
                        . "The link works for {$this->expiresInDays} days; after that, use \"Forgot password\" on the sign-in page.",
                ],
                'ctaText' => 'Set / Reset Password',
                'ctaUrl' => $this->resetUrl,
                'fallbackUrl' => $this->resetUrl,
                'footerNote' => 'You’re receiving this message from Oia Properties.',
            ]);
    }
}
