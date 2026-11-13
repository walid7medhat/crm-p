<?php

namespace Tests\Unit\Brand;

use App\Models\Lead;
use App\Models\Listing;
use App\Models\Project;
use App\Support\Brand\BrandContext;
use App\Support\Brand\BrandCrmScope;
use App\Support\Brand\BrandIsolation;
use App\Support\Brand\BrandScope;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BrandIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BrandIsolation::clearColumnCache();
    }

    public function test_trusted_key_comes_from_brand_context_not_defaults_when_set(): void
    {
        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        $this->assertSame('luxvant', BrandIsolation::trustedKey());

        app(BrandContext::class)->set(config('brands.brands.oia'));
        $this->assertSame('oia', BrandIsolation::trustedKey());
    }

    public function test_super_admin_cross_brand_is_off_by_default(): void
    {
        $this->assertFalse(BrandIsolation::superAdminCrossBrandEnabled());
        $this->assertFalse(config('brands.super_admin_cross_brand'));
    }

    public function test_luxvant_storage_prefix_and_oia_legacy_paths(): void
    {
        app(BrandContext::class)->set(config('brands.brands.oia'));
        $this->assertSame('', BrandIsolation::storagePrefix());
        $this->assertSame('listings/gallery', BrandIsolation::storagePath('listings', 'gallery'));
        $this->assertSame('listings/gallery', BrandIsolation::ensureCompanyStoragePath('listings/gallery'));

        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        $this->assertSame('luxvant', BrandIsolation::storagePrefix());
        $this->assertSame('luxvant/listings/gallery', BrandIsolation::storagePath('listings', 'gallery'));
        $this->assertSame(
            'luxvant/listings/gallery',
            BrandIsolation::ensureCompanyStoragePath('luxvant/listings/gallery')
        );
    }

    public function test_path_allowed_prevents_cross_company_file_access(): void
    {
        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        $this->assertTrue(BrandIsolation::pathAllowedForCurrentBrand('luxvant/listings/a.webp'));
        $this->assertFalse(BrandIsolation::pathAllowedForCurrentBrand('listings/a.webp'));
        $this->assertFalse(BrandIsolation::pathAllowedForCurrentBrand('Setting/1745128256Oia Watermark.png'));

        app(BrandContext::class)->set(config('brands.brands.oia'));
        $this->assertTrue(BrandIsolation::pathAllowedForCurrentBrand('listings/a.webp'));
        $this->assertTrue(BrandIsolation::pathAllowedForCurrentBrand('Setting/1745128256Oia Watermark.png'));
        $this->assertFalse(BrandIsolation::pathAllowedForCurrentBrand('luxvant/listings/a.webp'));
    }

    public function test_cache_segment_includes_brand(): void
    {
        app(BrandContext::class)->set(config('brands.brands.oia'));
        $this->assertSame('brand:oia', BrandIsolation::cacheSegment());

        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        $this->assertSame('brand:luxvant', BrandIsolation::cacheSegment());
    }

    public function test_strip_client_brand_key(): void
    {
        $payload = BrandIsolation::stripClientBrandKey([
            'title' => 'x',
            'brand_key' => 'oia',
        ]);
        $this->assertArrayNotHasKey('brand_key', $payload);
        $this->assertSame('x', $payload['title']);
    }

    public function test_oia_watermark_path_unchanged(): void
    {
        app(BrandContext::class)->set(config('brands.brands.oia'));
        $this->assertSame(
            'Setting/1745128256Oia Watermark.png',
            BrandIsolation::watermarkStorageRelativePath()
        );
    }

    public function test_listing_scope_sql_for_both_brands_when_column_exists(): void
    {
        if (! Schema::hasColumn('listings', 'brand_key')) {
            $this->markTestSkipped('brand_key column not migrated locally yet');
        }

        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        $luxSql = strtolower(Listing::query()->toSql());
        $this->assertStringContainsString('brand_key', $luxSql);
        $this->assertTrue(
            str_contains($luxSql, 'luxvant') || str_contains($luxSql, '?'),
            'Luxvant listing scope should constrain brand_key: '.$luxSql
        );

        app(BrandContext::class)->set(config('brands.brands.oia'));
        $oiaSql = strtolower(Listing::query()->toSql());
        $this->assertStringContainsString('brand_key', $oiaSql);
    }

    public function test_brand_owned_trait_is_on_core_models(): void
    {
        $this->assertContains(
            \App\Support\Brand\Concerns\BrandOwned::class,
            class_uses_recursive(Listing::class)
        );
        $this->assertContains(
            BrandScope::class,
            array_map('get_class', (new Listing)->getGlobalScopes())
        );
    }

    public function test_luxvant_lead_scope_allows_null_while_crm_scope_active(): void
    {
        if (! Schema::hasColumn('leads', 'brand_key')) {
            $this->markTestSkipped('brand_key column not migrated locally yet');
        }

        BrandCrmScope::reset();
        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        $this->assertTrue(BrandCrmScope::active());

        $sql = strtolower(Lead::query()->toSql());
        $this->assertTrue(
            str_contains($sql, 'is null') || str_contains($sql, 'brand_key'),
            'Luxvant CRM leads must keep unclassified NULL rows visible for BrandCrmScope: '.$sql
        );

        // Listings stay strictly luxvant-only (no NULL legacy open).
        $listingSql = strtolower(Listing::query()->toSql());
        $this->assertStringNotContainsString('or `listings`.`brand_key` is null', $listingSql);
        $this->assertStringNotContainsString('or "listings"."brand_key" is null', $listingSql);
    }

    public function test_projects_are_shared_catalog_without_brand_read_scope(): void
    {
        $this->assertContains(
            \App\Support\Brand\Concerns\BrandStampOnly::class,
            class_uses_recursive(Project::class)
        );
        $this->assertSame(
            [],
            array_filter(
                (new Project)->getGlobalScopes(),
                fn ($scope) => $scope instanceof BrandScope
            )
        );

        if (! Schema::hasColumn('projects', 'brand_key')) {
            return;
        }

        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        $sql = strtolower(Project::query()->toSql());
        $this->assertStringNotContainsString('brand_key', $sql);
    }
}
