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
    use \App\Support\Brand\Concerns\BrandAwareJob;

    public function __construct(
        public int $userId,
        public int $leadId,
        public ?string $title = null,
        public ?string $body = null,
        public ?string $type = null,
    ) {
        $this->initializeBrandContextForLead($leadId);
    }

    public function handle(LeadAssignmentWebPushSender $sender): void
    {
        $this->runWithBrandContext(function () use ($sender) {
            try {
                $sender->sendToUser($this->userId, $this->leadId, array_filter([
                    'title' => $this->title,
                    'body' => $this->body,
                    'type' => $this->type,
                ], static fn ($value) => $value !== null && $value !== ''));
            } catch (\Throwable $e) {
                Log::warning('lead_assignment.test_web_push_failed', [
                    'user_id' => $this->userId,
                    'error' => $e::class,
                ]);
            }
        });
    }
}
