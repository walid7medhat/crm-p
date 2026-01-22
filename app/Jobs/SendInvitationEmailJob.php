<?php

namespace App\Jobs;

use App\Mail\UserInvitationMail;
use App\Models\UserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendInvitationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public UserInvitation $invitation)
    {
    }

    public function handle(): void
    {
        Mail::to($this->invitation->email)
            ->send(new UserInvitationMail($this->invitation));
    }
}