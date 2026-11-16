<?php

namespace Tests\Feature;

use App\Jobs\SendLeadAssignmentWebPush;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\DubaiNewLeadAlertNotification;
use App\Services\WebPush\DubaiNewLeadNotifier;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DubaiNewLeadAlertNotificationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.dubai_new_lead_alert.enabled', true);
        Config::set('services.dubai_new_lead_alert.user_ids', [195]);
        Config::set('lead_merge.new_lead_stage_id', 3);
    }

    public function test_payload_says_you_have_a_new_lead_and_is_not_a_lead_star_notification(): void
    {
        $notification = new DubaiNewLeadAlertNotification(99);
        $payload = $notification->toBroadcast(new User())->data;

        $this->assertSame(['database', 'broadcast'], $notification->via(new User()));
        $this->assertSame('You have a new lead', $payload['title']);
        $this->assertSame('You have a new lead', $payload['message']);
        $this->assertSame(99, $payload['lead_id']);
        $this->assertDoesNotMatchRegularExpression(
            '/\\\\Lead\\w*Notification$/',
            DubaiNewLeadAlertNotification::class
        );
    }

    public function test_dubai_new_lead_notifies_configured_watchers(): void
    {
        Notification::fake();
        Bus::fake();

        $recipient = User::query()->find(195);
        if (! $recipient || $recipient->status !== 'active') {
            $this->markTestSkipped('User 195 is not an active recipient.');
        }

        $lead = new Lead([
            'id' => 999001,
            'stage_id' => 3,
            'branch' => 'Dubai',
            'lead_branch_source' => 'Dubai',
            'responsible_person_id' => 59,
        ]);
        $lead->exists = true;

        app(DubaiNewLeadNotifier::class)->notifyIfEligible($lead);

        Notification::assertSentTo($recipient, DubaiNewLeadAlertNotification::class);
        Bus::assertDispatched(SendLeadAssignmentWebPush::class, function (SendLeadAssignmentWebPush $job) {
            return $job->userId === 195
                && $job->leadId === 999001
                && $job->title === 'You have a new lead'
                && $job->type === 'dubai_new_lead';
        });
    }

    public function test_non_dubai_new_lead_does_not_notify(): void
    {
        Notification::fake();
        Bus::fake();

        $lead = new Lead([
            'id' => 999002,
            'stage_id' => 3,
            'branch' => 'Abu Dhabi',
            'lead_branch_source' => 'Abu Dhabi',
            'responsible_person_id' => 25,
        ]);
        $lead->exists = true;

        app(DubaiNewLeadNotifier::class)->notifyIfEligible($lead);

        Notification::assertNothingSent();
        Bus::assertNotDispatched(SendLeadAssignmentWebPush::class);
    }

    public function test_dubai_lead_outside_new_lead_stage_does_not_notify(): void
    {
        Notification::fake();
        Bus::fake();

        $lead = new Lead([
            'id' => 999003,
            'stage_id' => 4,
            'branch' => 'Dubai',
            'lead_branch_source' => 'Dubai',
            'responsible_person_id' => 59,
        ]);
        $lead->exists = true;

        app(DubaiNewLeadNotifier::class)->notifyIfEligible($lead);

        Notification::assertNothingSent();
        Bus::assertNotDispatched(SendLeadAssignmentWebPush::class);
    }

    public function test_already_eligible_update_does_not_notify_again(): void
    {
        Notification::fake();
        Bus::fake();

        $lead = new Lead([
            'id' => 999004,
            'stage_id' => 3,
            'branch' => 'Dubai',
            'lead_branch_source' => null,
            'responsible_person_id' => 59,
        ]);
        $lead->exists = true;
        $lead->syncOriginal();
        $lead->lead_branch_source = 'Dubai';

        app(DubaiNewLeadNotifier::class)->notifyIfEligible($lead, onlyIfNewlyEligible: true);

        Notification::assertNothingSent();
        Bus::assertNotDispatched(SendLeadAssignmentWebPush::class);
    }
}
