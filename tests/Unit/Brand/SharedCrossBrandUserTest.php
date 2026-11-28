<?php

namespace Tests\Unit\Brand;

use App\Models\User;
use App\Support\Brand\BrandContext;
use App\Support\Brand\BrandResolver;
use Tests\TestCase;

/** User #30 (brands.shared_user_ids) works on both OIA and Luxvant. */
class SharedCrossBrandUserTest extends TestCase
{
    protected function tearDown(): void
    {
        app(BrandContext::class)->set(config('brands.brands.oia'));
        parent::tearDown();
    }

    private function user(int $id): User
    {
        $u = new class extends User {
            public function hasRole($roles, ?string $guard = null): bool
            {
                return false;
            }

            public function hasAnyRole(...$roles): bool
            {
                return false;
            }
        };
        $u->forceFill(['id' => $id, 'brand_key' => 'oia']);

        return $u;
    }

    public function test_user_30_is_shared_by_default(): void
    {
        $this->assertTrue(BrandResolver::isSharedUserId(30));
        $this->assertTrue(BrandResolver::isSharedUserId(33));
        $this->assertFalse(BrandResolver::isSharedUserId(31));
        $this->assertTrue(BrandResolver::isCrossBrandUser($this->user(30)));
        $this->assertFalse(BrandResolver::isCrossBrandUser($this->user(31)));
    }

    public function test_user_30_may_sign_in_on_luxvant_other_oia_users_may_not(): void
    {
        $resolver = app(BrandResolver::class);
        $luxvant = config('brands.brands.luxvant');

        $this->assertTrue($resolver->userMayAccess($luxvant, $this->user(30)));
        $this->assertTrue($resolver->userMayAccess($luxvant, $this->user(33)));
        $this->assertTrue($resolver->userMayAccess(config('brands.brands.oia'), $this->user(30)));
    }

    public function test_shared_list_comes_from_config(): void
    {
        config(['brands.shared_user_ids' => [77]]);

        $this->assertTrue(BrandResolver::isSharedUserId(77));
        $this->assertFalse(BrandResolver::isSharedUserId(30));
    }
}
