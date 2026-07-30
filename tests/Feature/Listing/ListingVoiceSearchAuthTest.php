<?php

namespace Tests\Feature\Listing;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Auth / route contract tests for POST /api/listings/voice-search.
 *
 * Full HTTP role-matrix tests require Spatie roles tables. This suite asserts
 * the route is wired with jwt.auth + role:super_admin|admin, and that
 * unauthenticated callers are rejected. Parsing coverage is in Unit tests.
 */
class ListingVoiceSearchAuthTest extends TestCase
{
    public function test_voice_search_route_requires_jwt_and_admin_roles(): void
    {
        $route = collect(Route::getRoutes())
            ->first(function ($route) {
                return in_array('POST', $route->methods(), true)
                    && $route->uri() === 'api/listings/voice-search';
            });

        $this->assertNotNull($route, 'POST api/listings/voice-search route must exist');

        $middleware = $route->gatherMiddleware();

        $this->assertTrue(
            collect($middleware)->contains(fn ($m) => $m === 'jwt.auth' || str_contains((string) $m, 'JwtAuth')),
            'voice-search must be behind jwt.auth'
        );

        $this->assertTrue(
            collect($middleware)->contains(fn ($m) => is_string($m) && str_contains($m, 'role:super_admin|admin')),
            'voice-search must require role:super_admin|admin'
        );
    }

    public function test_unauthenticated_user_cannot_access_voice_search(): void
    {
        $this->postJson('/api/listings/voice-search', [
            'transcript' => '2 bedroom apartment in Reem Island',
        ])->assertStatus(401);
    }

    public function test_authenticated_non_admin_gets_403(): void
    {
        // Bypass JWT + BlockBots so we isolate Spatie role middleware.
        $this->withoutMiddleware([
            \App\Http\Middleware\JwtAuthMiddleware::class,
            \App\Http\Middleware\BlockBots::class,
        ]);

        $user = new class extends User {
            public $id = 999001;

            public $exists = true;

            public $status = 'active';

            public function hasRole($roles, ?string $guard = null): bool
            {
                return false;
            }

            public function hasAnyRole(...$roles): bool
            {
                return false;
            }
        };

        $this->actingAs($user, 'api');

        $this->postJson('/api/listings/voice-search', [
            'transcript' => '2 bedroom apartment in Reem Island',
        ])->assertStatus(403);
    }

    public function test_authenticated_admin_role_is_allowed_by_middleware(): void
    {
        $this->withoutMiddleware([
            \App\Http\Middleware\JwtAuthMiddleware::class,
            \App\Http\Middleware\BlockBots::class,
        ]);

        $user = new class extends User {
            public $id = 999002;

            public $exists = true;

            public $status = 'active';

            public function hasRole($roles, ?string $guard = null): bool
            {
                $roles = is_array($roles) ? $roles : [$roles];

                return in_array('admin', $roles, true) || in_array('super_admin', $roles, true);
            }

            public function hasAnyRole(...$roles): bool
            {
                $flat = [];
                foreach ($roles as $role) {
                    if ($role instanceof \Illuminate\Support\Collection) {
                        $flat = array_merge($flat, $role->all());
                    } elseif (is_array($role)) {
                        $flat = array_merge($flat, $role);
                    } else {
                        $flat[] = $role;
                    }
                }

                // Spatie may pass "super_admin|admin" as one string.
                $expanded = [];
                foreach ($flat as $item) {
                    foreach (explode('|', (string) $item) as $part) {
                        $expanded[] = trim($part);
                    }
                }

                return (bool) array_intersect(['admin', 'super_admin'], $expanded);
            }
        };

        $this->actingAs($user, 'api');

        // Role gate should pass; endpoint may still 200/422 depending on listing schema.
        $response = $this->postJson('/api/listings/voice-search', [
            'transcript' => '2 bedroom apartment in Reem Island under 1.5 million',
        ]);

        $this->assertNotEquals(403, $response->status(), 'Admin must not be blocked by role middleware');
        $this->assertNotEquals(401, $response->status(), 'Admin must not be blocked as unauthenticated');
    }

    public function test_authenticated_super_admin_role_is_allowed_by_middleware(): void
    {
        $this->withoutMiddleware([
            \App\Http\Middleware\JwtAuthMiddleware::class,
            \App\Http\Middleware\BlockBots::class,
        ]);

        $user = new class extends User {
            public $id = 999003;

            public $exists = true;

            public $status = 'active';

            public function hasRole($roles, ?string $guard = null): bool
            {
                $roles = is_array($roles) ? $roles : [$roles];

                return in_array('super_admin', $roles, true);
            }

            public function hasAnyRole(...$roles): bool
            {
                $flat = [];
                foreach ($roles as $role) {
                    if (is_array($role)) {
                        $flat = array_merge($flat, $role);
                    } else {
                        $flat[] = $role;
                    }
                }
                $expanded = [];
                foreach ($flat as $item) {
                    foreach (explode('|', (string) $item) as $part) {
                        $expanded[] = trim($part);
                    }
                }

                return in_array('super_admin', $expanded, true);
            }
        };

        $this->actingAs($user, 'api');

        $response = $this->postJson('/api/listings/voice-search', [
            'transcript' => 'عايز شقة غرفتين في جزيرة الريم بسعر مليون ونص',
        ]);

        $this->assertNotEquals(403, $response->status(), 'Super Admin must not be blocked by role middleware');
        $this->assertNotEquals(401, $response->status(), 'Super Admin must not be blocked as unauthenticated');
    }
}
