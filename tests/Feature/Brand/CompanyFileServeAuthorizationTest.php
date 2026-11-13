<?php

namespace Tests\Feature\Brand;

use App\Models\Deal;
use App\Models\User;
use App\Support\Brand\BrandContext;
use App\Support\Brand\BrandIsolation;
use App\Support\Brand\CompanyFileAuthorizer;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class CompanyFileServeAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BrandIsolation::clearColumnCache();
        Storage::fake('company');
    }

    public function test_unauthenticated_signed_url_is_rejected(): void
    {
        Storage::disk('company')->put('deals/1/a.pdf', 'secret');
        $url = URL::temporarySignedRoute(
            'company-files.serve',
            now()->addMinutes(5),
            [
                'disk' => 'company',
                'path' => 'deals/1/a.pdf',
                'resource' => CompanyFileAuthorizer::TYPE_DEAL,
                'resource_id' => 1,
            ]
        );

        $relative = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);
        $this->get($relative)->assertUnauthorized();
    }

    public function test_tampered_resource_id_fails_signature(): void
    {
        $url = URL::temporarySignedRoute(
            'company-files.serve',
            now()->addMinutes(5),
            [
                'disk' => 'company',
                'path' => 'deals/1/a.pdf',
                'resource' => CompanyFileAuthorizer::TYPE_DEAL,
                'resource_id' => 1,
            ]
        );
        $tampered = preg_replace('/resource_id=1/', 'resource_id=999', $url, 1);
        $relative = parse_url($tampered, PHP_URL_PATH).'?'.parse_url($tampered, PHP_URL_QUERY);

        $user = User::query()->first();
        if (! $user) {
            $this->markTestSkipped('No users in DB');
        }

        $this->withHeader('Authorization', 'Bearer '.JWTAuth::fromUser($user))
            ->get($relative)
            ->assertForbidden(); // invalid signature → 403 from ValidateSignature
    }

    public function test_same_brand_unauthorized_user_gets_403_not_file(): void
    {
        if (! Schema::hasTable('deals')) {
            $this->markTestSkipped('deals table missing');
        }

        app(BrandContext::class)->set(config('brands.brands.oia'));

        $deal = Deal::query()->orderByDesc('id')->first();
        if (! $deal) {
            $this->markTestSkipped('No deals');
        }

        $path = 'deals/'.$deal->id.'/probe-acl.pdf';
        Storage::disk('company')->put($path, 'TOPSECRET');

        $viewer = User::query()
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['super_admin', 'admin']))
            ->orderByDesc('id')
            ->first();
        if (! $viewer || $viewer->canViewDeal($deal)) {
            $this->markTestSkipped('No unauthorized same-brand viewer available');
        }

        $url = URL::temporarySignedRoute(
            'company-files.serve',
            now()->addMinutes(5),
            [
                'disk' => 'company',
                'path' => $path,
                'resource' => CompanyFileAuthorizer::TYPE_DEAL,
                'resource_id' => $deal->id,
            ]
        );
        $relative = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);

        $response = $this->withHeader('Authorization', 'Bearer '.JWTAuth::fromUser($viewer))
            ->get($relative);

        $this->assertTrue(in_array($response->status(), [403, 404], true), 'status='.$response->status());
        $this->assertStringNotContainsString('TOPSECRET', $response->getContent());
    }

    public function test_cross_brand_path_returns_404(): void
    {
        $user = User::query()->first();
        if (! $user) {
            $this->markTestSkipped('No users');
        }

        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        Storage::disk('company')->put('deals/1/oia-legacy.pdf', 'x');

        $url = URL::temporarySignedRoute(
            'company-files.serve',
            now()->addMinutes(5),
            [
                'disk' => 'company',
                'path' => 'deals/1/oia-legacy.pdf',
                'resource' => CompanyFileAuthorizer::TYPE_DEAL,
                'resource_id' => 1,
            ]
        );
        $relative = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);

        $this->withHeader('Authorization', 'Bearer '.JWTAuth::fromUser($user))
            ->get($relative)
            ->assertStatus(404);
    }
}
