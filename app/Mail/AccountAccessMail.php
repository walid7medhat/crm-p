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
    ) {
    }

    public function build()
    {
        // Same layout as the admin emails (AdminEmailController) — plain text lines, only the text differs.
        // Users set their password themselves via "Forgot Password" on the sign-in page.
        return $this->subject('The new ALT CRM is now ready')
            ->view('emails.saas-notification-dark')
            ->with([
                'userName' => null, // "Hello,"
                'subtitle' => 'ALT CRM',
                'headline' => 'The new ALT CRM is now ready!',
                'bodyLines' => [
                    'You can access the new system using your company email address.',
                    'Steps to log in:',
                    '1. Visit the link below.',
                    '2. Enter your company email.',
                    '3. Click Forgot Password.',
                    '4. Set your new password.',
                    '5. Sign in and start using the new CRM.',
                    'Login URL:',
                ],
                'ctaText' => 'Go to Login',
                'ctaUrl' => $this->loginUrl,
                'fallbackUrl' => $this->loginUrl,
                'footerNote' => 'You’re receiving this message from Oia Properties.',
            ]);
    }
}
