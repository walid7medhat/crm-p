<?php

namespace Tests\Unit\Maintenance;

use App\Http\Middleware\EnsureNotInMaintenance;
use App\Models\User;
use App\Support\Maintenance\MaintenanceModeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class EnsureNotInMaintenanceMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('platform_maintenance_settings')) {
            $this->markTestSkipped('platform_maintenance_settings not migrated');
        }
        Cache::forget(MaintenanceModeService::CACHE_KEY);
        app(MaintenanceModeService::class)->current()->forceFill(['is_enabled' => true])->save();
        app(MaintenanceModeService::class)->forgetCache();
    }

    public function test_allowlists_health_brand_login_and_status(): void
    {
        $mw = app(EnsureNotInMaintenance::class);
        foreach (['up', 'api/brand', 'api/auth/login', 'api/maintenance/status'] as $path) {
            $request = Request::create('/'.$path, $path === 'api/auth/login' ? 'POST' : 'GET');
            $response = $mw->handle($request, fn () => response('ok', 200));
            $this->assertSame(200, $response->getStatusCode(), $path);
        }
    }

    public function test_blocks_anonymous_api_with_503(): void
    {
        $mw = app(EnsureNotInMaintenance::class);
        $request = Request::create('/api/leads', 'GET');
        $response = $mw->handle($request, fn () => response('ok', 200));
        $this->assertSame(503, $response->getStatusCode());
        $this->assertTrue($response->headers->has('Retry-After'));
    }

    public function test_super_admin_bearer_bypasses(): void
    {
        Role::findOrCreate('super_admin', 'api');
        $sa = User::factory()->create(['status' => 'active']);
        $sa->assignRole('super_admin');
        $token = JWTAuth::fromUser($sa);

        $mw = app(EnsureNotInMaintenance::class);
        $request = Request::create('/api/leads', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$token);
        $response = $mw->handle($request, fn () => response('ok', 200));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_admin_role_does_not_bypass(): void
    {
        Role::findOrCreate('admin', 'api');
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');
        $token = JWTAuth::fromUser($admin);

        $mw = app(EnsureNotInMaintenance::class);
        $request = Request::create('/api/leads', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$token);
        $response = $mw->handle($request, fn () => response('ok', 200));
        $this->assertSame(503, $response->getStatusCode());
    }
}
