<?php

namespace Tests\Feature;

use App\Events\LeadUpdated;
use App\Jobs\SendLeadAssignmentWebPush;
use App\Models\Lead;
use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\AssignmentPocNotification;
use App\Services\WebPush\LeadAssignmentWebPushSender;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Minishlink\WebPush\MessageSentReport;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class LeadAssignmentWebPushTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.lead_assignment_test.enabled', true);
        Config::set('services.lead_assignment_test.user_id', 1);
        Config::set('services.web_push.subject', 'mailto:admin@oiaproperties.com');
        Config::set('services.web_push.public_key', 'test-public');
        Config::set('services.web_push.private_key', 'test-private');

        Event::fake([LeadUpdated::class]);
    }

    public function test_subscription_requires_authentication(): void
    {
        $this->postJson('/api/push-subscriptions', [
            'endpoint' => 'https://push.example.test/guest',
            'keys' => ['p256dh' => 'public-key', 'auth' => 'auth-secret'],
        ])->assertStatus(401);
    }

    public function test_subscription_is_stored_for_the_authenticated_user_only(): void
    {
        $user = $this->activeUser(1);
        $otherId = (int) User::query()->where('id', '!=', $user->id)->value('id');
        $endpoint = 'https://push.example.test/device-'.uniqid();

        $response = $this->asUser($user)->postJson('/api/push-subscriptions', [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'public-key', 'auth' => 'auth-secret'],
            'user_id' => $otherId,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.subscribed', true);
        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint_hash' => hash('sha256', $endpoint),
        ]);
        $this->assertDatabaseMissing('push_subscriptions', [
            'user_id' => $otherId,
            'endpoint_hash' => hash('sha256', $endpoint),
        ]);
        $response->assertJsonMissing(['auth-secret']);
    }

    public function test_another_user_cannot_register_during_test_mode(): void
    {
        $other = User::query()->where('id', '!=', 1)->where('status', 'active')->first();
        if (! $other) {
            $this->markTestSkipped('No second active user.');
        }

        $response = $this->asUser($other)->postJson('/api/push-subscriptions', [
            'endpoint' => 'https://push.example.test/other-'.uniqid(),
            'keys' => ['p256dh' => 'public-key', 'auth' => 'auth-secret'],
            'user_id' => 1,
        ]);

        $response->assertStatus(403);
        $this->assertSame(0, PushSubscription::query()->where('user_id', $other->id)->count());
    }

    public function test_config_hides_the_public_key_from_other_users(): void
    {
        $other = User::query()->where('id', '!=', 1)->where('status', 'active')->first();
        if (! $other) {
            $this->markTestSkipped('No second active user.');
        }

        $this->asUser($other)->getJson('/api/push-subscriptions/config')
            ->assertOk()
            ->assertJsonPath('data.eligible', false)
            ->assertJsonPath('data.public_key', null);

        $this->asUser($this->activeUser(1))->getJson('/api/push-subscriptions/config')
            ->assertOk()
            ->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.public_key', 'test-public');
    }

    public function test_payload_opens_the_lead_view_without_customer_data(): void
    {
        $sender = app(LeadAssignmentWebPushSender::class);
        $payload = $sender->payload(55);

        $this->assertSame([
            'type' => 'lead_assignment',
            'title' => 'New Lead Assigned',
            'body' => 'A new lead has been assigned to you.',
            'lead_id' => 55,
            'url' => '/?lead=55',
        ], $payload);
        $this->assertSame('/?lead=55', $sender->leadViewUrl(55));
        foreach (['phone', 'email', 'lead_name', 'client', 'work_phone'] as $key) {
            $this->assertArrayNotHasKey($key, $payload);
        }
    }

    public function test_expired_subscription_is_removed_without_touching_other_devices(): void
    {
        $kept = $this->makeSubscription(1, 'https://push.example.test/kept');
        $expired = $this->makeSubscription(1, 'https://push.example.test/expired');

        $report = new MessageSentReport(
            new PsrRequest('POST', $expired->endpoint),
            new PsrResponse(410),
            false,
            'gone'
        );

        app(LeadAssignmentWebPushSender::class)->handleReport(
            PushSubscription::query()->where('user_id', 1)->get(),
            $report
        );

        $this->assertDatabaseMissing('push_subscriptions', ['id' => $expired->id]);
        $this->assertDatabaseHas('push_subscriptions', ['id' => $kept->id]);
    }

    public function test_sender_refuses_users_other_than_the_test_recipient(): void
    {
        $sender = app(LeadAssignmentWebPushSender::class);

        $this->assertTrue($sender->isTestRecipient(1));
        $this->assertFalse($sender->isTestRecipient(2));

        Config::set('services.lead_assignment_test.enabled', false);
        $this->assertFalse($sender->isTestRecipient(1));
    }

    public function test_web_push_failure_does_not_fail_assignment_and_pusher_still_sends(): void
    {
        Notification::fake();
        Bus::fake()->except([SendLeadAssignmentWebPush::class]);
        $this->mock(LeadAssignmentWebPushSender::class, function ($mock) {
            $mock->shouldReceive('sendToUser')->once()->andThrow(new \RuntimeException('push down'));
        });

        $lead = Lead::query()->first();
        $otherUserId = (int) User::query()->where('id', '!=', 1)->value('id');
        if (! $lead || $otherUserId < 1) {
            $this->markTestSkipped('Need a lead and a second user.');
        }
        if ((int) $lead->responsible_person_id === 1) {
            $lead->update(['responsible_person_id' => $otherUserId]);
            $lead = $lead->fresh();
        }

        $actor = User::role('super_admin')->where('status', 'active')->first();
        if (! $actor) {
            $this->markTestSkipped('No active super admin.');
        }

        $response = $this->withHeader('Authorization', 'Bearer '.JWTAuth::fromUser($actor))
            ->postJson('/api/leads/'.$lead->id.'/assign-responsible-person', [
                'responsible_person_id' => 1,
            ]);

        $response->assertOk();
        $response->assertJsonPath('status', true);
        $this->assertSame(1, (int) $lead->fresh()->responsible_person_id);
        Notification::assertSentTo(User::query()->find(1), AssignmentPocNotification::class);
    }

    private function activeUser(int $id): User
    {
        $user = User::query()->where('id', $id)->where('status', 'active')->first();
        if (! $user) {
            $this->markTestSkipped('User '.$id.' is not an active account.');
        }

        return $user;
    }

    private function asUser(User $user): self
    {
        return $this->withHeader('Authorization', 'Bearer '.JWTAuth::fromUser($user));
    }

    private function makeSubscription(int $userId, string $endpoint): PushSubscription
    {
        return PushSubscription::query()->create([
            'user_id' => $userId,
            'endpoint_hash' => hash('sha256', $endpoint),
            'endpoint' => $endpoint,
            'public_key' => 'public-key',
            'auth_token' => 'auth-secret',
            'content_encoding' => 'aes128gcm',
        ]);
    }
}
