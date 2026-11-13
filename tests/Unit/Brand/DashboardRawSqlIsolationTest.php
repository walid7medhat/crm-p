<?php

namespace Tests\Unit\Brand;

use App\Support\Brand\BrandContext;
use App\Support\Brand\BrandIsolation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardRawSqlIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BrandIsolation::clearColumnCache();
    }

    public function test_constrain_on_listings_excludes_null_for_luxvant_includes_for_oia(): void
    {
        if (! Schema::hasColumn('listings', 'brand_key')) {
            $this->markTestSkipped('brand_key not migrated');
        }

        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        $lux = DB::table('listings')->select('id');
        BrandIsolation::constrain($lux, 'listings', 'listings.brand_key');
        $luxSql = strtolower($lux->toSql());
        $this->assertContains('luxvant', $lux->getBindings());
        $this->assertStringNotContainsString('is null', $luxSql);

        app(BrandContext::class)->set(config('brands.brands.oia'));
        $oia = DB::table('listings')->select('id');
        BrandIsolation::constrain($oia, 'listings', 'listings.brand_key');
        $oiaSql = strtolower($oia->toSql());
        $this->assertContains('oia', $oia->getBindings());
        $this->assertStringContainsString('is null', $oiaSql);
    }

    public function test_constrain_on_deals_join_path_uses_deals_brand_key(): void
    {
        if (! Schema::hasColumn('deals', 'brand_key')) {
            $this->markTestSkipped('brand_key not migrated');
        }

        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        $q = DB::table('deal_properties')
            ->join('deals', 'deals.id', '=', 'deal_properties.deal_id');
        BrandIsolation::constrain($q, 'deals', 'deals.brand_key');
        $this->assertContains('luxvant', $q->getBindings());
        $this->assertStringContainsString('brand_key', strtolower($q->toSql()));
    }

    public function test_super_admin_cross_brand_flag_defaults_off(): void
    {
        $this->assertFalse(BrandIsolation::superAdminCrossBrandEnabled());
    }

    public function test_dashboard_controller_source_contains_constrain_for_all_raw_tables(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/Api/DashboardController.php'));
        $this->assertStringContainsString("BrandIsolation::constrain(\$propertyTypeQuery, 'listings'", $src);
        $this->assertStringContainsString("BrandIsolation::constrain(\$propertyRevenueQuery, 'deals'", $src);
        $this->assertStringContainsString("BrandIsolation::constrain(\$leadRevenueQuery, 'leads'", $src);
        $this->assertStringContainsString("BrandIsolation::constrain(\$dealRevenueQuery, 'deals'", $src);
        $this->assertStringContainsString("BrandIsolation::constrain(\$visitQuery, 'listing_access_requests'", $src);
    }
}
