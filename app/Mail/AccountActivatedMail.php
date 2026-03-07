<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountActivatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userEmail,
        public ?string $userName = null
    ) {
    }

    public function build()
    {
        return $this->subject('Your OIA Properties Listing Portal Account Is Now Active')
            ->view('emails.account-activated')
            ->with([
                'userName' => $this->userName,
            ]);
    }
}
