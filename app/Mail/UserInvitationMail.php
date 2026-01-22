<?php

namespace App\Mail;

use App\Models\UserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public UserInvitation $invitation)
    {
    }

    public function build()
    {
        return $this->subject('Invitation to Join Our System')
                    ->markdown('emails.user-invitation')
                    ->with([
                        'invitationUrl' => $this->getInvitationUrl(),
                        'expiresAt' => $this->invitation->expires_at->format('Y-m-d H:i'),
                    ]);
    }

    private function getInvitationUrl(): string
    {
        return url("/register/{$this->invitation->token}");
    }
}