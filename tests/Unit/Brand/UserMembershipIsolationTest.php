<?php

namespace Tests\Unit\Brand;

use App\Models\User;
use App\Support\Brand\BrandContext;
use App\Support\Brand\BrandIsolation;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserMembershipIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BrandIsolation::clearColumnCache();
    }

    public function test_user_id_endpoints_check_membership(): void
    {
        $userCtrl = file_get_contents(app_path('Http/Controllers/Api/UserController.php'));
        $this->assertStringContainsString('userBelongsToCurrentBrand', $userCtrl);
        $this->assertGreaterThanOrEqual(4, substr_count($userCtrl, 'userBelongsToCurrentBrand'));

        $report = file_get_contents(app_path('Http/Controllers/Api/ReportController.php'));
        $this->assertStringContainsString('userBelongsToCurrentBrand', $report);

        $ai = file_get_contents(app_path('Http/Controllers/Api/AiSalesIntelligence/AiSalesIntelligenceController.php'));
        $this->assertStringContainsString('userBelongsToCurrentBrand', $ai);
        $this->assertStringContainsString('ensureUserBelongsOrNotFound', $ai);

        $roles = file_get_contents(app_path('Http/Controllers/Api/UserRoleController.php'));
        $this->assertStringContainsString('ensureUserBelongsOrNotFound', $roles);
    }

    public function test_user_belongs_rules_for_null_oia_and_luxvant(): void
    {
        if (! Schema::hasColumn('users', 'brand_key')) {
            $this->markTestSkipped('users.brand_key not migrated');
        }

        $oiaLegacy = new User;
        $oiaLegacy->forceFill(['id' => 900001, 'brand_key' => null]);

        $oia = new User;
        $oia->forceFill(['id' => 900002, 'brand_key' => 'oia']);

        $lux = new User;
        $lux->forceFill(['id' => 900003, 'brand_key' => 'luxvant']);

        app(BrandContext::class)->set(config('brands.brands.oia'));
        $this->assertTrue(BrandIsolation::userBelongsToCurrentBrand($oiaLegacy));
        $this->assertTrue(BrandIsolation::userBelongsToCurrentBrand($oia));
        $this->assertFalse(BrandIsolation::userBelongsToCurrentBrand($lux));

        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        $this->assertFalse(BrandIsolation::userBelongsToCurrentBrand($oiaLegacy));
        $this->assertFalse(BrandIsolation::userBelongsToCurrentBrand($oia));
        $this->assertTrue(BrandIsolation::userBelongsToCurrentBrand($lux));
    }

    public function test_user_has_no_global_brand_owned_read_scope(): void
    {
        $this->assertNotContains(
            \App\Support\Brand\Concerns\BrandOwned::class,
            class_uses_recursive(User::class)
        );
        $this->assertContains(
            \App\Support\Brand\Concerns\BrandStampOnly::class,
            class_uses_recursive(User::class)
        );
    }
}
