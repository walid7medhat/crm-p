<?php

namespace Tests\Feature\Deal;

use App\Http\Controllers\Api\Deal\DealController;
use App\Http\Resources\Deal\DealKanbanCardResource;
use App\Http\Resources\Deal\DealResource;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Focused contract tests for Deal Kanban board payload slim-down (Step 7).
 * Uses the live DB (same as other Feature suites that need real data).
 */
class DealKanbanPayloadTest extends TestCase
{
    public function test_grouped_by_stage_and_get_more_routes_exist(): void
    {
        $grouped = collect(Route::getRoutes())->first(
            fn ($route) => in_array('GET', $route->methods(), true)
                && $route->uri() === 'api/deals/grouped-by-stage'
        );
        $more = collect(Route::getRoutes())->first(
            fn ($route) => in_array('GET', $route->methods(), true)
                && $route->uri() === 'api/deals/get-more/by-stage'
        );

        $this->assertNotNull($grouped);
        $this->assertNotNull($more);
    }

    public function test_kanban_card_resource_omits_heavy_detail_keys(): void
    {
        $deal = Deal::query()
            ->with([
                'lead:id,lead_name,converted_at',
                'stage:id,name,color,order',
                'responsiblePerson',
                'addedBy',
                'parties:id,deal_id,party_type,party_role,first_name,last_name',
            ])
            ->first();

        if (!$deal) {
            $this->markTestSkipped('No deals in database');
        }

        DealKanbanCardResource::primeForCollection([$deal]);
        try {
            $card = (new DealKanbanCardResource($deal))->resolve();
        } finally {
            DealKanbanCardResource::clearCollectionPrime();
        }

        $this->assertArrayHasKey('id', $card);
        $this->assertArrayHasKey('deal_name', $card);
        $this->assertArrayHasKey('buyer_name', $card);
        $this->assertArrayHasKey('deal_total_amount', $card);
        $this->assertArrayHasKey('responsible_person', $card);
        $this->assertArrayHasKey('parent', $card);
        $this->assertArrayHasKey('assigned_at', $card);
        $this->assertArrayHasKey('stage', $card);

        foreach (['parties', 'documents', 'properties', 'listing', 'area', 'developer'] as $heavy) {
            $this->assertArrayNotHasKey($heavy, $card, "Kanban card must not serialize {$heavy}");
        }
    }

    public function test_grouped_by_stage_returns_lightweight_cards(): void
    {
        $admin = User::role('super_admin')->first() ?? User::find(1);
        if (!$admin) {
            $this->markTestSkipped('No admin user');
        }
        auth()->setUser($admin);

        $response = app(DealController::class)->getDealsGroupedByStage(
            Request::create('/api/deals/grouped-by-stage', 'GET', [
                'deal_type' => 'primary',
                'per_page' => 10,
            ])
        );

        $this->assertSame(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertTrue($json['success'] ?? false);

        $stages = $json['data'] ?? [];
        $this->assertIsArray($stages);

        $firstCard = null;
        foreach ($stages as $stage) {
            $this->assertArrayHasKey('deals_count', $stage);
            $this->assertArrayHasKey('total_count', $stage);
            $this->assertArrayHasKey('has_more_pages', $stage);
            $deals = $stage['deals'] ?? [];
            if ($deals !== [] && $firstCard === null) {
                $firstCard = $deals[0];
            }
        }

        if ($firstCard === null) {
            $this->markTestSkipped('No primary deal cards to assert shape');
        }

        foreach (['parties', 'documents', 'properties', 'listing'] as $heavy) {
            $this->assertArrayNotHasKey($heavy, $firstCard);
        }
        $this->assertArrayHasKey('buyer_name', $firstCard);
        $this->assertArrayHasKey('deal_total_amount', $firstCard);
    }

    public function test_show_still_returns_detail_relations(): void
    {
        $admin = User::role('super_admin')->first() ?? User::find(1);
        $deal = Deal::query()->first();
        if (!$admin || !$deal) {
            $this->markTestSkipped('Missing admin or deal');
        }
        auth()->setUser($admin);

        $response = app(DealController::class)->show($deal);
        $json = json_decode($response->getContent(), true);
        $this->assertTrue($json['success'] ?? false);
        $data = $json['data'] ?? [];

        // Full DealResource still exposes detail collections when loaded.
        $this->assertArrayHasKey('parties', $data);
        $this->assertArrayHasKey('documents', $data);
        $this->assertArrayHasKey('properties', $data);
    }

    public function test_deal_resource_and_kanban_resource_are_distinct_classes(): void
    {
        $this->assertTrue(is_subclass_of(DealKanbanCardResource::class, DealResource::class));
        $this->assertNotSame(DealResource::class, DealKanbanCardResource::class);
    }
}
