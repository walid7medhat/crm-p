<?php

namespace Tests\Unit\Roles;

use PHPUnit\Framework\TestCase;

/**
 * Static contract checks. They do not boot Laravel and do not touch a database.
 */
class RoleSimulationEndpointContractTest extends TestCase
{
    public function test_preview_routes_are_registered_before_the_role_id_route(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 3).'/routes/api.php');

        $catalog = strpos($routes, "Route::get('/roles/permission-catalog'");
        $search = strpos($routes, "Route::get('/roles/simulation-users'");
        $preview = strpos($routes, "Route::post('/roles/access-preview'");
        $resource = strpos($routes, "Route::apiResource('roles'");

        $this->assertNotFalse($catalog);
        $this->assertNotFalse($search);
        $this->assertNotFalse($preview);
        $this->assertNotFalse($resource);
        $this->assertLessThan($resource, $catalog);
        $this->assertLessThan($resource, $search);
        $this->assertLessThan($resource, $preview);
    }

    public function test_controller_requires_role_management_permission_and_does_not_write(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/Api/RoleSimulationController.php');

        $this->assertStringContainsString(
            "middleware('role_or_permission:super_admin|roles-list|roles-create|roles-edit')",
            $source
        );
        $this->assertStringContainsString('->limit(15)', $source);
        $this->assertStringNotContainsString('syncPermissions', $source);
        $this->assertStringNotContainsString('assignRole', $source);
        $this->assertStringNotContainsString('syncRoles', $source);
        $this->assertStringNotContainsString('->save(', $source);
        $this->assertStringNotContainsString('->delete(', $source);
        $this->assertStringNotContainsString('Lead::', $source);
        $this->assertStringNotContainsString('Deal::', $source);
        $this->assertStringNotContainsString('Listing::', $source);
    }
}
