<?php

namespace App\Services\WebPush;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class LeadAssignmentWebPushSender
{
    /**
     * @param  array{type?: string, title?: string, body?: string, url?: string}  $overrides
     */
    public function sendToUser(int $userId, int $leadId, array $overrides = []): void
    {
        if (! $this->isEligibleUser($userId)) {
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

        $payload = json_encode($this->payload($leadId, $overrides), JSON_THROW_ON_ERROR);
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
     * @param  array{type?: string, title?: string, body?: string, url?: string}  $overrides
     * @return array{type: string, title: string, body: string, lead_id?: int, url?: string}
     */
    public function payload(int $leadId, array $overrides = []): array
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

        foreach (['type', 'title', 'body', 'url'] as $key) {
            if (isset($overrides[$key]) && is_string($overrides[$key]) && $overrides[$key] !== '') {
                $payload[$key] = $overrides[$key];
            }
        }

        return $payload;
    }

    public function leadViewUrl(int $leadId): string
    {
        return '/?lead='.$leadId;
    }

    /**
     * Any active CRM account. Role is not checked. The old test-user flag is ignored.
     */
    public function isEligibleUser(int $userId): bool
    {
        if ($userId < 1) {
            return false;
        }

        return User::query()->whereKey($userId)->where('status', 'active')->exists();
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
