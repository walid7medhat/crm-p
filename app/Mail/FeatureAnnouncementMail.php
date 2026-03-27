<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FeatureAnnouncementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $ctaUrl,
    ) {}

    public function build()
    {
        $bodyLines = [
            'We’re excited to share a new feature that makes collaboration faster and more context-aware.',
            'You can now chat with any agent on the platform, and you can also start a chat directly from a specific property so everyone immediately understands the context.',
            'Path: Property Details → Property Actions → Chat with Agent',
        ];

        return $this->subject('New Feature Available: Chat with Agents & Property Context')
            ->view('emails.saas-notification-dark')
            ->with([
                'userName' => $this->recipientName,
                'subtitle' => 'New Feature Available',
                'headline' => 'Chat with agents — with property context',
                'bodyLines' => $bodyLines,
                'ctaText' => 'Start Chatting Now',
                'ctaUrl' => $this->ctaUrl,
                'fallbackUrl' => $this->ctaUrl,
                'footerNote' => 'You’re receiving this email because you have an active account on Oia Properties Listing Portal.',
            ]);
    }
}

