<?php

namespace App\Jobs;

use App\Services\WebPush\LeadAssignmentWebPushSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendLeadAssignmentWebPush implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $userId)
    {
    }

    public function handle(LeadAssignmentWebPushSender $sender): void
    {
        try {
            $sender->sendToUser($this->userId);
        } catch (\Throwable $e) {
            Log::warning('lead_assignment.test_web_push_failed', [
                'user_id' => $this->userId,
                'error' => $e::class,
            ]);
        }
    }
}
