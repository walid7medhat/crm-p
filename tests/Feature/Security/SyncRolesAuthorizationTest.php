<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * P0: syncRoles must require the same assign-role permission as assign/remove.
 */
class SyncRolesAuthorizationTest extends TestCase
{
    public function test_sync_roles_route_requires_assign_role_permission_middleware(): void
    {
        $route = collect(Route::getRoutes())->first(
            fn ($r) => in_array('POST', $r->methods(), true) && $r->uri() === 'api/users/{user}/sync-roles'
        );

        $this->assertNotNull($route);
        $middleware = $route->gatherMiddleware();
        $this->assertTrue(
            collect($middleware)->contains(
                fn ($m) => $m === 'permission:assign-role' || str_contains((string) $m, 'assign-role')
            ),
            'sync-roles must gather permission:assign-role (controller middleware)'
        );
    }

    public function test_unauthorized_user_cannot_sync_roles_including_super_admin(): void
    {
        $permission = Permission::findOrCreate('assign-role', 'api');

        $attacker = User::factory()->create(['status' => 'active']);
        // Ensure attacker does not hold assign-role (directly or via role).
        $attacker->revokePermissionTo($permission);
        foreach ($attacker->roles as $role) {
            $attacker->removeRole($role);
        }

        $targetCreated = false;
        $target = User::query()
            ->where('status', 'active')
            ->where('id', '!=', $attacker->id)
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'super_admin'))
            ->first();

        if (!$target) {
            $target = User::factory()->create(['status' => 'active']);
            $targetCreated = true;
        }

        $originalRoleNames = $target->getRoleNames()->values()->all();

        $token = JWTAuth::fromUser($attacker);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/users/'.$target->id.'/sync-roles', [
                'role' => 'sales',
                'roles' => ['super_admin'],
            ]);

        $this->assertSame(403, $response->status());

        $target->refresh();
        $this->assertFalse(
            $target->hasRole('super_admin'),
            'Unauthorized syncRoles must not grant super_admin'
        );
        $this->assertEqualsCanonicalizing(
            $originalRoleNames,
            $target->getRoleNames()->values()->all()
        );

        $attacker->delete();
        if ($targetCreated) {
            $target->delete();
        }
    }

    public function test_authorized_user_with_assign_role_can_sync_roles(): void
    {
        Permission::findOrCreate('assign-role', 'api');
        Role::findOrCreate('sales', 'api');

        $actor = User::factory()->create(['status' => 'active']);
        $actor->givePermissionTo('assign-role');

        $target = User::factory()->create(['status' => 'active']);
        $previousRoles = $target->getRoleNames()->values()->all();

        $token = JWTAuth::fromUser($actor);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/users/'.$target->id.'/sync-roles', [
                'role' => 'sales',
                'roles' => ['sales'],
            ]);

        $this->assertSame(200, $response->status(), (string) $response->getContent());
        $this->assertTrue($response->json('status') ?? $response->json('success') ?? true);

        $target->refresh();
        $this->assertTrue($target->hasRole('sales'));

        // Restore / cleanup factory users
        if ($previousRoles === []) {
            $target->syncRoles([]);
        } else {
            $target->syncRoles($previousRoles);
        }
        $target->delete();
        $actor->delete();
    }

    public function test_assign_role_still_requires_permission(): void
    {
        Permission::findOrCreate('assign-role', 'api');
        Role::findOrCreate('sales', 'api');

        $attacker = User::factory()->create(['status' => 'active']);
        foreach ($attacker->roles as $role) {
            $attacker->removeRole($role);
        }
        $attacker->revokePermissionTo('assign-role');

        $target = User::factory()->create(['status' => 'active']);

        $token = JWTAuth::fromUser($attacker);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/users/'.$target->id.'/assign-role', [
                'role' => 'sales',
            ]);

        $this->assertSame(403, $response->status());

        $target->delete();
        $attacker->delete();
    }
}
