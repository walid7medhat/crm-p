<?php

namespace Tests\Unit\Maintenance;

use App\Events\MaintenanceModeUpdated;
use App\Models\PlatformMaintenanceSetting;
use App\Models\User;
use App\Support\Maintenance\MaintenanceModeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaintenanceModeServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('platform_maintenance_settings')) {
            $this->markTestSkipped('platform_maintenance_settings not migrated');
        }
        Cache::forget(MaintenanceModeService::CACHE_KEY);
    }

    public function test_cache_invalidates_immediately_on_update(): void
    {
        Event::fake([MaintenanceModeUpdated::class]);
        $service = app(MaintenanceModeService::class);
        $sa = $this->makeSuperAdmin();

        $this->assertFalse($service->isEnabled());

        $service->update(['is_enabled' => true, 'title' => 'Now'], $sa);
        $this->assertTrue($service->isEnabled());
        $this->assertSame('Now', $service->statusPayload()['title']);

        $service->update(['is_enabled' => false], $sa);
        $this->assertFalse($service->isEnabled());
    }

    public function test_model_is_not_brand_owned(): void
    {
        $this->assertNotContains(
            \App\Support\Brand\Concerns\BrandOwned::class,
            class_uses_recursive(PlatformMaintenanceSetting::class)
        );
    }

    private function makeSuperAdmin(): User
    {
        Role::findOrCreate('super_admin', 'api');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('super_admin');

        return $user;
    }
}
