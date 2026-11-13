<?php

namespace App\Jobs;

use App\Mail\UserInvitationMail;
use App\Models\UserInvitation;
use App\Support\Brand\Concerns\BrandAwareJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendInvitationEmailJob implements ShouldQueue
{
    use BrandAwareJob;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public UserInvitation $invitation)
    {
        $this->initializeBrandContext();
    }

    public function handle(): void
    {
        $this->runWithBrandContext(function () {
            Mail::to($this->invitation->email)
                ->send(new UserInvitationMail($this->invitation));
        });
    }
}
