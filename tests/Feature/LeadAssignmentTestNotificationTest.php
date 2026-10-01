<?php

namespace Tests\Feature;

use App\Events\LeadUpdated;
use App\Jobs\SendLeadAssignmentWebPush;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\AssignmentPocNotification;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Manual assignment test toast. Rolls back every lead write on the live database.
 */
class LeadAssignmentTestNotificationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.lead_assignment_test.enabled', true);
        Config::set('services.lead_assignment_test.user_id', 1);

        Event::fake([LeadUpdated::class]);
        Bus::fake();
    }

    public function test_notification_payload_has_no_lead_data_and_is_not_suppressed_for_super_admins(): void
    {
        $notification = new AssignmentPocNotification();
        $payload = $notification->toBroadcast(new User())->data;

        $this->assertSame(['broadcast'], $notification->via(new User()));
        $this->assertSame('New Lead Assigned', $payload['title']);
        $this->assertSame(
            'New Lead Assigned — You have a new lead assigned to you.',
            $payload['message']
        );
        $this->assertSame(['title', 'message'], array_keys($payload));
        $this->assertDoesNotMatchRegularExpression(
            '/\\\\Lead\\w*Notification$/',
            AssignmentPocNotification::class
        );
    }

    public function test_only_the_new_assignee_is_notified_when_the_responsible_person_changes(): void
    {
        Notification::fake();

        $testUserId = $this->testUserId();
        $recipient = User::query()->find($testUserId);
        $lead = $this->leadAssignedToSomeoneElse($testUserId);

        $response = $this->assignLead($lead, $testUserId);

        $response->assertOk();
        $response->assertJsonPath('status', true);
        $this->assertSame($testUserId, (int) $lead->fresh()->responsible_person_id);
        $this->assertSame('user.'.$testUserId, $recipient->receivesBroadcastNotificationsOn());

        Notification::assertSentTo(
            $recipient,
            AssignmentPocNotification::class,
            function (AssignmentPocNotification $notification) use ($recipient) {
                $payload = $notification->toBroadcast($recipient)->data;

                return $payload['title'] === 'New Lead Assigned'
                    && $payload['message'] === 'New Lead Assigned — You have a new lead assigned to you.';
            }
        );
        Notification::assertCount(1);
        Bus::assertDispatched(SendLeadAssignmentWebPush::class, function (SendLeadAssignmentWebPush $job) use ($testUserId, $lead) {
            return $job->userId === $testUserId && $job->leadId === (int) $lead->id;
        });
    }

    public function test_another_assignee_receives_the_same_notification(): void
    {
        Notification::fake();

        $otherUserId = (int) User::query()->where('id', '!=', 1)->where('status', 'active')->value('id');
        if ($otherUserId < 1) {
            $this->markTestSkipped('No second active user.');
        }

        $lead = $this->leadAssignedToSomeoneElse($otherUserId);
        $response = $this->assignLead($lead, $otherUserId);

        $response->assertOk();
        $response->assertJsonPath('status', true);
        $this->assertSame($otherUserId, (int) $lead->fresh()->responsible_person_id);
        Notification::assertSentTo(User::query()->find($otherUserId), AssignmentPocNotification::class);
        Bus::assertDispatchedTimes(SendLeadAssignmentWebPush::class, 1);
        Bus::assertDispatched(SendLeadAssignmentWebPush::class, function (SendLeadAssignmentWebPush $job) use ($otherUserId, $lead) {
            return $job->userId === $otherUserId && $job->leadId === (int) $lead->id;
        });
    }

    public function test_reassigning_the_same_user_sends_nothing(): void
    {
        Notification::fake();

        $testUserId = $this->testUserId();
        $lead = Lead::query()->first();
        if (! $lead) {
            $this->markTestSkipped('No lead available.');
        }

        $lead->update(['responsible_person_id' => $testUserId]);
        Notification::fake();
        Bus::fake();
        $response = $this->assignLead($lead->fresh(), $testUserId);

        $response->assertOk();
        Notification::assertNothingSent();
        Bus::assertNotDispatched(SendLeadAssignmentWebPush::class);
    }

    public function test_changing_the_assignee_on_the_lead_notifies_without_the_assign_endpoint(): void
    {
        Notification::fake();

        $testUserId = $this->testUserId();
        $lead = $this->leadAssignedToSomeoneElse($testUserId);
        $recipient = User::query()->find($testUserId);

        $lead->update(['responsible_person_id' => $testUserId]);

        Notification::assertSentTo($recipient, AssignmentPocNotification::class);
        Bus::assertDispatched(SendLeadAssignmentWebPush::class, function (SendLeadAssignmentWebPush $job) use ($testUserId, $lead) {
            return $job->userId === $testUserId && $job->leadId === (int) $lead->id;
        });
        Bus::assertDispatchedTimes(SendLeadAssignmentWebPush::class, 1);
    }

    public function test_sales_admin_and_super_admin_assignees_each_receive_one_push(): void
    {
        foreach (['sales', 'admin', 'super_admin'] as $role) {
            Notification::fake();
            Bus::fake();

            $assignee = User::role($role)->where('status', 'active')->first();
            if (! $assignee) {
                $this->markTestSkipped('No active '.$role.' user.');
            }

            $lead = $this->leadAssignedToSomeoneElse((int) $assignee->id);
            $lead->update(['responsible_person_id' => $assignee->id]);

            Notification::assertSentTo($assignee, AssignmentPocNotification::class);
            Bus::assertDispatchedTimes(SendLeadAssignmentWebPush::class, 1);
            Bus::assertDispatched(SendLeadAssignmentWebPush::class, function (SendLeadAssignmentWebPush $job) use ($assignee, $lead) {
                return $job->userId === (int) $assignee->id && $job->leadId === (int) $lead->id;
            });
        }
    }

    public function test_test_mode_off_still_notifies_the_new_assignee(): void
    {
        Notification::fake();
        $assigneeId = (int) User::query()->where('status', 'active')->orderBy('id')->value('id');
        if ($assigneeId < 1) {
            $this->markTestSkipped('No active user.');
        }
        Config::set('services.lead_assignment_test.enabled', false);
        Config::set('services.lead_assignment_test.user_id', null);

        $lead = $this->leadAssignedToSomeoneElse($assigneeId);
        $response = $this->assignLead($lead, $assigneeId);

        $response->assertOk();
        $response->assertJsonPath('status', true);
        $this->assertSame($assigneeId, (int) $lead->fresh()->responsible_person_id);
        Notification::assertSentTo(User::query()->find($assigneeId), AssignmentPocNotification::class);
        Bus::assertDispatchedTimes(SendLeadAssignmentWebPush::class, 1);
    }

    public function test_pusher_failure_does_not_fail_the_assignment(): void
    {
        $testUserId = $this->testUserId();
        $lead = $this->leadAssignedToSomeoneElse($testUserId);

        $dispatcher = \Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('send')->once()->andThrow(new \RuntimeException('Pusher connection failed'));
        $this->app->instance(Dispatcher::class, $dispatcher);

        $response = $this->assignLead($lead, $testUserId);

        $response->assertOk();
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('message', 'Responsible person assigned successfully');
        $this->assertSame($testUserId, (int) $lead->fresh()->responsible_person_id);
        Bus::assertDispatched(SendLeadAssignmentWebPush::class, function (SendLeadAssignmentWebPush $job) use ($testUserId, $lead) {
            return $job->userId === $testUserId && $job->leadId === (int) $lead->id;
        });
    }

    private function testUserId(): int
    {
        return (int) config('services.lead_assignment_test.user_id');
    }

    private function leadAssignedToSomeoneElse(int $assigneeId): Lead
    {
        $lead = Lead::query()->first();
        $otherUserId = (int) User::query()->where('id', '!=', $assigneeId)->value('id');
        if (! $lead || $otherUserId < 1) {
            $this->markTestSkipped('Need a lead and a second user.');
        }

        if ((int) $lead->responsible_person_id === $assigneeId) {
            $lead->update(['responsible_person_id' => $otherUserId]);
            $lead = $lead->fresh();
        }

        return $lead;
    }

    private function assignLead(Lead $lead, int $assigneeId): \Illuminate\Testing\TestResponse
    {
        $actor = User::role('super_admin')->where('status', 'active')->first();
        if (! $actor) {
            $this->markTestSkipped('No active super admin.');
        }

        $token = JWTAuth::fromUser($actor);

        return $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/leads/'.$lead->id.'/assign-responsible-person', [
                'responsible_person_id' => $assigneeId,
            ]);
    }
}
