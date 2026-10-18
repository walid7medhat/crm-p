<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Stage;
use App\Models\User;
use App\Services\SalesAction\SalesActionCenterService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class SalesActionCenterTest extends TestCase
{
    use DatabaseTransactions;

    public function test_overdue_follow_up_outranks_a_repeated_no_answer_and_clears_when_completed(): void
    {
        $agent = $this->makeUser('sales');
        $contacted = $this->stageId(3);
        if (! $contacted) {
            $this->markTestSkipped('No Contacted stage.');
        }

        $lead = $this->lead($agent, $contacted, [
            'interaction_result' => 'no_answer',
            'no_answer_count' => 3,
        ]);
        $activity = $this->activity($lead, $agent, now()->subDay());

        $overdue = $this->keys($agent, 'urgent_overdue');
        $noAnswer = $this->keys($agent, 'contacted_repeated_no_answer');

        $this->assertContains($lead->id, $overdue);
        $this->assertNotContains($lead->id, $noAnswer);

        $activity->forceFill(['is_completed' => true])->saveQuietly();

        $this->assertNotContains($lead->id, $this->keys($agent, 'urgent_overdue'));
        $this->assertContains($lead->id, $this->keys($agent, 'contacted_repeated_no_answer'));
    }

    public function test_follow_up_due_today_is_separate_from_overdue(): void
    {
        $agent = $this->makeUser('sales');
        $contacted = $this->stageId(3);
        if (! $contacted) {
            $this->markTestSkipped('No Contacted stage.');
        }

        $lead = $this->lead($agent, $contacted);
        $this->activity($lead, $agent, now()->addHour());

        $this->assertContains($lead->id, $this->keys($agent, 'contacted_follow_up_due'));
        $this->assertNotContains($lead->id, $this->keys($agent, 'urgent_overdue'));
    }

    public function test_assigned_lead_without_activity_needs_first_contact_and_a_saved_activity_removes_it(): void
    {
        $agent = $this->makeUser('sales');
        $assigned = $this->stageId(2);
        if (! $assigned) {
            $this->markTestSkipped('No Assigned stage.');
        }

        $waiting = $this->lead($agent, $assigned, [
            'last_stage_change_at' => now()->subHours(6),
            'first_contacted_at' => null,
            'interaction_result' => null,
        ]);
        $touched = $this->lead($agent, $assigned, [
            'first_contacted_at' => null,
            'interaction_result' => null,
        ]);
        $this->activity($touched, $agent, now()->addDay());

        $ids = $this->keys($agent, 'assigned_needs_first_contact');
        $this->assertContains($waiting->id, $ids);
        $this->assertNotContains($touched->id, $ids);

        $row = collect($this->center($agent, 'assigned_needs_first_contact')['recommendations'])
            ->firstWhere('lead_id', $waiting->id);
        $this->assertSame('call_now', $row['key']);
        $this->assertStringContainsString('does not record a call', $row['explanation']);
    }

    public function test_qualified_missing_requirements_are_listed_and_complete_ones_are_not(): void
    {
        $agent = $this->makeUser('sales');
        $qualified = $this->stageId(4);
        if (! $qualified) {
            $this->markTestSkipped('No Qualified stage.');
        }

        $missing = $this->lead($agent, $qualified, [
            'area_id' => null,
            'property_type_id' => null,
            'bedrooms' => null,
            'budget' => null,
            'budget_from' => null,
            'budget_to' => null,
            'extra_client_requirements' => [],
        ]);
        $ready = $this->lead($agent, $qualified, [
            'area_id' => null,
            'budget_from' => 500000,
            'extra_client_requirements' => [],
        ]);

        $this->assertContains($missing->id, $this->keys($agent, 'qualified_missing_requirements'));
        $this->assertNotContains($ready->id, $this->keys($agent, 'qualified_missing_requirements'));

        $center = $this->center($agent);
        $keys = collect($center['categories'])->pluck('key')->all();
        $this->assertNotContains('qualified_ready_to_match', $keys);
    }

    public function test_converted_lead_without_a_deal_is_listed_and_a_linked_deal_is_not(): void
    {
        $agent = $this->makeUser('sales');
        $converted = $this->stageId(6);
        $dealStage = Stage::query()->where('stage_type', 'deal')->value('id');
        if (! $converted || ! $dealStage) {
            $this->markTestSkipped('No converted lead stage or deal stage.');
        }

        $missing = $this->lead($agent, $converted, ['converted_to_deal_id' => null]);
        $linked = $this->lead($agent, $converted, ['converted_to_deal_id' => null]);
        $deal = Deal::query()->create([
            'deal_name' => 'Existing deal '.uniqid(),
            'deal_type' => 'primary',
            'stage_id' => $dealStage,
            'status' => 'draft',
            'currency' => 'AED',
            'created_by' => $agent->id,
            'added_by' => $agent->id,
            'responsible_person_id' => $agent->id,
            'lead_id' => $linked->id,
        ]);
        $linked->forceFill(['converted_to_deal_id' => $deal->id])->saveQuietly();

        $ids = $this->keys($agent, 'converted_deal_missing');
        $this->assertContains($missing->id, $ids);
        $this->assertNotContains($linked->id, $ids);
    }

    public function test_inactivity_uses_last_engagement_and_ignores_updated_at(): void
    {
        $agent = $this->makeUser('sales');
        $shared = $this->stageId(7);
        if (! $shared) {
            $this->markTestSkipped('No Shared Leads stage.');
        }

        $quiet = $this->lead($agent, $shared, [
            'last_engagement_at' => now()->subDays(10),
            'updated_at' => now(),
        ]);
        $recent = $this->lead($agent, $shared, [
            'last_engagement_at' => now()->subHour(),
            'updated_at' => now()->subDays(30),
        ]);

        $ids = $this->keys($agent, 'inactive');
        $this->assertContains($quiet->id, $ids);
        $this->assertNotContains($recent->id, $ids);
    }

    public function test_lead_pool_is_excluded_and_comments_are_not_queried(): void
    {
        $agent = $this->makeUser('sales');
        $poolId = $this->poolStageId();
        if (! $poolId) {
            $this->markTestSkipped('No Lead Pool stage.');
        }

        $lead = $this->lead($agent, $poolId, [
            'interaction_result' => 'no_answer',
            'no_answer_count' => 3,
            'last_engagement_at' => now()->subDays(30),
        ]);
        $this->activity($lead, $agent, now()->subDay());

        $sql = [];
        DB::listen(function ($query) use (&$sql) {
            $sql[] = $query->sql;
        });

        $center = $this->center($agent, 'urgent_overdue');
        $ids = collect($center['recommendations'])->pluck('lead_id');

        $this->assertNotContains($lead->id, $ids->all());
        $this->assertFalse(collect($sql)->contains(fn ($statement) => str_contains($statement, 'lead_comments')));
        $this->assertLessThan(40, count($sql));
    }

    public function test_a_sales_agent_cannot_see_another_agents_leads_or_preview_them(): void
    {
        $agent = $this->makeUser('sales');
        $other = $this->makeUser('sales');
        $assigned = $this->stageId(2);
        if (! $assigned) {
            $this->markTestSkipped('No Assigned stage.');
        }

        $own = $this->lead($agent, $assigned);
        $foreign = $this->lead($other, $assigned);

        $ids = $this->keys($agent, 'assigned_needs_first_contact');
        $this->assertContains($own->id, $ids);
        $this->assertNotContains($foreign->id, $ids);

        $this->asUser($agent)
            ->getJson('/api/sales-action-center?preview_user_id='.$other->id)
            ->assertForbidden();
    }

    public function test_super_admin_preview_uses_the_agent_scope_and_does_not_write(): void
    {
        $admin = $this->makeUser('super_admin');
        $agent = $this->makeUser('sales');
        $other = $this->makeUser('sales');
        $assigned = $this->stageId(2);
        if (! $assigned) {
            $this->markTestSkipped('No Assigned stage.');
        }

        $visible = $this->lead($agent, $assigned);
        $hidden = $this->lead($other, $assigned);
        $before = Lead::query()->find($visible->id)->updated_at;

        $response = $this->asUser($admin)->getJson('/api/sales-action-center?preview_user_id='.$agent->id.'&category=assigned_needs_first_contact');
        $response->assertOk();
        $response->assertJsonPath('data.preview', true);
        $response->assertJsonPath('data.live', false);

        $ids = collect($response->json('data.recommendations'))->pluck('lead_id');
        $this->assertContains($visible->id, $ids->all());
        $this->assertNotContains($hidden->id, $ids->all());
        $this->assertTrue(Lead::query()->find($visible->id)->updated_at->equalTo($before));
    }

    public function test_two_overdue_activities_produce_one_recommendation(): void
    {
        $agent = $this->makeUser('sales');
        $contacted = $this->stageId(3);
        if (! $contacted) {
            $this->markTestSkipped('No Contacted stage.');
        }

        $lead = $this->lead($agent, $contacted);
        $this->activity($lead, $agent, now()->subDays(2));
        $this->activity($lead, $agent, now()->subDay());

        $matches = collect($this->center($agent, 'urgent_overdue')['recommendations'])
            ->where('lead_id', $lead->id);

        $this->assertCount(1, $matches);
        $this->assertSame('complete_overdue_follow_up', $matches->first()['key']);
        $this->assertNotEmpty($matches->first()['evidence']);
    }

    public function test_a_single_no_answer_is_a_call_again_and_an_answered_lead_goes_quiet_after_a_week(): void
    {
        $agent = $this->makeUser('sales');
        $contacted = $this->stageId(3);
        if (! $contacted) {
            $this->markTestSkipped('No Contacted stage.');
        }

        $once = $this->lead($agent, $contacted, [
            'interaction_result' => 'no_answer',
            'no_answer_count' => 1,
            'last_engagement_at' => now()->subDays(2),
        ]);
        $answeredQuiet = $this->lead($agent, $contacted, [
            'interaction_result' => 'answered',
            'last_engagement_at' => now()->subDays(10),
        ]);
        $answeredRecent = $this->lead($agent, $contacted, [
            'interaction_result' => 'answered',
            'last_engagement_at' => now()->subDay(),
        ]);

        $this->assertContains($once->id, $this->keys($agent, 'no_answer_call_again'));
        $this->assertNotContains($once->id, $this->keys($agent, 'contacted_repeated_no_answer'));
        $this->assertNotContains($once->id, $this->keys($agent, 'inactive'));

        $this->assertContains($answeredQuiet->id, $this->keys($agent, 'answered_gone_quiet'));
        $this->assertNotContains($answeredQuiet->id, $this->keys($agent, 'inactive'));
        $this->assertNotContains($answeredRecent->id, $this->keys($agent, 'answered_gone_quiet'));

        $row = collect($this->center($agent, 'answered_gone_quiet')['recommendations'])
            ->firstWhere('lead_id', $answeredQuiet->id);
        $this->assertSame('call_again_after_silence', $row['key']);
        $this->assertStringContainsString('Answered', $row['card']['reason']);
    }

    private function center(User $user, ?string $category = null): array
    {
        return app(SalesActionCenterService::class)->center($user, $category, null, 1);
    }

    private function keys(User $user, string $category): array
    {
        return collect($this->center($user, $category)['recommendations'])->pluck('lead_id')->all();
    }

    private function stageId(int $order): ?int
    {
        $id = Stage::query()->where('stage_type', 'lead')->where('order', $order)->value('id');

        return $id ? (int) $id : null;
    }

    private function poolStageId(): ?int
    {
        $stage = Stage::query()->where('stage_type', 'lead')->get(['id', 'name', 'order'])
            ->first(function (Stage $stage) {
                $name = strtolower(trim((string) $stage->name));

                return $name === 'lead pool' || $name === 'leadpool' || (int) $stage->order === 9 || (int) $stage->id === 10;
            });

        return $stage ? (int) $stage->id : null;
    }

    private function lead(User $owner, int $stageId, array $extra = []): Lead
    {
        return Lead::withoutEvents(function () use ($owner, $stageId, $extra) {
            return Lead::query()->create(array_merge([
                'lead_name' => 'Action Lead '.uniqid(),
                'first_name' => 'Action',
                'stage_id' => $stageId,
                'responsible_person_id' => $owner->id,
                'added_by' => $owner->id,
                'assignment_hold' => 0,
                'available_to_everyone' => 0,
                'no_answer_count' => 0,
                'lead_pool_visits' => 0,
                'notified_revert' => 0,
                'last_engagement_at' => now(),
            ], $extra));
        });
    }

    private function activity(Lead $lead, User $user, $when): LeadActivity
    {
        return LeadActivity::withoutEvents(function () use ($lead, $user, $when) {
            return LeadActivity::query()->create([
                'lead_id' => $lead->id,
                'user_id' => $user->id,
                'title' => 'Follow up '.uniqid(),
                'reminder_date' => $when,
                'is_completed' => false,
                'reminders' => [],
            ]);
        });
    }

    private function asUser(User $user)
    {
        return $this->withHeader('Authorization', 'Bearer '.JWTAuth::fromUser($user));
    }

    private function makeUser(string $role): User
    {
        $roleModel = Role::query()->firstOrCreate([
            'name' => $role,
            'guard_name' => 'api',
        ]);

        $user = User::factory()->create([
            'name' => $role.' '.uniqid(),
            'status' => 'active',
        ]);
        $user->assignRole($roleModel);

        return $user->fresh();
    }
}
