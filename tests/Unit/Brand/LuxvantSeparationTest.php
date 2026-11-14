<?php

namespace Tests\Unit\Brand;

use App\Models\User;
use App\Rules\BrandEmailDomain;
use App\Support\Brand\BrandContext;
use App\Support\Brand\BrandCrmScope;
use App\Support\Brand\BrandIsolation;
use App\Support\Brand\BrandResolver;
use App\Support\Brand\BrandStorage;
use App\Support\Brand\CompanyFileService;
use App\Helpers\ImageHelper;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Luxvant / OIA separation: login, email domain, storage, watermark, settings.
 */
class LuxvantSeparationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BrandIsolation::clearColumnCache();
        BrandCrmScope::reset();
    }

    protected function tearDown(): void
    {
        app(BrandContext::class)->set(config('brands.brands.oia'));
        BrandCrmScope::reset();
        parent::tearDown();
    }

    private function user(int $id, ?string $brand, bool $superAdmin = false): User
    {
        $user = new class extends User {
            public bool $fakeSuperAdmin = false;

            public function hasRole($roles, ?string $guard = null): bool
            {
                return $this->fakeSuperAdmin && in_array('super_admin', (array) $roles, true);
            }

            public function hasAnyRole(...$roles): bool
            {
                return $this->hasRole(collect($roles)->flatten()->all());
            }
        };
        $user->forceFill(['id' => $id, 'brand_key' => $brand, 'email' => "u{$id}@example.test"]);
        $user->fakeSuperAdmin = $superAdmin;

        return $user;
    }

    // ── Login ────────────────────────────────────────────────────────────

    public function test_luxvant_member_cannot_access_oia_host(): void
    {
        $resolver = new BrandResolver;
        $this->assertFalse($resolver->userMayAccess(config('brands.brands.oia'), $this->user(910001, 'luxvant')));
    }

    public function test_oia_member_cannot_access_luxvant_host(): void
    {
        $resolver = new BrandResolver;
        $this->assertFalse($resolver->userMayAccess(config('brands.brands.luxvant'), $this->user(910002, 'oia')));
        $this->assertFalse($resolver->userMayAccess(config('brands.brands.luxvant'), $this->user(910003, null)));
    }

    public function test_members_access_their_own_host(): void
    {
        $resolver = new BrandResolver;
        $this->assertTrue($resolver->userMayAccess(config('brands.brands.oia'), $this->user(910004, 'oia')));
        $this->assertTrue($resolver->userMayAccess(config('brands.brands.oia'), $this->user(910005, null)));
        $this->assertTrue($resolver->userMayAccess(config('brands.brands.luxvant'), $this->user(910006, 'luxvant')));
    }

    public function test_super_admin_is_shared_on_both_hosts(): void
    {
        $resolver = new BrandResolver;
        $sa = $this->user(910007, 'oia', true);
        $this->assertTrue($resolver->userMayAccess(config('brands.brands.oia'), $sa));
        $this->assertTrue($resolver->userMayAccess(config('brands.brands.luxvant'), $sa));
    }

    // ── Email domain ────────────────────────────────────────────────────

    public function test_email_domain_follows_host_brand(): void
    {
        $rule = fn ($email) => Validator::make(['email' => $email], ['email' => [new BrandEmailDomain]])->passes();

        app(BrandContext::class)->set(config('brands.brands.oia'));
        $this->assertTrue($rule('a@oiaproperties.com'));
        $this->assertFalse($rule('a@luxvant.com'));

        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        $this->assertTrue($rule('a@luxvant.com'));
        $this->assertFalse($rule('a@oiaproperties.com'));
    }

    public function test_unchanged_existing_email_still_validates(): void
    {
        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        $v = Validator::make(['email' => 'old@gmail.com'], ['email' => [new BrandEmailDomain('old@gmail.com')]]);
        $this->assertTrue($v->passes());
    }

    // ── Storage ─────────────────────────────────────────────────────────

    public function test_luxvant_crm_and_user_uploads_use_separate_storage(): void
    {
        app(BrandContext::class)->set(config('brands.brands.luxvant'));

        $this->assertSame('luxvant_public', BrandStorage::diskForNewUpload('listings/gallery'));
        $this->assertSame('luxvant_public', BrandStorage::diskForNewUpload('users/avatars'));
        $this->assertSame('luxvant_company', BrandStorage::diskForNewUpload('deals/5/docs'));
        $this->assertSame('luxvant_company', BrandStorage::diskForNewUpload('comments/attachments'));
        $this->assertSame('luxvant_company', BrandStorage::diskForNewUpload('listings/documents'));
    }

    public function test_shared_catalog_and_other_files_stay_on_main_storage(): void
    {
        app(BrandContext::class)->set(config('brands.brands.luxvant'));

        $this->assertSame('public', BrandStorage::diskForNewUpload('projects/gallery'));
        $this->assertSame('public', BrandStorage::diskForNewUpload('developers/avatars'));
        $this->assertSame('projects/gallery', BrandIsolation::ensureCompanyStoragePath('projects/gallery'));
        $this->assertSame('company', BrandStorage::diskForNewUpload('employees/1/passport'));
        $this->assertSame('luxvant/employees/1', BrandIsolation::ensureCompanyStoragePath('employees/1'));
    }

    public function test_oia_uploads_are_unchanged(): void
    {
        app(BrandContext::class)->set(config('brands.brands.oia'));

        $this->assertSame('public', BrandStorage::diskForNewUpload('listings/gallery'));
        $this->assertSame('company', BrandStorage::diskForNewUpload('deals/5/docs'));
        $this->assertSame('listings/gallery', BrandIsolation::ensureCompanyStoragePath('listings/gallery'));
    }

    public function test_luxvant_listing_image_lands_on_luxvant_disk(): void
    {
        Storage::fake('public');
        Storage::fake('luxvant_public');
        app(BrandContext::class)->set(config('brands.brands.luxvant'));

        $file = UploadedFile::fake()->image('photo.jpg', 400, 300);
        $result = ImageHelper::compressAndConvertToWebP($file, 'listings/gallery');

        $this->assertStringStartsWith('luxvant/listings/gallery/', $result['path']);
        Storage::disk('luxvant_public')->assertExists($result['path']);
        Storage::disk('public')->assertMissing($result['path']);
        $this->assertSame('luxvant_public', BrandStorage::publicDiskFor($result['path']));

        $this->assertTrue(ImageHelper::deleteImage($result['path']));
        Storage::disk('luxvant_public')->assertMissing($result['path']);
    }

    public function test_legacy_luxvant_file_on_main_disk_still_resolves(): void
    {
        Storage::fake('public');
        Storage::fake('luxvant_public');
        app(BrandContext::class)->set(config('brands.brands.luxvant'));

        Storage::disk('public')->put('luxvant/listings/gallery/old.webp', 'x');
        $this->assertSame('public', BrandStorage::publicDiskFor('luxvant/listings/gallery/old.webp'));
    }

    public function test_deal_images_go_to_private_disk(): void
    {
        Storage::fake('company');
        Storage::fake('public');
        app(BrandContext::class)->set(config('brands.brands.oia'));

        $file = UploadedFile::fake()->image('passport.jpg', 200, 200);
        $result = ImageHelper::compressAndConvertToWebP($file, 'deals/7/passport');

        Storage::disk('company')->assertExists($result['path']);
        Storage::disk('public')->assertMissing($result['path']);
    }

    public function test_company_file_service_accepts_luxvant_disks_only_for_luxvant_paths(): void
    {
        Storage::fake('luxvant_company');
        app(BrandContext::class)->set(config('brands.brands.luxvant'));

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(CompanyFileService::class)->assertCanAccess('luxvant_company', 'deals/1/a.pdf');
    }

    // ── Watermark ───────────────────────────────────────────────────────

    public function test_luxvant_watermark_is_luxvant_logo(): void
    {
        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        $path = BrandIsolation::watermarkAbsolutePath();

        if ($path === null) {
            $this->markTestSkipped('Luxvant logo asset not present');
        }

        $this->assertStringNotContainsString('Oia Watermark', $path);
        $this->assertStringContainsString('luxvant', strtolower(str_replace('\\', '/', $path)));
    }

    // ── Strict CRM brand + settings ─────────────────────────────────────

    public function test_luxvant_no_longer_sees_unclassified_leads_by_default(): void
    {
        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        $this->assertFalse(BrandIsolation::luxvantAllowsUnclassifiedCrmRows('leads'));
        $this->assertFalse(BrandIsolation::luxvantAllowsUnclassifiedCrmRows('deals'));
    }

    public function test_leads_follow_responsible_luxvant_member(): void
    {
        $members = \Closure::bind(function ($ids) { self::$memberIds = $ids; }, null, BrandCrmScope::class);
        $members([501, 502]);

        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        $lux = \Illuminate\Support\Facades\DB::table('leads');
        BrandIsolation::constrain($lux, 'leads');
        $this->assertStringContainsString('`leads`.`brand_key` = ?', $lux->toSql());
        $this->assertStringContainsString('or `leads`.`responsible_person_id` in (?, ?)', $lux->toSql());

        app(BrandContext::class)->set(config('brands.brands.oia'));
        $oia = \Illuminate\Support\Facades\DB::table('deals');
        BrandIsolation::constrain($oia, 'deals');
        $this->assertStringContainsString('`deals`.`brand_key` is null', $oia->toSql());
        $this->assertStringContainsString('`deals`.`responsible_person_id` not in (?, ?)', $oia->toSql());

        BrandCrmScope::reset();
    }

    public function test_dashboard_and_heat_cache_keys_are_brand_segmented(): void
    {
        $dash = file_get_contents(app_path('Http/Controllers/Api/DashboardController.php'));
        $this->assertStringContainsString("'dashboard:' . \\App\\Support\\Brand\\BrandIsolation::cacheSegment()", $dash);
        $this->assertStringContainsString('constrainActivityToBrand', $dash);

        $heat = file_get_contents(app_path('Services/LeadKanbanHeatCache.php'));
        $this->assertStringContainsString('cacheSegment()', $heat);
    }

    public function test_owners_hot_deals_investments_and_recruitment_are_brand_owned(): void
    {
        foreach ([
            \App\Models\Owner::class,
            \App\Models\OwnerAdditionalDocument::class,
            \App\Models\HotDealRequest::class,
            \App\Models\Investment::class,
            \App\Models\InvestmentOverride::class,
            \App\Models\InvestmentScenarioVersion::class,
            \App\Models\Job::class,
            \App\Models\Applicant::class,
            \App\Models\Interview::class,
        ] as $model) {
            $this->assertContains(\App\Support\Brand\Concerns\BrandOwned::class, class_uses_recursive($model), $model);
        }
    }

    public function test_owner_and_applicant_files_use_separate_luxvant_storage(): void
    {
        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        $this->assertSame('luxvant_company', BrandStorage::diskForNewUpload('owners/documents'));
        $this->assertSame('luxvant_company', BrandStorage::diskForNewUpload('owners/additional_documents'));
        $this->assertSame('luxvant_company', BrandStorage::diskForNewUpload('applicants/resumes/3'));

        app(BrandContext::class)->set(config('brands.brands.oia'));
        $this->assertSame('company', BrandStorage::diskForNewUpload('owners/documents'));
    }

    public function test_reference_data_stays_shared(): void
    {
        foreach ([\App\Models\Area::class, \App\Models\PropertyType::class, \App\Models\Stage::class, \App\Models\Developer::class] as $model) {
            $this->assertNotContains(\App\Support\Brand\Concerns\BrandOwned::class, class_uses_recursive($model), $model);
        }
    }

    public function test_matches_and_search_alerts_are_brand_owned(): void
    {
        $this->assertContains(\App\Support\Brand\Concerns\BrandOwned::class, class_uses_recursive(\App\Models\LeadListingMatch::class));
        $this->assertContains(\App\Support\Brand\Concerns\BrandOwned::class, class_uses_recursive(\App\Models\SearchAlert::class));
    }
}
