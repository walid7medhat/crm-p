<?php

namespace Tests\Unit\Branch;

use App\Http\Middleware\ApplyBranchView;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Listing;
use App\Models\User;
use App\Support\Branch\BranchView;
use App\Support\Brand\BrandContext;
use App\Support\Brand\BrandIsolation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * OIA Abu Dhabi / Dubai view switch (super admin + #30, GET only).
 */
class BranchViewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BranchView::reset();
        app(BrandContext::class)->set(config('brands.brands.oia'));
        Cache::put('branch_view:members:dubai', [59, 700, 701], 600);
        Cache::put('branch_view:members:abu_dhabi', [25, 800], 600);
    }

    protected function tearDown(): void
    {
        BranchView::reset();
        app(BrandContext::class)->set(config('brands.brands.oia'));
        Cache::forget('branch_view:members:dubai');
        Cache::forget('branch_view:members:abu_dhabi');
        parent::tearDown();
    }

    private function user(int $id, bool $superAdmin = false): User
    {
        $user = new class extends User {
            public bool $fakeSuperAdmin = false;

            public function hasRole($roles, ?string $guard = null): bool
            {
                return $this->fakeSuperAdmin && in_array('super_admin', (array) $roles, true);
            }
        };
        $user->forceFill(['id' => $id]);
        $user->fakeSuperAdmin = $superAdmin;

        return $user;
    }

    public function test_off_by_default_leaves_queries_unchanged(): void
    {
        $this->assertFalse(BranchView::active());
        $this->assertStringNotContainsString('responsible_person_id', Lead::query()->toSql());
        $this->assertSame('', BranchView::cacheSuffix());
    }

    public function test_dubai_filters_leads_deals_listings_by_branch_members(): void
    {
        BranchView::set('dubai');

        $lead = Lead::query();
        $this->assertStringContainsString('"leads"."responsible_person_id" in', str_replace('`', '"', $lead->toSql()));
        $this->assertContains(700, $lead->getBindings());

        $deal = Deal::query();
        $this->assertStringContainsString('"deals"."responsible_person_id" in', str_replace('`', '"', $deal->toSql()));

        $listing = Listing::query();
        $this->assertStringContainsString('"listings"."agent_id" in', str_replace('`', '"', $listing->toSql()));
        $this->assertNotContains(800, $listing->getBindings(), 'Abu Dhabi agent must not be in the Dubai view');
    }

    public function test_raw_dashboard_queries_and_users_follow_the_branch(): void
    {
        BranchView::set('abu_dhabi');

        $raw = DB::table('deal_properties')->join('deals', 'deals.id', '=', 'deal_properties.deal_id');
        BrandIsolation::constrain($raw, 'deals', 'deals.brand_key');
        $this->assertStringContainsString('"deals"."responsible_person_id" in', str_replace('`', '"', $raw->toSql()));
        $this->assertContains(800, $raw->getBindings());

        $users = BrandIsolation::constrainUsers(User::query());
        $this->assertStringContainsString('"users"."id" in', str_replace('`', '"', $users->toSql()));

        $this->assertStringEndsWith(':branch:abu_dhabi', BrandIsolation::cacheSegment());
    }

    public function test_only_super_admin_and_user_30_on_oia(): void
    {
        $this->assertTrue(BranchView::canUse($this->user(1, true)));
        $this->assertTrue(BranchView::canUse($this->user(30)));
        $this->assertFalse(BranchView::canUse($this->user(31)));
        $this->assertFalse(BranchView::canUse(null));

        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        $this->assertFalse(BranchView::canUse($this->user(1, true)), 'Luxvant host never gets the switch');
    }

    public function test_normalize_key(): void
    {
        $this->assertSame('abu_dhabi', BranchView::normalizeKey('Abu Dhabi'));
        $this->assertSame('dubai', BranchView::normalizeKey('dubai'));
        $this->assertNull(BranchView::normalizeKey('cairo'));
        $this->assertNull(BranchView::normalizeKey(''));
    }

    public function test_middleware_ignores_header_without_allowed_user_and_on_writes(): void
    {
        $mw = new ApplyBranchView();
        $seen = null;

        // No bearer token → no user → off.
        $get = Request::create('/api/leads', 'GET');
        $get->headers->set(BranchView::HEADER, 'dubai');
        $mw->handle($get, function () use (&$seen) {
            $seen = BranchView::current();

            return response('ok');
        });
        $this->assertNull($seen);

        // Writes are never filtered.
        $post = Request::create('/api/leads', 'POST');
        $post->headers->set(BranchView::HEADER, 'dubai');
        $mw->handle($post, function () use (&$seen) {
            $seen = BranchView::current();

            return response('ok');
        });
        $this->assertNull($seen);
        $this->assertFalse(BranchView::active(), 'state is reset after the request');
    }
}
