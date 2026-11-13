<?php

namespace Tests\Feature\Maintenance;

use App\Models\PlatformMaintenanceSetting;
use App\Models\User;
use App\Support\Brand\BrandContext;
use App\Support\Maintenance\MaintenanceModeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class GlobalMaintenanceModeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('platform_maintenance_settings')) {
            $this->markTestSkipped('platform_maintenance_settings not migrated');
        }

        Cache::forget(MaintenanceModeService::CACHE_KEY);
        $update = [
            'is_enabled' => false,
            'title' => "We'll Be Back Soon",
            'message' => 'The CRM is temporarily unavailable while we perform scheduled maintenance.',
            'estimated_return' => null,
            'updated_by' => null,
        ];
        if (Schema::hasColumn('platform_maintenance_settings', 'scope')) {
            $update['scope'] = 'all';
            $update['branch_user_ids'] = json_encode([]);
        }
        PlatformMaintenanceSetting::query()->update($update);
        app(MaintenanceModeService::class)->forgetCache();
        app(BrandContext::class)->set(config('brands.brands.oia'));
    }

    public function test_maintenance_off_allows_authenticated_users(): void
    {
        $agent = $this->makeUser('sales');
        $this->asUser($agent)->getJson('/api/maintenance/status')
            ->assertOk()
            ->assertJsonPath('data.enabled', false);
    }

    public function test_status_endpoint_is_public_while_maintenance_on(): void
    {
        $this->enableMaintenance();

        $this->getJson('/api/maintenance/status')
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.title', 'Scheduled upgrade');
    }

    public function test_non_super_admin_api_requests_return_503_when_on(): void
    {
        $this->enableMaintenance();
        $agent = $this->makeUser('sales');
        $admin = $this->makeUser('admin');
        $branch = $this->makeUser('branch_admin');

        foreach ([$agent, $admin, $branch] as $user) {
            $this->asUser($user)->getJson('/api/auth/notifications')
                ->assertStatus(503)
                ->assertJsonPath('maintenance', true)
                ->assertJsonPath('data.enabled', true);
        }
    }

    public function test_super_admin_retains_full_api_access_when_on(): void
    {
        $this->enableMaintenance();
        $sa = $this->makeUser('super_admin');

        $this->asUser($sa)->getJson('/api/maintenance')
            ->assertOk()
            ->assertJsonPath('data.enabled', true);

        $this->asUser($sa)->putJson('/api/maintenance', [
            'message' => 'Updated while ON',
            'estimated_return' => 'Tonight 10pm',
        ])->assertOk()
            ->assertJsonPath('data.message', 'Updated while ON')
            ->assertJsonPath('data.estimated_return', 'Tonight 10pm')
            ->assertJsonPath('data.enabled', true);
    }

    public function test_login_and_brand_remain_reachable_when_on(): void
    {
        $this->enableMaintenance();

        $this->getJson('/api/brand')->assertOk();

        $sa = $this->makeUser('super_admin');
        $this->postJson('/api/auth/login', [
            'email' => $sa->email,
            'password' => 'password',
        ])->assertOk();
    }

    public function test_public_webhooks_and_auth_recovery_are_blocked_when_on(): void
    {
        $this->enableMaintenance();

        $blocked = [
            ['POST', '/api/bitrix24/webhook'],
            ['POST', '/api/propertyfinder/webhook'],
            ['POST', '/api/webhook/facebook'],
            ['POST', '/api/website-lead'],
            ['POST', '/api/auth/forgot-password', ['email' => 'a@example.com']],
            ['POST', '/api/auth/register', [
                'name' => 'X',
                'email' => 'x'.uniqid().'@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]],
            ['GET', '/api/invitation/fake-token'],
            ['POST', '/api/register', ['token' => 'fake']],
            ['GET', '/api/recruitment/jobs'],
        ];

        foreach ($blocked as $call) {
            $method = $call[0];
            $uri = $call[1];
            $payload = $call[2] ?? [];
            $response = $method === 'GET'
                ? $this->getJson($uri)
                : $this->postJson($uri, $payload);
            $response->assertStatus(503, "Expected 503 for {$method} {$uri}");
            $response->assertJsonPath('maintenance', true);
        }
    }

    public function test_admin_cannot_toggle_maintenance(): void
    {
        $admin = $this->makeUser('admin');
        $this->asUser($admin)->putJson('/api/maintenance', ['is_enabled' => true])
            ->assertStatus(403);

        $this->assertFalse(app(MaintenanceModeService::class)->isEnabled());
    }

    public function test_sales_cannot_toggle_maintenance(): void
    {
        $agent = $this->makeUser('sales');
        $this->asUser($agent)->putJson('/api/maintenance', ['is_enabled' => true])
            ->assertStatus(403);
    }

    public function test_deactivating_restores_access_immediately(): void
    {
        $sa = $this->makeUser('super_admin');
        $agent = $this->makeUser('sales');

        $this->asUser($sa)->putJson('/api/maintenance', ['is_enabled' => true])->assertOk();
        $this->asUser($agent)->getJson('/api/auth/notifications')->assertStatus(503);

        $this->asUser($sa)->putJson('/api/maintenance', ['is_enabled' => false])->assertOk();
        Cache::forget(MaintenanceModeService::CACHE_KEY);

        // Assert maintenance gate is gone (not 503). Endpoint may still 401/403 for
        // unrelated ACL — only the maintenance payload must be absent.
        $restored = $this->asUser($agent)->getJson('/api/auth/notifications');
        $this->assertNotSame(503, $restored->status());
        $this->assertNotTrue($restored->json('maintenance'));
        $this->assertFalse(app(MaintenanceModeService::class)->isEnabled());
        $this->assertDatabaseHas('users', ['id' => $agent->id]);
    }

    public function test_applies_on_luxvant_host_as_well(): void
    {
        $this->enableMaintenance();
        app(BrandContext::class)->set(config('brands.brands.luxvant'));

        $agent = $this->makeUser('sales');
        $this->asUser($agent)
            ->getJson('http://luxvant.altcrm.com/api/auth/notifications', [
                'HTTP_HOST' => 'luxvant.altcrm.com',
            ])
            ->assertStatus(503)
            ->assertJsonPath('maintenance', true);
    }

    public function test_impersonation_token_cannot_bypass(): void
    {
        $this->enableMaintenance();
        $sa = $this->makeUser('super_admin');
        $agent = $this->makeUser('sales');

        // Mimic AuthController::impersonate — JWT subject is the target user.
        $token = JWTAuth::claims(['impersonated_by' => (int) $sa->id])->fromUser($agent);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/notifications')
            ->assertStatus(503)
            ->assertJsonPath('maintenance', true);
    }

    public function test_audit_log_written_on_toggle(): void
    {
        if (! Schema::hasTable('activity_log')) {
            $this->markTestSkipped('activity_log missing');
        }

        Event::fake([\App\Events\MaintenanceModeUpdated::class]);
        $sa = $this->makeUser('super_admin');

        $this->asUser($sa)->putJson('/api/maintenance', [
            'is_enabled' => true,
            'title' => 'Audit title',
        ])->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'maintenance',
            'description' => 'maintenance.enabled',
            'causer_id' => $sa->id,
        ]);

        Event::assertDispatched(\App\Events\MaintenanceModeUpdated::class);
    }

    public function test_unauthenticated_api_is_blocked_when_on(): void
    {
        $this->enableMaintenance();
        $this->getJson('/api/auth/notifications')->assertStatus(503);
    }

    public function test_branch_scope_blocks_only_selected_branch_users(): void
    {
        if (! Schema::hasColumn('platform_maintenance_settings', 'scope')) {
            $this->markTestSkipped('branch scope columns missing');
        }

        Role::findOrCreate('admin', 'api');
        Role::findOrCreate('sales', 'api');
        Role::findOrCreate('super_admin', 'api');

        $root = User::factory()->create(['status' => 'active', 'parent_id' => null]);
        $root->assignRole('super_admin');

        $branchA = User::factory()->create(['status' => 'active', 'parent_id' => $root->id, 'name' => 'Branch A City']);
        $branchA->assignRole('admin');
        $agentA = User::factory()->create(['status' => 'active', 'parent_id' => $branchA->id]);
        $agentA->assignRole('sales');

        $branchB = User::factory()->create(['status' => 'active', 'parent_id' => $root->id, 'name' => 'Branch B City']);
        $branchB->assignRole('admin');
        $agentB = User::factory()->create(['status' => 'active', 'parent_id' => $branchB->id]);
        $agentB->assignRole('sales');

        // AccountAccessMailer::isBranch only accepts admins under a root parent.
        $this->assertTrue(app(\App\Services\AccountAccessMailer::class)->isBranch($branchA->id));

        $sa = $this->makeUser('super_admin');
        $this->asUser($sa)->putJson('/api/maintenance', [
            'is_enabled' => true,
            'scope' => 'branches',
            'branch_user_ids' => [$branchA->id],
            'title' => 'Branch A only',
        ])->assertOk();

        $this->asUser($agentA)->getJson('/api/auth/notifications')
            ->assertStatus(503)
            ->assertJsonPath('maintenance', true);

        $other = $this->asUser($agentB)->getJson('/api/auth/notifications');
        $this->assertNotSame(503, $other->status(), 'Other branches must stay online');
        $this->assertNotTrue($other->json('maintenance'));

        // Partial scope keeps public integrations available (not maintenance 503).
        $public = $this->postJson('/api/bitrix24/webhook', []);
        $this->assertNotSame(503, $public->status());
        $this->assertNotTrue($public->json('maintenance'));
    }

    public function test_branch_scope_requires_at_least_one_branch(): void
    {
        if (! Schema::hasColumn('platform_maintenance_settings', 'scope')) {
            $this->markTestSkipped('branch scope columns missing');
        }

        $sa = $this->makeUser('super_admin');
        $this->asUser($sa)->putJson('/api/maintenance', [
            'is_enabled' => true,
            'scope' => 'branches',
            'branch_user_ids' => [],
        ])->assertStatus(422);
    }

    private function enableMaintenance(): void
    {
        $row = app(MaintenanceModeService::class)->current();
        $row->forceFill([
            'is_enabled' => true,
            'scope' => 'all',
            'branch_user_ids' => [],
            'title' => 'Scheduled upgrade',
            'message' => 'Down for upgrades.',
            'estimated_return' => 'Soon',
        ])->save();
        app(MaintenanceModeService::class)->forgetCache();
    }

    private function makeUser(string $role): User
    {
        Role::findOrCreate($role, 'api');
        $user = User::factory()->create([
            'status' => 'active',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole($role);

        return $user->fresh();
    }

    private function asUser(User $user)
    {
        return $this->withHeader('Authorization', 'Bearer '.JWTAuth::fromUser($user));
    }
}
