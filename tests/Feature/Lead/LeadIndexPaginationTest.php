<?php

namespace Tests\Feature\Lead;

use App\Http\Controllers\Api\LeadController;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeadIndexPaginationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_default_index_is_paginated_not_grouped_dump(): void
    {
        $admin = User::role('super_admin')->first();
        if (! $admin || Lead::query()->count() < 1) {
            $this->markTestSkipped('Need super_admin and leads');
        }

        auth()->setUser($admin);
        $response = app(LeadController::class)->index(
            Request::create('/api/leads', 'GET', ['per_page' => 10, 'page' => 1])
        );

        $this->assertSame(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertTrue($json['success'] ?? false);
        $this->assertIsArray($json['data'] ?? null);
        $this->assertArrayHasKey('pagination', $json);
        $this->assertLessThanOrEqual(10, count($json['data']));

        // Must not be the legacy grouped-by-stage shape.
        if (count($json['data']) > 0) {
            $this->assertArrayNotHasKey('leads', $json['data'][0]);
            $this->assertArrayHasKey('id', $json['data'][0]);
        }
    }

    public function test_stage_counts_only_returns_aggregates_without_lead_rows(): void
    {
        $admin = User::role('super_admin')->first();
        if (! $admin || Lead::query()->count() < 1) {
            $this->markTestSkipped('Need super_admin and leads');
        }

        auth()->setUser($admin);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = app(LeadController::class)->index(
            Request::create('/api/leads', 'GET', [
                'paginate' => 1,
                'stage_counts_only' => 1,
            ])
        );

        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('total', $json['data'] ?? []);
        $this->assertArrayHasKey('stages', $json['data'] ?? []);
        $this->assertIsArray($json['data']['stages']);

        // Should not serialize full lead collections.
        $this->assertLessThan(25, $queries->count(), 'stage_counts_only should stay cheap');
    }

    public function test_with_total_exposes_pagination_total(): void
    {
        $admin = User::role('super_admin')->first();
        if (! $admin || Lead::query()->count() < 1) {
            $this->markTestSkipped('Need super_admin and leads');
        }

        auth()->setUser($admin);
        $response = app(LeadController::class)->index(
            Request::create('/api/leads', 'GET', [
                'paginate' => 1,
                'with_total' => 1,
                'per_page' => 5,
                'page' => 1,
            ])
        );

        $json = json_decode($response->getContent(), true);
        $this->assertNotNull($json['pagination']['total'] ?? null);
        $this->assertGreaterThan(0, (int) $json['pagination']['total']);
    }

    public function test_grouped_over_max_returns_422_instead_of_silent_truncation(): void
    {
        $admin = User::role('super_admin')->first();
        if (! $admin || Lead::query()->count() < 2) {
            $this->markTestSkipped('Need super_admin and at least 2 leads');
        }

        // Force the safety ceiling below the current visible set.
        config(['leads.index_grouped_max' => 1]);

        auth()->setUser($admin);
        $response = app(LeadController::class)->index(
            Request::create('/api/leads', 'GET', ['grouped' => 1])
        );

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_sales_visibility_still_scopes_paginated_index(): void
    {
        $sales = User::role('sales')->first();
        if (! $sales) {
            $this->markTestSkipped('Need a sales user');
        }

        auth()->setUser($sales);
        $response = app(LeadController::class)->index(
            Request::create('/api/leads', 'GET', [
                'paginate' => 1,
                'with_total' => 1,
                'per_page' => 15,
            ])
        );

        $this->assertSame(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        foreach ($json['data'] ?? [] as $row) {
            $lead = Lead::query()->find($row['id'] ?? null);
            $this->assertNotNull($lead);
            $this->assertTrue(
                $sales->canViewLead($lead),
                'Sales must not receive lead '.$lead->id
            );
        }
    }

    public function test_total_count_endpoint_returns_integer_total(): void
    {
        $admin = User::role('super_admin')->first();
        if (! $admin) {
            $this->markTestSkipped('Need super_admin');
        }

        auth()->setUser($admin);
        $response = app(LeadController::class)->totalCount();
        $this->assertSame(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        // ApiResponse uses `status`, not `success`.
        $this->assertTrue(($json['status'] ?? false) || ($json['success'] ?? false));
        $this->assertIsInt($json['data']['total'] ?? null);
    }
}
