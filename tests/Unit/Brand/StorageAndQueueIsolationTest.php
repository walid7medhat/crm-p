<?php

namespace Tests\Unit\Brand;

use App\Jobs\ProcessLeadAutoAssignmentJob;
use App\Support\Brand\BrandContext;
use App\Support\Brand\BrandIsolation;
use App\Support\Brand\Concerns\BrandAwareJob;
use Tests\TestCase;

class StorageAndQueueIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BrandIsolation::clearColumnCache();
    }

    public function test_luxvant_uploads_use_namespace_oia_legacy_unchanged(): void
    {
        app(BrandContext::class)->set(config('brands.brands.oia'));
        $this->assertSame('deals/1/docs', BrandIsolation::ensureCompanyStoragePath('deals/1/docs'));

        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        $this->assertSame('luxvant/deals/1/docs', BrandIsolation::ensureCompanyStoragePath('deals/1/docs'));
    }

    public function test_path_allowed_both_directions(): void
    {
        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        $this->assertTrue(BrandIsolation::pathAllowedForCurrentBrand('luxvant/deals/a.pdf'));
        $this->assertFalse(BrandIsolation::pathAllowedForCurrentBrand('deals/a.pdf'));

        app(BrandContext::class)->set(config('brands.brands.oia'));
        $this->assertTrue(BrandIsolation::pathAllowedForCurrentBrand('deals/a.pdf'));
        $this->assertFalse(BrandIsolation::pathAllowedForCurrentBrand('luxvant/deals/a.pdf'));
    }

    public function test_assert_path_allowed_aborts_cross_brand(): void
    {
        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        BrandIsolation::assertPathAllowedForCurrentBrand('listings/hero/x.webp');
    }

    public function test_brand_aware_job_captures_and_clears_context_between_jobs(): void
    {
        $this->assertContains(BrandAwareJob::class, class_uses_recursive(ProcessLeadAutoAssignmentJob::class));

        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        $luxJob = new ProcessLeadAutoAssignmentJob(101);
        $this->assertSame('luxvant', $luxJob->brandKey);

        app(BrandContext::class)->set(config('brands.brands.oia'));
        $oiaJob = new ProcessLeadAutoAssignmentJob(202);
        $this->assertSame('oia', $oiaJob->brandKey);

        // Simulate worker: restore luxvant then clear, then oia must not see luxvant leftover.
        $luxJob->restoreBrandContext();
        $this->assertSame('luxvant', BrandIsolation::trustedKey());
        $seen = null;
        $luxJob->runWithBrandContext(function () use (&$seen) {
            $seen = BrandIsolation::trustedKey();
        });
        $this->assertSame('luxvant', $seen);
        // finally cleared to default brand (oia)
        $this->assertSame('oia', BrandIsolation::trustedKey());

        $oiaJob->runWithBrandContext(function () use (&$seen) {
            $seen = BrandIsolation::trustedKey();
        });
        $this->assertSame('oia', $seen);
        $this->assertSame('oia', BrandIsolation::trustedKey());
    }

    public function test_ai_settings_cache_key_includes_brand_segment(): void
    {
        $src = file_get_contents(app_path('Models/AiSalesIntelligence/AiSalesIntelligenceSetting.php'));
        $this->assertStringContainsString('BrandIsolation::cacheSegment()', $src);
        $this->assertStringContainsString('BrandOwned', $src);
    }

    public function test_system_campaign_uses_brand_owned(): void
    {
        $this->assertContains(
            \App\Support\Brand\Concerns\BrandOwned::class,
            class_uses_recursive(\App\Models\SystemCampaign::class)
        );
    }

    public function test_company_touching_jobs_use_brand_aware_trait(): void
    {
        foreach ([
            \App\Jobs\CheckSearchAlerts::class,
            \App\Jobs\SendInvitationEmailJob::class,
            \App\Jobs\RecalculateSalesAgentScoreJob::class,
            \App\Jobs\ProcessBitrixLeadJob::class,
            \App\Jobs\ProcessPropertyFinderEventJob::class,
            \App\Jobs\AiSalesIntelligence\RecalculateAllAiSalesIntelligenceJob::class,
        ] as $job) {
            $this->assertContains(BrandAwareJob::class, class_uses_recursive($job), $job);
        }
    }

    public function test_bitrix_and_propertyfinder_force_oia_brand(): void
    {
        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        $bitrix = new \App\Jobs\ProcessBitrixLeadJob(['ID' => 1], 1);
        $this->assertSame('oia', $bitrix->brandKey);

        $pf = new \App\Jobs\ProcessPropertyFinderEventJob(1);
        $this->assertSame('oia', $pf->brandKey);
    }

    public function test_controllers_use_authorized_delete_helper(): void
    {
        $listing = file_get_contents(app_path('Http/Controllers/Api/Listing/ListingController.php'));
        $this->assertStringContainsString('deletePublicPath', $listing);
        $this->assertStringNotContainsString("Storage::disk('public')->delete(", $listing);

        $deal = file_get_contents(app_path('Http/Controllers/Api/Deal/DealController.php'));
        $this->assertStringContainsString('deletePublicPath', $deal);
    }
}
