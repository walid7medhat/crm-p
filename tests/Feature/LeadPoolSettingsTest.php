<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadPoolAssignment;
use App\Models\User;
use App\Services\LeadPoolAssignmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class LeadPoolSettingsTest extends TestCase
{
    use DatabaseTransactions;

    private function payload(bool $enabled = true, int $daily = 20, int $hourly = 5): array
    {
        return ['lead_pool_enabled' => $enabled, 'daily_limit' => $daily, 'hourly_limit' => $hourly];
    }

    public function test_super_admin_manages_any_agent(): void
    {
        [$superAdmin, $branchA] = $this->branch();
        $sales = $this->makeUser('sales', $branchA->id);

        $this->asUser($superAdmin)
            ->putJson("/api/lead-pool/settings/{$sales->id}", $this->payload(false, 25, 7))
            ->assertOk()
            ->assertJsonPath('data.lead_pool_enabled', false)
            ->assertJsonPath('data.daily_limit', 25)
            ->assertJsonPath('data.hourly_limit', 7);

        $sales->refresh();
        $this->assertFalse($sales->lead_pool_enabled);
        $this->assertSame(25, $sales->lead_pool_daily_limit);
        $this->assertSame(7, $sales->lead_pool_hourly_limit);
    }

    public function test_user_30_manages_all_settings(): void
    {
        $user = new User();
        $user->id = 30;

        $this->assertTrue(app(LeadPoolAssignmentService::class)->managesAllSettings($user));
    }

    public function test_only_users_with_the_permission_manage_and_only_their_own_branch(): void
    {
        [$superAdmin, $branchA] = $this->branch();
        $branchB = $this->makeUser('admin', $superAdmin->id);
        $branchAdminA = $this->makeUser('branch_admin', $branchA->id);
        $sameRoleNoPermission = $this->makeUser('branch_admin', $branchA->id);
        $salesA = $this->makeUser('sales', $branchAdminA->id);
        $salesB = $this->makeUser('sales', $branchB->id);

        $this->grantPermission($branchAdminA);
        $this->grantPermission($branchA);

        // Same role, no permission → no access at all.
        $this->asUser($sameRoleNoPermission)->getJson('/api/lead-pool/settings')->assertForbidden();
        $this->asUser($sameRoleNoPermission)
            ->putJson("/api/lead-pool/settings/{$salesA->id}", $this->payload())
            ->assertForbidden();
        $this->asUser($branchB)->getJson('/api/lead-pool/settings')->assertForbidden();

        foreach ([$branchAdminA, $branchA] as $manager) {
            $this->asUser($manager)
                ->putJson("/api/lead-pool/settings/{$salesA->id}", $this->payload())
                ->assertOk();

            $this->asUser($manager)
                ->putJson("/api/lead-pool/settings/{$salesB->id}", $this->payload())
                ->assertForbidden();

            $ids = collect($this->asUser($manager)->getJson('/api/lead-pool/settings')
                ->assertOk()
                ->json('data.agents'))->pluck('id');
            $this->assertTrue($ids->contains($salesA->id));
            $this->assertFalse($ids->contains($salesB->id));
        }

        $this->asUser($branchAdminA)
            ->putJson('/api/lead-pool/settings/bulk', $this->payload() + ['user_ids' => [$salesA->id, $salesB->id]])
            ->assertForbidden();
    }

    public function test_sales_cannot_manage_settings(): void
    {
        [, $branchA] = $this->branch();
        $sales = $this->makeUser('sales', $branchA->id);
        $other = $this->makeUser('sales', $branchA->id);

        $this->asUser($sales)->getJson('/api/lead-pool/settings')->assertForbidden();
        $this->asUser($sales)
            ->putJson("/api/lead-pool/settings/{$other->id}", $this->payload())
            ->assertForbidden();
    }

    public function test_hourly_limit_cannot_exceed_daily_limit(): void
    {
        [$superAdmin, $branchA] = $this->branch();
        $sales = $this->makeUser('sales', $branchA->id);

        $this->asUser($superAdmin)
            ->putJson("/api/lead-pool/settings/{$sales->id}", $this->payload(true, 5, 10))
            ->assertStatus(422);
    }

    public function test_defaults_are_40_per_day_and_10_per_hour(): void
    {
        [, $branchA] = $this->branch();
        $sales = $this->makeUser('sales', $branchA->id);

        $status = app(LeadPoolAssignmentService::class)->getStatus($sales->id);

        $this->assertTrue($status['enabled']);
        $this->assertSame(40, $status['daily_limit']);
        $this->assertSame(10, $status['batch_limit']);
    }

    public function test_disabled_agent_sees_empty_pool_and_cannot_assign(): void
    {
        [$superAdmin, $branchA] = $this->branch();
        $sales = $this->makeUser('sales', $branchA->id);
        $this->poolLead($branchA, $superAdmin);

        $sales->forceFill(['lead_pool_enabled' => false])->save();

        $leads = $this->asUser($sales)
            ->getJson('/api/leads?stage_id=10&paginate=1&page=1&per_page=24')
            ->assertOk()
            ->json('data');
        $this->assertSame([], $leads);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Lead Pool is disabled');
        app(LeadPoolAssignmentService::class)->validateBatch($sales->id, 1);
    }

    public function test_custom_hourly_limit_is_enforced(): void
    {
        [$superAdmin, $branchA] = $this->branch();
        $sales = $this->makeUser('sales', $branchA->id);
        $sales->forceFill(['lead_pool_daily_limit' => 20, 'lead_pool_hourly_limit' => 3])->save();

        $this->assignToday($sales, $this->poolLead($branchA, $superAdmin), 3);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You have assigned 3 leads');
        app(LeadPoolAssignmentService::class)->validateBatch($sales->id, 1);
    }

    public function test_custom_daily_limit_is_enforced(): void
    {
        [$superAdmin, $branchA] = $this->branch();
        $sales = $this->makeUser('sales', $branchA->id);
        $sales->forceFill(['lead_pool_daily_limit' => 4, 'lead_pool_hourly_limit' => 4])->save();

        $this->assignToday($sales, $this->poolLead($branchA, $superAdmin), 4);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('daily limit of 4');
        app(LeadPoolAssignmentService::class)->validateBatch($sales->id, 1);
    }

    private function assignToday(User $user, Lead $lead, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            LeadPoolAssignment::create([
                'user_id' => $user->id,
                'lead_id' => $lead->id,
                'batch_id' => (string) Str::uuid(),
                'assigned_at' => now()->subMinutes($count - $i),
            ]);
        }
    }

    private function grantPermission(User $user): void
    {
        $permission = Permission::query()->firstOrCreate([
            'name' => LeadPoolAssignmentService::SETTINGS_PERMISSION,
            'guard_name' => 'api',
        ]);
        $user->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function asUser(User $user)
    {
        return $this->withHeader('Authorization', 'Bearer '.JWTAuth::fromUser($user));
    }

    /** @return array{0: User, 1: User} super_admin and a branch (admin right under them) */
    private function branch(): array
    {
        $superAdmin = $this->makeUser('super_admin');
        $admin = $this->makeUser('admin', $superAdmin->id);

        return [$superAdmin, $admin];
    }

    private function poolLead(User $branchAdmin, User $owner): Lead
    {
        return Lead::withoutEvents(fn () => Lead::query()->create([
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
        ]));
    }

    private function makeUser(string $role, ?int $parentId = null): User
    {
        $roleModel = Role::query()->firstOrCreate(['name' => $role, 'guard_name' => 'api']);

        $user = User::factory()->create([
            'name' => $role.' '.uniqid(),
            'status' => 'active',
            'parent_id' => $parentId,
        ]);
        $user->assignRole($roleModel);

        return $user->fresh();
    }
}
