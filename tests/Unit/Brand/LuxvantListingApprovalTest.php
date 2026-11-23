<?php

namespace Tests\Unit\Brand;

use App\Models\User;
use App\Services\Listing\ListingApprovalPolicy;
use App\Support\Brand\BrandContext;
use Tests\TestCase;

/** Luxvant admins / branch admins approve Luxvant listings; OIA rules unchanged. */
class LuxvantListingApprovalTest extends TestCase
{
    protected function tearDown(): void
    {
        app(BrandContext::class)->set(config('brands.brands.oia'));
        parent::tearDown();
    }

    private function userWithRoles(array $roles): User
    {
        $u = new class extends User {
            public array $fakeRoles = [];

            public function hasRole($roles, ?string $guard = null): bool
            {
                return (bool) array_intersect((array) $roles, $this->fakeRoles);
            }

            public function hasAnyRole(...$roles): bool
            {
                $flat = [];
                array_walk_recursive($roles, function ($r) use (&$flat) { $flat[] = $r; });

                return (bool) array_intersect($flat, $this->fakeRoles);
            }
        };
        $u->forceFill(['id' => 777]);
        $u->fakeRoles = $roles;

        return $u;
    }

    public function test_luxvant_admins_approve_everything_on_luxvant(): void
    {
        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        $policy = app(ListingApprovalPolicy::class);

        $this->assertTrue($policy->isGlobalApprover($this->userWithRoles(['admin'])));
        $this->assertTrue($policy->isGlobalApprover($this->userWithRoles(['branch_admin'])));
        $this->assertFalse($policy->isGlobalApprover($this->userWithRoles(['sales'])));
    }

    public function test_oia_branch_admin_is_not_a_global_approver(): void
    {
        app(BrandContext::class)->set(config('brands.brands.oia'));
        $policy = app(ListingApprovalPolicy::class);

        $this->assertFalse($policy->isLuxvantAdminApprover($this->userWithRoles(['branch_admin'])));
        $this->assertFalse($policy->isGlobalApprover($this->userWithRoles(['branch_admin'])));
        $this->assertTrue($policy->isGlobalApprover($this->userWithRoles(['admin'])));
    }
}
