<?php

namespace Tests\Feature\Deal;

use App\Http\Controllers\Api\Deal\DealController;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GET /deals (DealController@index) must keep full DealResource fields while
 * avoiding per-deal assignment-history and per-user hierarchy N+1 queries.
 */
class DealIndexPerformanceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_index_batches_assignment_history_and_avoids_per_deal_history_queries(): void
    {
        $admin = User::role('super_admin')->first();
        if (! $admin || Deal::query()->count() < 1) {
            $this->markTestSkipped('Need a super admin and at least one deal');
        }

        auth()->setUser($admin);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = app(DealController::class)->index(
            Request::create('/api/deals', 'GET', ['per_page' => 15])
        );

        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertTrue($json['success'] ?? false);
        $this->assertIsArray($json['data'] ?? null);
        $this->assertArrayHasKey('meta', $json);

        $pageCount = count($json['data']);
        $this->assertGreaterThan(0, $pageCount);

        foreach (['id', 'deal_name', 'stage', 'responsible_person', 'assigned_at', 'properties', 'parties', 'lead'] as $key) {
            $this->assertArrayHasKey($key, $json['data'][0], "DealResource list item must expose {$key}");
        }

        // One batched window query (+ hydrate), not one histories() query per deal.
        $perDealHistory = $queries->filter(
            fn ($q) => preg_match('/from `lead_histories`.*`changes`->.*assigned/i', $q['query'])
                || (
                    str_contains($q['query'], 'lead_histories')
                    && str_contains(strtolower($q['query']), 'assigned')
                    && ! str_contains(strtolower($q['query']), 'row_number()')
                    && preg_match('/where .*deal_id.*=/i', $q['query'])
                )
        )->count();

        $batchedHistory = $queries->filter(
            fn ($q) => str_contains($q['query'], 'lead_histories')
                && str_contains(strtolower($q['query']), 'row_number()')
        )->count();

        $this->assertGreaterThan(0, $batchedHistory, 'Expected ROW_NUMBER assignment-history prime');
        $this->assertSame(
            0,
            $perDealHistory,
            'index must not run a per-deal assigned-history query (got '.$perDealHistory.')'
        );

        // Guard against regressing to hundreds of queries on a 15-row page.
        $this->assertLessThan(
            80,
            $queries->count(),
            'Expected far fewer than the pre-optimization ~380 queries; got '.$queries->count()
        );
    }

    public function test_index_respects_visible_for_scope(): void
    {
        $admin = User::role('super_admin')->first();
        $sales = User::role('sales')->first();
        if (! $admin || ! $sales) {
            $this->markTestSkipped('Need super_admin and sales users');
        }

        auth()->setUser($sales);
        $response = app(DealController::class)->index(
            Request::create('/api/deals', 'GET', ['per_page' => 15])
        );
        $this->assertSame(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $ids = collect($json['data'] ?? [])->pluck('id')->all();

        foreach ($ids as $id) {
            $deal = Deal::query()->find($id);
            $this->assertNotNull($deal);
            $this->assertTrue(
                $sales->canViewDeal($deal),
                "Sales user must not receive deal {$id} they cannot view"
            );
        }
    }
}
