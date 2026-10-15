<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadComment;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class LeadPoolConversationAccessTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_and_super_admin_can_read_lead_pool_comments_and_activities(): void
    {
        [$superAdmin, $admin] = $this->branch();
        $lead = $this->poolLead($admin, $superAdmin);
        $this->conversation($lead, $superAdmin, 'pool-secret-comment', 'pool-secret-activity');

        foreach ([$admin, $superAdmin] as $user) {
            $this->asUser($user)->getJson("/api/leads/{$lead->id}/comments")
                ->assertOk()
                ->assertJsonFragment(['comment' => 'pool-secret-comment']);

            $this->asUser($user)->getJson("/api/leads/{$lead->id}/activities")
                ->assertOk()
                ->assertJsonFragment(['title' => 'pool-secret-activity']);
        }
    }

    public function test_non_admin_roles_cannot_read_lead_pool_comments_or_activities(): void
    {
        [$superAdmin, $admin] = $this->branch();
        $lead = $this->poolLead($admin, $superAdmin);
        $this->conversation($lead, $superAdmin, 'hidden-pool-comment', 'hidden-pool-activity');

        foreach (['sales', 'team_lead', 'manager', 'marketing', 'branch_admin'] as $role) {
            $user = $this->makeUser($role, $admin->id);

            $comments = $this->asUser($user)->getJson("/api/leads/{$lead->id}/comments");
            $comments->assertForbidden();
            $this->assertStringNotContainsString('hidden-pool-comment', $comments->getContent());

            $activities = $this->asUser($user)->getJson("/api/leads/{$lead->id}/activities");
            $activities->assertForbidden();
            $this->assertStringNotContainsString('hidden-pool-activity', $activities->getContent());
        }
    }

    public function test_admin_who_cannot_view_the_lead_is_still_denied(): void
    {
        [$superAdmin, $admin] = $this->branch();
        $lead = $this->poolLead($admin, $superAdmin);
        $this->conversation($lead, $superAdmin, 'other-branch-comment', 'other-branch-activity');

        $outsider = $this->makeUser('admin', $superAdmin->id);

        $comments = $this->asUser($outsider)->getJson("/api/leads/{$lead->id}/comments");
        $comments->assertForbidden();
        $this->assertStringNotContainsString('other-branch-comment', $comments->getContent());

        $activities = $this->asUser($outsider)->getJson("/api/leads/{$lead->id}/activities");
        $activities->assertForbidden();
        $this->assertStringNotContainsString('other-branch-activity', $activities->getContent());
    }

    public function test_comment_and_activity_access_on_other_stages_is_unchanged(): void
    {
        [$superAdmin, $admin] = $this->branch();
        $sales = $this->makeUser('sales', $admin->id);
        $lead = $this->otherStageLead($sales);
        $this->conversation($lead, $sales, 'open-stage-comment', 'open-stage-activity');

        foreach ([$sales, $admin, $superAdmin] as $user) {
            $this->asUser($user)->getJson("/api/leads/{$lead->id}/comments")
                ->assertOk()
                ->assertJsonFragment(['comment' => 'open-stage-comment']);

            $this->asUser($user)->getJson("/api/leads/{$lead->id}/activities")
                ->assertOk()
                ->assertJsonFragment(['title' => 'open-stage-activity']);
        }
    }

    private function asUser(User $user)
    {
        return $this->withHeader('Authorization', 'Bearer '.JWTAuth::fromUser($user));
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function branch(): array
    {
        $superAdmin = $this->makeUser('super_admin');
        $admin = $this->makeUser('admin', $superAdmin->id);

        return [$superAdmin, $admin];
    }

    private function poolLead(User $branchAdmin, User $owner): Lead
    {
        return Lead::withoutEvents(function () use ($branchAdmin, $owner) {
            return Lead::query()->create([
                'lead_name' => 'Pool Lead '.uniqid(),
                'first_name' => 'Pool',
                'stage_id' => 10,
                'lead_branch_source' => $branchAdmin->name,
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

    private function otherStageLead(User $owner): Lead
    {
        $stageId = Stage::query()->where('stage_type', 'lead')->where('id', '!=', 10)->value('id');
        if (! $stageId) {
            $this->markTestSkipped('No non-pool lead stage.');
        }

        return Lead::withoutEvents(function () use ($owner, $stageId) {
            return Lead::query()->create([
                'lead_name' => 'Open Lead '.uniqid(),
                'first_name' => 'Open',
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

    private function conversation(Lead $lead, User $author, string $comment, string $activity): void
    {
        LeadComment::withoutEvents(function () use ($lead, $author, $comment) {
            LeadComment::query()->create([
                'lead_id' => $lead->id,
                'user_id' => $author->id,
                'comment' => $comment,
            ]);
        });

        LeadActivity::withoutEvents(function () use ($lead, $author, $activity) {
            LeadActivity::query()->create([
                'lead_id' => $lead->id,
                'user_id' => $author->id,
                'title' => $activity,
                'reminder_date' => now()->addDay(),
                'is_completed' => false,
            ]);
        });
    }

    private function makeUser(string $role, ?int $parentId = null): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['name' => $role, 'guard_name' => 'api']
        );

        $user = User::factory()->create([
            'name' => $role.' '.uniqid(),
            'status' => 'active',
            'parent_id' => $parentId,
        ]);
        $user->assignRole($roleModel);

        return $user->fresh();
    }
}
