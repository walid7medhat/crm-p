<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\InternalUpdate;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\Listing;
use App\Models\ListingComment;
use App\Models\Stage;
use App\Models\User;
use App\Services\MyDay\MyDayService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class MyDayTest extends TestCase
{
    use DatabaseTransactions;

    private MyDayService $myDay;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Asia/Dubai']);
        Carbon::setTestNow(Carbon::parse('2026-10-13 15:00:00', 'Asia/Dubai'));
        Bus::fake();
        Event::fake();
        $this->myDay = app(MyDayService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_own_stage_change_is_included_and_someone_elses_is_not(): void
    {
        $user = $this->makeUser('sales');
        $other = $this->makeUser('sales');
        $lead = $this->makeLead($user);

        $this->history($lead, $user, ['action' => 'stage_changed', 'old_stage' => 'New', 'new_stage' => 'Contacted']);
        $this->history($lead, $other, ['action' => 'stage_changed', 'old_stage' => 'Contacted', 'new_stage' => 'Qualified']);

        $labels = collect($this->myDay->forUser($user)['actions'])->pluck('label');

        $this->assertTrue($labels->contains(fn ($label) => str_contains($label, 'You moved') && str_contains($label, 'Contacted')));
        $this->assertFalse($labels->contains(fn ($label) => str_contains($label, 'Qualified')));
        $this->assertSame([], $this->myDay->forUser($other)['actions']);
    }

    public function test_owning_the_lead_does_not_make_the_owner_the_actor(): void
    {
        $actor = $this->makeUser('manager');
        $sales = $this->makeUser('sales', $actor->id);
        $lead = $this->makeLead($sales);
        $this->history($lead, $actor, ['action' => 'comment_added', 'comment' => 'secret note']);

        $this->assertSame([], $this->myDay->forUser($sales)['actions']);
        $labels = collect($this->myDay->forUser($actor)['actions'])->pluck('label');
        $this->assertTrue($labels->contains(fn ($label) => str_contains($label, 'You added a comment')));
        $this->assertFalse($labels->contains(fn ($label) => str_contains($label, 'secret note')));
    }

    public function test_assignment_events_are_excluded(): void
    {
        $user = $this->makeUser('super_admin');
        $lead = $this->makeLead($user);
        $this->history($lead, $user, [
            'action' => 'assigned',
            'old_person' => 'Pool',
            'new_person' => $user->name,
        ]);

        $this->assertSame([], $this->myDay->forUser($user)['actions']);
    }

    public function test_sales_history_before_assignment_and_lead_pool_rows_stay_hidden(): void
    {
        $sales = $this->makeUser('sales');
        $lead = $this->makeLead($sales);
        $this->history($lead, $sales, ['action' => 'stage_changed', 'old_stage' => 'Old', 'new_stage' => 'Hidden']);
        $this->history($lead, $sales, ['action' => 'assigned', 'old_person' => 'Pool', 'new_person' => $sales->name]);
        $this->history($lead, $sales, [
            'action' => 'stage_changed',
            'old_stage' => 'Lead Pool',
            'new_stage' => 'Contacted',
            'from_lead_pool' => true,
        ]);
        $this->history($lead, $sales, ['action' => 'comment_added', 'comment' => 'after']);

        $labels = collect($this->myDay->forUser($sales)['actions'])->pluck('label');

        $this->assertFalse($labels->contains(fn ($label) => str_contains($label, 'Hidden')));
        $this->assertFalse($labels->contains(fn ($label) => str_contains($label, 'Lead Pool')));
        $this->assertTrue($labels->contains(fn ($label) => str_contains($label, 'You added a comment')));
    }

    public function test_actions_are_limited_to_the_current_dubai_day(): void
    {
        $user = $this->makeUser('sales');
        $lead = $this->makeLead($user);
        $yesterday = now()->subDay();
        LeadHistory::create([
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'changes' => ['action' => 'stage_changed', 'old_stage' => 'A', 'new_stage' => 'Yesterday'],
            'created_at' => $yesterday,
            'updated_at' => $yesterday,
        ]);
        $this->history($lead, $user, ['action' => 'stage_changed', 'old_stage' => 'A', 'new_stage' => 'Today']);

        $labels = collect($this->myDay->forUser($user)['actions'])->pluck('label');

        $this->assertTrue($labels->contains(fn ($label) => str_contains($label, 'Today')));
        $this->assertFalse($labels->contains(fn ($label) => str_contains($label, 'Yesterday')));
        $this->assertSame('Asia/Dubai', $this->myDay->forUser($user)['timezone']);
    }

    public function test_endpoint_uses_the_authenticated_user_only(): void
    {
        $user = $this->makeUser('sales');
        $other = $this->makeUser('sales');
        $this->history($this->makeLead($other), $other, ['action' => 'comment_added', 'comment' => 'theirs']);

        $token = JWTAuth::fromUser($user);
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/dashboard/my-day');

        $response->assertOk();
        $response->assertJsonPath('data.actions', []);
        $response->assertJsonPath('data.timezone', 'Asia/Dubai');
    }

    public function test_a_conversion_counts_only_when_this_user_created_the_deal_today(): void
    {
        $user = $this->makeUser('sales');
        $lead = $this->makeLead($user);
        $stageId = Stage::query()->where('stage_type', 'deal')->value('id');
        if (! $stageId) {
            $this->markTestSkipped('No deal stage.');
        }
        $deal = Deal::query()->create([
            'deal_name' => 'Converted deal',
            'deal_type' => 'primary',
            'stage_id' => $stageId,
            'status' => 'draft',
            'currency' => 'AED',
            'created_by' => $user->id,
            'added_by' => $user->id,
            'responsible_person_id' => $user->id,
            'lead_id' => $lead->id,
        ]);
        $this->history($lead, $user, ['action' => 'created'], $deal->id);
        $lead->forceFill(['converted_to_deal_id' => $deal->id, 'converted_at' => now()])->saveQuietly();

        $labels = collect($this->myDay->forUser($user)['actions'])->pluck('label');
        $this->assertSame(1, $labels->count());
        $this->assertTrue($labels->contains(fn ($label) => str_contains($label, 'You converted')));
    }

    public function test_standalone_my_day_navigation_is_removed(): void
    {
        $router = file_get_contents(base_path('resources/js/router.js'));
        $header = file_get_contents(base_path('resources/js/components/layout/header/index.vue'));
        $home = file_get_contents(base_path('resources/js/components/dashboard/home/StudioDashboard.vue'));
        $layer = file_get_contents(base_path('resources/js/components/daily-motivation/DailyEdgeLayer.vue'));

        $this->assertStringNotContainsString("'/my-day'", $router);
        $this->assertStringNotContainsString('My Day', $header);
        $this->assertStringNotContainsString('my-day', $home);
        $this->assertStringContainsString('DailyEdgeCard', $layer);
        $this->assertStringContainsString('/dashboard/my-day', $layer);
        $this->assertFileDoesNotExist(base_path('resources/js/pages/my-day/index.vue'));

        $this->assertStringContainsString('Your activity will appear here as you work today', $layer);
        $this->assertStringContainsString("Couldn't load today's activity.", $layer);
        $this->assertStringContainsString('My Day', $layer);
        $this->assertStringContainsString('openLeadView', $layer);
        $this->assertStringContainsString('openPropertyDetails', $layer);
        $this->assertStringContainsString('listing_id', $layer);
    }

    public function test_own_listing_work_shows_and_someone_elses_does_not(): void
    {
        $user = $this->makeUser('sales');
        $other = $this->makeUser('sales');
        $listing = $this->makeListing($user, ['status' => 'published', 'approved' => 1]);

        $this->listingActivity($listing, $user, 'updated', ['price' => 900000], ['price' => 800000]);
        $this->listingActivity($listing, $other, 'updated', ['unit_number' => 'Hidden']);
        $this->listingActivity($listing, $user, 'updated', ['agent_id' => $other->id], ['agent_id' => $user->id]);
        ListingComment::query()->create([
            'listing_id' => $listing->id,
            'user_id' => $user->id,
            'comment' => 'private listing note',
            'is_approved' => 1,
        ]);
        InternalUpdate::query()->create([
            'listing_id' => $listing->id,
            'user_id' => $user->id,
            'content' => 'private internal note',
        ]);

        $labels = collect($this->myDay->forUser($user)['actions'])->pluck('label');

        $this->assertTrue($labels->contains(fn ($label) => str_contains($label, 'You updated the price') && str_contains($label, 'Unit 1204')));
        $this->assertTrue($labels->contains(fn ($label) => str_contains($label, 'You added a comment')));
        $this->assertTrue($labels->contains(fn ($label) => str_contains($label, 'You added an internal note')));
        $this->assertFalse($labels->contains(fn ($label) => str_contains($label, 'private')));
        $this->assertFalse($labels->contains(fn ($label) => str_contains($label, 'agent')));

        $otherLabels = collect($this->myDay->forUser($other)['actions'])->pluck('label');
        $this->assertTrue($otherLabels->contains('You updated this listing on Unit 1204'));
        $this->assertFalse($otherLabels->contains(fn ($label) => str_contains($label, 'price') || str_contains($label, 'comment')));
    }

    public function test_a_private_listing_stays_hidden_from_someone_who_cannot_open_it(): void
    {
        $owner = $this->makeUser('sales');
        $other = $this->makeUser('sales');
        $listing = $this->makeListing($owner, ['status' => 'draft', 'approved' => 0]);
        $this->listingActivity($listing, $other, 'updated', ['price' => 1]);

        $this->assertSame([], $this->myDay->forUser($other)['actions']);
        $labels = collect($this->myDay->forUser($owner)['actions'])->pluck('label');
        $this->assertFalse($labels->contains(fn ($label) => str_contains($label, 'price')));
    }

    private function listingActivity(Listing $listing, User $actor, string $event, array $attributes, array $old = []): Activity
    {
        return Activity::query()->create([
            'log_name' => 'listing',
            'description' => $event,
            'subject_type' => Listing::class,
            'subject_id' => $listing->id,
            'causer_type' => User::class,
            'causer_id' => $actor->id,
            'event' => $event,
            'properties' => ['attributes' => $attributes, 'old' => $old],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeListing(User $owner, array $overrides = []): Listing
    {
        return Listing::withoutEvents(function () use ($owner, $overrides) {
            return Listing::query()->create(array_merge([
                'title' => 'Day Listing '.uniqid(),
                'unit_number' => '1204',
                'status' => 'published',
                'approved' => 1,
                'agent_id' => $owner->id,
                'added_by' => $owner->id,
            ], $overrides));
        });
    }

    private function history(Lead $lead, User $actor, array $changes, ?int $dealId = null): LeadHistory
    {
        return LeadHistory::create([
            'lead_id' => $lead->id,
            'deal_id' => $dealId,
            'user_id' => $actor->id,
            'changes' => $changes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeUser(string $role, ?int $parentId = null): User
    {
        $roleModel = Role::query()->where('name', $role)->first();
        if (! $roleModel) {
            $this->markTestSkipped('Role '.$role.' is not installed.');
        }

        $user = User::factory()->create([
            'name' => 'Personal '.$role.' '.uniqid(),
            'status' => 'active',
            'parent_id' => $parentId,
        ]);
        $user->assignRole($roleModel);

        return $user->fresh();
    }

    private function makeLead(User $owner): Lead
    {
        $stageId = Stage::query()->where('stage_type', 'lead')->where('id', '!=', 10)->value('id');
        if (! $stageId) {
            $this->markTestSkipped('No lead stage.');
        }

        return Lead::withoutEvents(function () use ($owner, $stageId) {
            return Lead::query()->create([
                'lead_name' => 'Personal Lead '.uniqid(),
                'first_name' => 'Day',
                'stage_id' => $stageId,
                'responsible_person_id' => $owner->id,
                'added_by' => $owner->id,
                'assignment_hold' => 0,
                'available_to_everyone' => 0,
                'no_answer_count' => 0,
                'lead_pool_visits' => 0,
                'notified_revert' => 0,
            ]);
        });
    }
}
