<?php

namespace Tests\Unit\Listing;

use App\Models\Owner;
use App\Models\User;
use App\Support\Listing\OwnerVisibility;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OwnerVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        OwnerVisibility::resetMemo();
    }

    protected function tearDown(): void
    {
        OwnerVisibility::resetMemo();
        Cache::forget('owner_visibility:branch_members:500');
        Cache::forget('owner_visibility:branch_members:600');
        parent::tearDown();
    }

    private function user(int $id, array $branch = [], bool $permission = false, bool $superAdmin = false): User
    {
        $u = new class extends User {
            public array $fakeBranch = [];
            public bool $fakePermission = false;
            public bool $fakeSuperAdmin = false;

            public function hasRole($roles, ?string $guard = null): bool
            {
                return $this->fakeSuperAdmin && in_array('super_admin', (array) $roles, true);
            }

            public function checkPermissionTo($permission, $guardName = null): bool
            {
                return $this->fakePermission && $permission === OwnerVisibility::PERMISSION;
            }

            public function getBranchUserIds(): array
            {
                return $this->fakeBranch ?: [$this->id];
            }
        };
        $u->forceFill(['id' => $id]);
        $u->fakeBranch = $branch;
        $u->fakePermission = $permission;
        $u->fakeSuperAdmin = $superAdmin;

        return $u;
    }

    public function test_super_admin_and_user_30_see_every_listing(): void
    {
        $this->assertTrue(OwnerVisibility::canSeeListingOf($this->user(1, [], false, true), 999));
        $this->assertTrue(OwnerVisibility::canSeeListingOf($this->user(30), 999));
    }

    public function test_permission_limits_to_own_branch(): void
    {
        $dubaiAdmin = $this->user(500, [59, 500, 501, 502], true);

        $this->assertTrue(OwnerVisibility::canSeeListingOf($dubaiAdmin, 501), 'Dubai agent listing');
        $this->assertFalse(OwnerVisibility::canSeeListingOf($dubaiAdmin, 800), 'Abu Dhabi agent listing');
        $this->assertFalse(OwnerVisibility::canSeeListingOf($dubaiAdmin, null), 'no agent');
    }

    public function test_without_permission_only_own_listing(): void
    {
        $manager = $this->user(600, [25, 600, 601], false);

        $this->assertTrue(OwnerVisibility::canSeeListingOf($manager, 600));
        $this->assertFalse(OwnerVisibility::canSeeListingOf($manager, 601), 'branch mate needs the permission');
        $this->assertFalse(OwnerVisibility::canSeeListingOf(null, 600));
    }

    public function test_unit_number_search_only_matches_visible_listings(): void
    {
        $search = function ($u) {
            $q = \App\Models\Listing::query()->where(function ($w) use ($u) {
                $w->where('reference_number', 'X');
                OwnerVisibility::orWhereSensitive($w, $u, fn ($s) => $s->where('unit_number', '1203'));
            });

            return [str_replace('`', '"', $q->toSql()), $q->getBindings()];
        };

        [$sql] = $search($this->user(1, [], false, true));
        $this->assertStringNotContainsString('agent_id', $sql, 'super admin searches every unit');

        [$sql, $bindings] = $search($this->user(500, [59, 500, 501], true));
        $this->assertStringContainsString('"listings"."agent_id" in', $sql);
        $this->assertContains(501, $bindings, 'branch agents');

        [, $bindings] = $search($this->user(600, [25, 600, 601], false));
        $this->assertContains(600, $bindings);
        $this->assertNotContains(601, $bindings, 'no permission → own listings only');
    }

    public function test_owner_query_scope(): void
    {
        $sql = fn ($u) => str_replace('`', '"', OwnerVisibility::constrainOwners(Owner::query(), $u)->toSql());

        $this->assertStringNotContainsString('added_by', $sql($this->user(1, [], false, true)));
        $this->assertStringContainsString('"added_by" = ?', $sql($this->user(600, [25, 600], false)));

        $branch = $sql($this->user(500, [59, 500, 501], true));
        $this->assertStringContainsString('"owners"."added_by" in', $branch);
        $this->assertStringContainsString('"listings"', $branch);

        $this->assertSame('own', OwnerVisibility::scopeKey($this->user(600, [], false)));
        $this->assertSame('all', OwnerVisibility::scopeKey($this->user(30)));
    }
}
