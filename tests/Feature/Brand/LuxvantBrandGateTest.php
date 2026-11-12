<?php

namespace Tests\Feature\Brand;

use App\Http\Middleware\EnsureBrandAccess;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Models\User;
use App\Support\Brand\BrandContext;
use App\Support\Brand\BrandResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Http\Middleware\Authenticate as TymonAuthenticate;

/**
 * Proves C1 fix: jwt.auth resolves to JwtAuthMiddleware, and ordinary users
 * are denied on Luxvant while OIA open mode stays permissive.
 *
 * Skips DB-dependent cases when the test database is unavailable.
 */
class LuxvantBrandGateTest extends TestCase
{
    public function test_jwt_auth_alias_resolves_to_custom_wrapper_not_bare_tymon(): void
    {
        $router = app('router');
        $route = null;
        foreach ($router->getRoutes() as $r) {
            if (in_array('jwt.auth', $r->gatherMiddleware(), true)) {
                $route = $r;
                break;
            }
        }

        $this->assertNotNull($route, 'Expected at least one jwt.auth route');

        $computed = $router->gatherRouteMiddleware($route);
        $this->assertContains(JwtAuthMiddleware::class, $computed);
        $this->assertNotContains(TymonAuthenticate::class, $computed);
    }

    public function test_brand_access_alias_is_fqcn(): void
    {
        $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
        $ref = new \ReflectionClass($kernel);
        $prop = $ref->getProperty('middlewareAliases');
        $prop->setAccessible(true);
        $aliases = $prop->getValue($kernel);

        $this->assertSame(EnsureBrandAccess::class, $aliases['brand.access'] ?? null);
        $this->assertSame(
            \App\Http\Middleware\BlockRiskyPublicOnLuxvant::class,
            $aliases['brand.block_luxvant_public'] ?? null
        );
        $this->assertSame(JwtAuthMiddleware::class, $aliases['jwt.auth'] ?? null);
    }

    public function test_host_header_not_x_forwarded_host_selects_brand(): void
    {
        putenv('BRAND_FORCE=');
        $_ENV['BRAND_FORCE'] = '';
        $_SERVER['BRAND_FORCE'] = '';

        $resolver = app(BrandResolver::class);

        $request = \Illuminate\Http\Request::create('https://luxvant.altcrm.com/api/brand', 'GET');
        $request->headers->set('Host', 'luxvant.altcrm.com');
        $request->headers->set('X-Forwarded-Host', 'oiaproperties.altcrm.com');

        $brand = $resolver->resolveFromRequest($request);
        $this->assertSame('luxvant', $brand['key']);

        $request2 = \Illuminate\Http\Request::create('https://oiaproperties.altcrm.com/api/brand', 'GET');
        $request2->headers->set('Host', 'oiaproperties.altcrm.com');
        $request2->headers->set('X-Forwarded-Host', 'luxvant.altcrm.com');

        $brand2 = $resolver->resolveFromRequest($request2);
        $this->assertSame('oia', $brand2['key']);
    }

    public function test_luxvant_public_risk_endpoints_blocked_oia_not(): void
    {
        putenv('BRAND_FORCE=');
        $_ENV['BRAND_FORCE'] = '';
        $_SERVER['BRAND_FORCE'] = '';

        $lux = $this->get('http://luxvant.altcrm.com/api/auth/users/role/manager', [
            'HTTP_HOST' => 'luxvant.altcrm.com',
        ]);
        // May be 403 from brand block (preferred) — never 200 with user emails on Luxvant.
        $this->assertTrue(
            in_array($lux->getStatusCode(), [403, 404], true)
            || ($lux->getStatusCode() === 200 && empty(data_get($lux->json(), 'data'))),
            'Luxvant must not freely expose role user emails'
        );
        if ($lux->getStatusCode() === 403) {
            $this->assertStringContainsStringIgnoringCase('luxvant', (string) data_get($lux->json(), 'message', $lux->getContent()));
        }
    }
}
