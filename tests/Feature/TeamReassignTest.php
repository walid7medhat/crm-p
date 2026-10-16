<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class TeamReassignTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guests_and_sales_cannot_reassign_people(): void
    {
        $tree = $this->tree();

        $this->postJson('/api/team/reassign', [
            'user_id' => $tree['salesDubai']->id,
            'parent_id' => $tree['teamB']->id,
        ])->assertUnauthorized();

        $this->asUser($tree['salesDubai'])->postJson('/api/team/reassign', [
            'user_id' => $tree['salesDubai']->id,
            'parent_id' => $tree['teamB']->id,
        ])->assertForbidden();

        $this->assertSame($tree['teamA']->id, $tree['salesDubai']->fresh()->parent_id);
    }

    public function test_manager_can_move_a_sales_employee_between_their_own_teams(): void
    {
        $tree = $this->tree();

        $this->asUser($tree['dubaiManager'])->postJson('/api/team/reassign', [
            'user_id' => $tree['salesDubai']->id,
            'parent_id' => $tree['teamB']->id,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.parent_id', $tree['teamB']->id);

        $this->assertSame($tree['teamB']->id, $tree['salesDubai']->fresh()->parent_id);
    }

    public function test_manager_cannot_move_people_across_branches_or_move_leaders(): void
    {
        $tree = $this->tree();

        $this->asUser($tree['dubaiManager'])->postJson('/api/team/reassign', [
            'user_id' => $tree['salesAbu']->id,
            'parent_id' => $tree['teamA']->id,
        ])->assertForbidden();

        $this->assertSame($tree['teamC']->id, $tree['salesAbu']->fresh()->parent_id);

        $this->asUser($tree['dubaiManager'])->postJson('/api/team/reassign', [
            'user_id' => $tree['teamA']->id,
            'parent_id' => $tree['dubaiManager']->id,
        ])->assertStatus(422);

        $this->assertSame($tree['dubaiManager']->id, $tree['teamA']->fresh()->parent_id);
    }

    public function test_admin_can_move_across_branches_and_invalid_targets_are_rejected(): void
    {
        $tree = $this->tree();

        $this->asUser($tree['super'])->postJson('/api/team/reassign', [
            'user_id' => $tree['salesAbu']->id,
            'parent_id' => $tree['teamA']->id,
        ])->assertOk()
            ->assertJsonPath('data.parent_id', $tree['teamA']->id);

        $this->assertSame($tree['teamA']->id, $tree['salesAbu']->fresh()->parent_id);

        $this->asUser($tree['super'])->postJson('/api/team/reassign', [
            'user_id' => $tree['salesDubai']->id,
            'parent_id' => $tree['salesAbu']->id,
        ])->assertStatus(422);

        $this->assertSame($tree['teamA']->id, $tree['salesDubai']->fresh()->parent_id);

        $this->asUser($tree['super'])->postJson('/api/team/reassign', [
            'user_id' => $tree['salesDubai']->id,
            'parent_id' => $tree['teamA']->id,
        ])->assertOk()
            ->assertJsonPath('message', 'Already on this team.');
    }

    public function test_reassignment_rejects_a_reporting_loop(): void
    {
        $tree = $this->tree();
        $floater = $this->makeUser('sales', ['parent_id' => $tree['dubaiManager']->id]);
        $leadUnderFloater = $this->makeUser('team_lead', ['parent_id' => $floater->id]);

        $this->asUser($tree['super'])->postJson('/api/team/reassign', [
            'user_id' => $floater->id,
            'parent_id' => $leadUnderFloater->id,
        ])->assertStatus(422);

        $this->assertSame($tree['dubaiManager']->id, $floater->fresh()->parent_id);
    }

    public function test_hierarchy_stays_inside_the_callers_scope(): void
    {
        $tree = $this->tree();

        $admin = $this->asUser($tree['super'])->getJson('/api/team/hierarchy')->assertOk();
        $this->assertTrue($admin->json('can_reassign'));
        $ids = collect($admin->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($tree['salesDubai']->id));
        $this->assertTrue($ids->contains($tree['salesAbu']->id));

        $manager = $this->asUser($tree['dubaiManager'])->getJson('/api/team/hierarchy')->assertOk();
        $managerIds = collect($manager->json('data'))->pluck('id');
        $this->assertTrue($managerIds->contains($tree['salesDubai']->id));
        $this->assertFalse($managerIds->contains($tree['salesAbu']->id));

        $sales = $this->asUser($tree['salesDubai'])->getJson('/api/team/hierarchy')->assertOk();
        $this->assertFalse($sales->json('can_reassign'));
    }

    private function tree(): array
    {
        $super = $this->makeUser('super_admin', ['name' => 'Org Root']);
        $dubai = $this->makeUser('admin', ['name' => 'Dubai', 'parent_id' => $super->id]);
        $abu = $this->makeUser('admin', ['name' => 'Abu Dhabi', 'parent_id' => $super->id]);
        $dubaiManager = $this->makeUser('manager', ['parent_id' => $dubai->id]);
        $abuManager = $this->makeUser('manager', ['parent_id' => $abu->id]);
        $teamA = $this->makeUser('team_lead', ['parent_id' => $dubaiManager->id]);
        $teamB = $this->makeUser('team_lead', ['parent_id' => $dubaiManager->id]);
        $teamC = $this->makeUser('team_lead', ['parent_id' => $abuManager->id]);
        $salesDubai = $this->makeUser('sales', ['parent_id' => $teamA->id]);
        $salesAbu = $this->makeUser('sales', ['parent_id' => $teamC->id]);

        return compact(
            'super',
            'dubai',
            'abu',
            'dubaiManager',
            'abuManager',
            'teamA',
            'teamB',
            'teamC',
            'salesDubai',
            'salesAbu'
        );
    }

    private function makeUser(string $role, array $extra = []): User
    {
        $user = User::factory()->create(array_merge([
            'status' => 'active',
            'email' => uniqid($role, true).'@example.test',
        ], $extra));
        Role::findOrCreate($role, 'api');
        $user->assignRole($role);

        return $user;
    }

    private function asUser(User $user): self
    {
        return $this->withHeader('Authorization', 'Bearer '.JWTAuth::fromUser($user));
    }
}
