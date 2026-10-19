<?php

namespace Tests\Feature\Deal;

use App\Http\Controllers\Api\Deal\DealController;
use App\Http\Resources\Deal\DealResource;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Deal::histories() is used by DealResource (deal detail assigned-by / assigned_at)
 * and DealPusherResource (realtime card). The history tab uses DealController::history,
 * which already skipped a null lead_id. Kanban cards use primeForCollection, which
 * already dropped null lead ids before the IN clause. Those screens must keep
 * deal-specific rows and, when a lead is linked, that lead's rows. They must not
 * pick up another unlinked deal's history.
 */
class DealDetailPerformanceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_unlinked_deal_history_does_not_match_other_null_lead_rows(): void
    {
        $template = Deal::query()->first();
        $lead = Lead::query()->first();
        if (! $template || ! $lead) {
            $this->markTestSkipped('Need a deal and a lead to build history fixtures');
        }

        $linked = $this->makeDeal($template, $lead->id);
        $unlinked = $this->makeDeal($template, null);
        $userId = $template->responsible_person_id ?: $template->added_by;

        $own = LeadHistory::query()->create([
            'deal_id' => $unlinked->id,
            'lead_id' => null,
            'user_id' => $userId,
            'changes' => ['action' => 'assigned', 'source' => 'own'],
            'created_at' => now()->subMinute(),
        ]);
        $trap = LeadHistory::query()->create([
            'deal_id' => $linked->id,
            'lead_id' => null,
            'user_id' => $userId,
            'changes' => ['action' => 'assigned', 'source' => 'other-unlinked'],
            'created_at' => now()->addHour(),
        ]);
        $leadRow = LeadHistory::query()->create([
            'deal_id' => null,
            'lead_id' => $lead->id,
            'user_id' => $userId,
            'changes' => ['action' => 'assigned', 'source' => 'lead'],
            'created_at' => now()->subMinutes(2),
        ]);
        $linkedDealRow = LeadHistory::query()->create([
            'deal_id' => $linked->id,
            'lead_id' => $lead->id,
            'user_id' => $userId,
            'changes' => ['action' => 'created', 'source' => 'linked-deal'],
            'created_at' => now()->subMinutes(3),
        ]);

        $unlinkedIds = $unlinked->fresh()->histories()->pluck('id');
        $this->assertTrue($unlinkedIds->contains($own->id));
        $this->assertFalse($unlinkedIds->contains($trap->id));
        $this->assertFalse($unlinkedIds->contains($leadRow->id));

        $linkedIds = $linked->fresh()->histories()->pluck('id');
        $this->assertTrue($linkedIds->contains($leadRow->id));
        $this->assertTrue($linkedIds->contains($linkedDealRow->id));
        $this->assertTrue($linkedIds->contains($trap->id));

        DealResource::clearCollectionPrime();
        DealResource::primeForCollection([$unlinked->fresh()]);
        $unlinked->load('stage');
        $payload = (new DealResource($unlinked))->resolve(Request::create('/api/deals/'.$unlinked->id));
        DealResource::clearCollectionPrime();

        $this->assertTrue(
            \Carbon\Carbon::parse($payload['assigned_at'])->equalTo($own->fresh()->created_at),
            'Unlinked deal must use its own assignment history'
        );
    }

    public function test_show_does_not_lazy_load_each_property_area_or_type(): void
    {
        $admin = User::role('super_admin')->first() ?? User::find(1);
        $deal = Deal::query()->withCount('properties')->orderByDesc('properties_count')->first();
        if (! $admin || ! $deal || $deal->properties_count < 1) {
            $this->markTestSkipped('Need an admin and a deal with properties');
        }

        auth()->setUser($admin);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = app(DealController::class)->show($deal->fresh());

        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $this->assertSame(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $data = $json['data'] ?? [];

        foreach (['id', 'deal_name', 'properties', 'parties', 'documents', 'listing', 'responsible_person', 'assigned_at'] as $key) {
            $this->assertArrayHasKey($key, $data);
        }

        $properties = $data['properties'] ?? [];
        $this->assertNotEmpty($properties);
        $first = $properties[0];
        foreach (['id', 'property_type_name', 'area_name', 'payment_proof', 'display_name'] as $key) {
            $this->assertArrayHasKey($key, $first);
        }

        $singleArea = $queries->filter(fn ($sql) => preg_match('/from `areas` where `areas`\.`id` = \?/i', $sql))->count();
        $singleType = $queries->filter(fn ($sql) => preg_match('/from `property_types` where `property_types`\.`id` = \?/i', $sql))->count();
        $this->assertSame(0, $singleArea, 'Area titles must be eager-loaded, not one query per property');
        $this->assertSame(0, $singleType, 'Property types must be eager-loaded, not one query per property');
        $this->assertLessThan(40, $queries->count());
    }

    private function makeDeal(Deal $template, ?int $leadId): Deal
    {
        return Deal::query()->create([
            'added_by' => $template->added_by,
            'lead_id' => $leadId,
            'deal_number' => 'P1HIST-'.uniqid(),
            'deal_type' => $template->deal_type,
            'stage_id' => $template->stage_id,
            'deal_name' => 'Phase 1 history scope',
            'status' => $template->status,
            'responsible_person_id' => $template->responsible_person_id,
            'created_by' => $template->created_by ?: $template->added_by,
        ]);
    }
}
