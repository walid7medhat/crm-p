<?php

namespace App\Console\Commands;

use App\Models\PushSubscription;
use App\Services\WebPush\LeadAssignmentWebPushSender;
use Illuminate\Console\Command;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class TestWebPushCommand extends Command
{
    protected $signature = 'push:test {user_id? : Defaults to PUSHER_LEAD_ASSIGNMENT_TEST_USER_ID} {--lead=0 : Lead id used only to open that lead view}';

    protected $description = 'Send a test lead-assignment Web Push right now (no queue) and print each step';

    public function handle(LeadAssignmentWebPushSender $sender): int
    {
        $userId = (int) ($this->argument('user_id') ?: config('services.lead_assignment_test.user_id'));

        $this->line('Target user id: '.$userId);

        if ($userId < 1 || ! $sender->isEligibleUser($userId)) {
            $this->error('User '.$userId.' is not an active CRM account.');

            return self::FAILURE;
        }

        $publicKey = (string) config('services.web_push.public_key');
        $privateKey = (string) config('services.web_push.private_key');
        if ($publicKey === '' || $privateKey === '') {
            $this->error('WEB_PUSH_VAPID_PUBLIC_KEY / WEB_PUSH_VAPID_PRIVATE_KEY are empty.');

            return self::FAILURE;
        }

        $subscriptions = PushSubscription::query()->where('user_id', $userId)->get();
        $this->line('Registered devices: '.$subscriptions->count());
        if ($subscriptions->isEmpty()) {
            $this->error('No device registered. Open the CRM on the phone as this user and tap the bell-plus icon.');

            return self::FAILURE;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => (string) config('services.web_push.subject'),
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);

        $payload = json_encode($sender->payload((int) $this->option('lead')), JSON_THROW_ON_ERROR);
        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding ?: 'aes128gcm',
                ]),
                $payload
            );
        }

        foreach ($webPush->flush() as $report) {
            $host = parse_url($report->getEndpoint(), PHP_URL_HOST);
            if ($report->isSuccess()) {
                $this->info("Sent to {$host}");
            } else {
                $status = $report->getResponse()?->getStatusCode() ?? 'no response';
                $this->error("Failed for {$host} ({$status}): ".$report->getReason());
            }
            $sender->handleReport($subscriptions, $report);
        }

        return self::SUCCESS;
    }
}
