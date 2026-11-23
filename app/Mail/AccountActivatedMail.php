<?php

namespace App\Mail;

use App\Support\Brand\BrandUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountActivatedMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Sign-in site of the user's company (resolved now — the mail is queued). */
    public string $siteUrl;

    public string $companyName;

    public function __construct(
        public string $userEmail,
        public ?string $userName = null,
        ?\App\Models\User $user = null,
    ) {
        $this->siteUrl = BrandUrl::forUser($user) . '/';
        $brandKey = $user
            ? \App\Support\Brand\BrandIsolation::brandKeyOfUser($user)
            : app(\App\Support\Brand\BrandContext::class)->key();
        $this->companyName = $brandKey === 'luxvant' ? 'Luxvant Properties' : 'OIA Properties';
    }

    public function build()
    {
        return $this->subject("Your {$this->companyName} Listing Portal Account Is Now Active")
            ->view('emails.account-activated')
            ->with([
                'userName' => $this->userName,
                'siteUrl' => $this->siteUrl,
                'companyName' => $this->companyName,
            ]);
    }
}
