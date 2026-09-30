<?php

namespace App\Services\WebPush;

use App\Models\PushSubscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class LeadAssignmentWebPushSender
{
    public function sendToUser(int $userId, int $leadId): void
    {
        if (! $this->isTestRecipient($userId)) {
            return;
        }

        $publicKey = (string) config('services.web_push.public_key');
        $privateKey = (string) config('services.web_push.private_key');
        if ($publicKey === '' || $privateKey === '') {
            Log::warning('lead_assignment.test_web_push_unconfigured', [
                'user_id' => $userId,
            ]);

            return;
        }

        $subscriptions = PushSubscription::query()->where('user_id', $userId)->get();
        if ($subscriptions->isEmpty()) {
            return;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => (string) config('services.web_push.subject'),
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);
        $webPush->setReuseVAPIDHeaders(true);

        $payload = json_encode($this->payload($leadId), JSON_THROW_ON_ERROR);
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
            $this->handleReport($subscriptions, $report);
        }
    }

    /**
     * Routing only. No customer name, phone, or email.
     *
     * @return array{type: string, title: string, body: string, lead_id?: int, url?: string}
     */
    public function payload(int $leadId): array
    {
        $payload = [
            'type' => 'lead_assignment',
            'title' => 'New Lead Assigned',
            'body' => 'A new lead has been assigned to you.',
        ];

        if ($leadId > 0) {
            $payload['lead_id'] = $leadId;
            $payload['url'] = $this->leadViewUrl($leadId);
        }

        return $payload;
    }

    public function leadViewUrl(int $leadId): string
    {
        return '/?lead='.$leadId;
    }

    public function isTestRecipient(int $userId): bool
    {
        if (! config('services.lead_assignment_test.enabled')) {
            return false;
        }

        $testUserId = (int) config('services.lead_assignment_test.user_id');

        return $testUserId > 0 && $userId === $testUserId;
    }

    /**
     * @param  Collection<int, PushSubscription>  $subscriptions
     */
    public function handleReport(Collection $subscriptions, MessageSentReport $report): void
    {
        $endpoint = $report->getEndpoint();
        $match = $subscriptions->first(fn (PushSubscription $subscription) => $subscription->endpoint === $endpoint);

        if ($report->isSubscriptionExpired()) {
            $match?->delete();
            Log::info('lead_assignment.test_web_push_subscription_removed', [
                'subscription_id' => $match?->id,
            ]);

            return;
        }

        if (! $report->isSuccess()) {
            Log::warning('lead_assignment.test_web_push_failed', [
                'subscription_id' => $match?->id,
                'status' => $report->getResponse()?->getStatusCode(),
            ]);
        }
    }
}
