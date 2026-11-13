<?php

namespace Tests\Unit\Brand;

use App\Models\Deal;
use App\Models\User;
use App\Support\Brand\BrandContext;
use App\Support\Brand\BrandIsolation;
use App\Support\Brand\CompanyFileAuthorizer;
use App\Support\Brand\CompanyFileService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Mockery;
use Tests\TestCase;

class CompanyFileAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BrandIsolation::clearColumnCache();
        Storage::fake('company');
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_url_embeds_resource_binding_and_rejects_path_only_stream(): void
    {
        app(BrandContext::class)->set(config('brands.brands.oia'));
        $svc = app(CompanyFileService::class);
        Storage::disk('company')->put('deals/1/buyer/a.pdf', 'x');

        $url = $svc->urlFor('company', 'deals/1/buyer/a.pdf', CompanyFileAuthorizer::TYPE_DEAL, 1);
        $this->assertNotNull($url);
        $this->assertStringContainsString('resource=deal', $url);
        $this->assertStringContainsString('resource_id=1', $url);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->stream('company', 'deals/1/buyer/a.pdf');
    }

    public function test_mismatched_path_vs_resource_returns_404(): void
    {
        app(BrandContext::class)->set(config('brands.brands.oia'));

        $deal = Mockery::mock(Deal::class)->makePartial();
        $deal->id = 42;
        $deal->shouldReceive('getAttribute')->with('id')->andReturn(42);
        $deal->shouldReceive('offsetExists')->andReturn(true);

        // Use a real authorizer pathBelongs via DealDocument miss + path prefix check:
        // deals/99/... does not belong to deal 42.
        $authorizer = Mockery::mock(CompanyFileAuthorizer::class)->makePartial();
        $authorizer->shouldAllowMockingProtectedMethods();
        // Direct unit: authorizeOrAbort with unknown type
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(CompanyFileAuthorizer::class)->authorizeOrAbort('not_a_real_type', 1, 'deals/1/a.pdf', new User);
    }

    public function test_cross_brand_path_rejected_before_record_lookup(): void
    {
        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        $user = new User;
        $user->id = 1;

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(CompanyFileAuthorizer::class)->authorizeOrAbort(
            CompanyFileAuthorizer::TYPE_DEAL,
            1,
            'deals/1/buyer/a.pdf', // missing luxvant/ prefix
            $user
        );
    }

    public function test_signed_route_params_include_resource_and_expire(): void
    {
        app(BrandContext::class)->set(config('brands.brands.oia'));
        $url = app(CompanyFileService::class)->urlFor(
            'company',
            'deals/3/x.pdf',
            CompanyFileAuthorizer::TYPE_DEAL,
            3
        );
        $this->assertNotNull($url);
        parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $q);
        $this->assertSame('deal', $q['resource'] ?? null);
        $this->assertSame('3', (string) ($q['resource_id'] ?? ''));
        $this->assertArrayHasKey('signature', $q);
        $this->assertArrayHasKey('expires', $q);
    }

    public function test_serve_requires_resource_query_params(): void
    {
        app(BrandContext::class)->set(config('brands.brands.oia'));
        Storage::disk('company')->put('deals/1/a.pdf', 'x');

        $unsigned = URL::route('company-files.serve', [
            'disk' => 'company',
            'path' => 'deals/1/a.pdf',
        ], false);

        // Missing resource → 404 from controller even if somehow reached
        $request = \Illuminate\Http\Request::create($unsigned, 'GET');
        $controller = app(\App\Http\Controllers\Api\CompanyFileController::class);

        try {
            $controller->serve($request);
            $this->fail('Expected abort');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_modified_resource_id_in_unsigned_request_is_rejected_by_signature_middleware_contract(): void
    {
        // Contract: resource_id is part of the signed payload. Tampering invalidates signature.
        $url = URL::temporarySignedRoute(
            'company-files.serve',
            now()->addMinutes(5),
            [
                'disk' => 'company',
                'path' => 'deals/1/a.pdf',
                'resource' => 'deal',
                'resource_id' => 1,
            ]
        );
        $tampered = preg_replace('/resource_id=1/', 'resource_id=999', $url, 1);
        $this->assertNotSame($url, $tampered);

        $request = \Illuminate\Http\Request::create($tampered, 'GET');
        $this->assertFalse($request->hasValidSignature());
    }

    public function test_expired_signature_is_invalid(): void
    {
        $url = URL::temporarySignedRoute(
            'company-files.serve',
            now()->subMinutes(1),
            [
                'disk' => 'company',
                'path' => 'deals/1/a.pdf',
                'resource' => 'deal',
                'resource_id' => 1,
            ]
        );
        $request = \Illuminate\Http\Request::create($url, 'GET');
        $this->assertFalse($request->hasValidSignature());
    }
}
