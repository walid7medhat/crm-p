<?php

namespace Tests\Unit\Brand;

use App\Support\Brand\BrandContext;
use App\Support\Brand\BrandIsolation;
use App\Support\Brand\CompanyFilePolicy;
use App\Support\Brand\CompanyFileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyFileIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BrandIsolation::clearColumnCache();
        Storage::fake('public');
        Storage::fake('company');
        Storage::fake('luxvant_public');
        Storage::fake('luxvant_company');
    }

    public function test_public_allowlist_and_sensitive_classification(): void
    {
        $this->assertTrue(CompanyFilePolicy::isPublicAllowlisted('listings/gallery/a.webp'));
        $this->assertTrue(CompanyFilePolicy::isPublicAllowlisted('luxvant/listings/gallery/a.webp'));
        $this->assertTrue(CompanyFilePolicy::isSensitive('deals/1/docs/a.pdf'));
        $this->assertTrue(CompanyFilePolicy::isSensitive('luxvant/deals/1/docs/a.pdf'));
        $this->assertSame('company', CompanyFilePolicy::diskForNewUpload('deals/1/docs'));
        $this->assertSame('public', CompanyFilePolicy::diskForNewUpload('listings/gallery'));
    }

    public function test_path_traversal_normalized(): void
    {
        $this->assertSame('deals/a.pdf', CompanyFilePolicy::normalize('../deals/./a.pdf'));
        $this->assertSame('deals/a.pdf', CompanyFilePolicy::normalize('storage/deals/a.pdf'));
    }

    public function test_sensitive_upload_uses_company_disk_with_luxvant_prefix(): void
    {
        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        $file = UploadedFile::fake()->create('secret.pdf', 20, 'application/pdf');
        $result = app(CompanyFileService::class)->storeInDirectory(
            $file,
            'deals/9/docs',
            \App\Support\Brand\CompanyFileAuthorizer::TYPE_DEAL,
            9
        );

        // Luxvant deal files live on the separate Luxvant private storage.
        $this->assertSame('luxvant_company', $result['disk']);
        $this->assertStringStartsWith('luxvant/deals/9/docs/', $result['path']);
        Storage::disk('luxvant_company')->assertExists($result['path']);
        Storage::disk('company')->assertMissing($result['path']);
        $this->assertNotNull($result['url']);
        $this->assertStringContainsString('company-files/serve', (string) $result['url']);
        $this->assertStringContainsString('resource=deal', (string) $result['url']);
        $this->assertStringContainsString('resource_id=9', (string) $result['url']);
    }

    public function test_sensitive_url_without_resource_binding_is_null(): void
    {
        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        $file = UploadedFile::fake()->create('secret.pdf', 20, 'application/pdf');
        $result = app(CompanyFileService::class)->storeInDirectory($file, 'comments/attachments');
        $this->assertSame('luxvant_company', $result['disk']);
        $this->assertNull($result['url']);
    }

    public function test_oia_sensitive_upload_keeps_legacy_relative_path_shape(): void
    {
        app(BrandContext::class)->set(config('brands.brands.oia'));
        $file = UploadedFile::fake()->create('secret.pdf', 20, 'application/pdf');
        $result = app(CompanyFileService::class)->storeInDirectory($file, 'deals/9/docs');

        $this->assertSame('company', $result['disk']);
        $this->assertStringStartsWith('deals/9/docs/', $result['path']);
        $this->assertStringNotContainsString('luxvant/', $result['path']);
    }

    public function test_cross_brand_delete_rejected(): void
    {
        Storage::disk('company')->put('luxvant/deals/x.pdf', 'x');
        app(BrandContext::class)->set(config('brands.brands.oia'));

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(CompanyFileService::class)->deleteAuthorized('luxvant/deals/x.pdf');
    }

    public function test_delete_requires_matching_expected_parent_path(): void
    {
        Storage::disk('company')->put('deals/own.pdf', 'x');
        app(BrandContext::class)->set(config('brands.brands.oia'));

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(CompanyFileService::class)->deleteAuthorized('deals/own.pdf', 'deals/other.pdf');
    }

    public function test_assert_can_access_rejects_cross_brand_preview(): void
    {
        Storage::disk('company')->put('deals/oia.pdf', 'x');
        app(BrandContext::class)->set(config('brands.brands.luxvant'));

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(CompanyFileService::class)->assertCanAccess('company', 'deals/oia.pdf');
    }

    public function test_attendance_paths_have_no_resolvable_owner_without_schema(): void
    {
        // Live attendances table has no attachment column — fail closed.
        $binding = app(\App\Support\Brand\CompanyFileAuthorizer::class)
            ->resolveBindingFromPath('attendance/12/proof.pdf');
        $this->assertNull($binding);

        app(BrandContext::class)->set(config('brands.brands.oia'));
        $url = app(CompanyFileService::class)->urlFor('company', 'attendance/12/proof.pdf');
        $this->assertNull($url);
    }
}
