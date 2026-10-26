<?php

namespace Tests\Feature\SmartSearch;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\Listing;
use App\Models\User;
use App\Support\UaeDateRange;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class SmartSearchEndpointTest extends TestCase
{
    use DatabaseTransactions;

    private User $superAdmin;

    private User $sales;

    private User $otherSales;

    private object $leadTemplate;

    private ?object $dealTemplate = null;

    private ?object $listingTemplate = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leadTemplate = DB::table('leads')->whereNull('deleted_at')->first();
        if (! $this->leadTemplate) {
            $this->markTestSkipped('Need at least one lead row for foreign-key templates');
        }

        $this->dealTemplate = DB::table('deals')->whereNull('deleted_at')->first();
        $this->listingTemplate = DB::table('listings')->first();

        foreach (['super_admin', 'sales', 'admin', 'manager', 'team_lead', 'branch_admin'] as $role) {
            Role::findOrCreate($role, 'api');
        }
        Permission::findOrCreate('leads-list', 'api');
        Permission::findOrCreate('projects-list', 'api');

        $this->superAdmin = User::factory()->create(['status' => 'active']);
        $this->superAdmin->assignRole('super_admin');

        $this->sales = User::factory()->create(['status' => 'active']);
        $this->sales->assignRole('sales');
        $this->sales->givePermissionTo('leads-list');

        $this->otherSales = User::factory()->create(['status' => 'active']);
        $this->otherSales->assignRole('sales');
        $this->otherSales->givePermissionTo('leads-list');
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/smart-search?q=test')->assertStatus(401);
    }

    public function test_short_query_returns_hint_without_results(): void
    {
        $res = $this->asUser($this->sales)->getJson('/api/smart-search?q=a');
        $res->assertOk();
        $this->assertFalse($res->json('data.executed'));
        $this->assertStringContainsString('2 characters', (string) $res->json('data.message'));
        $this->assertSame([], $res->json('data.groups'));
    }

    public function test_phone_shorter_than_four_digits_is_rejected(): void
    {
        $res = $this->asUser($this->sales)->getJson('/api/smart-search?q=050');
        $res->assertOk();
        $this->assertFalse($res->json('data.executed'));
        $this->assertStringContainsString('4 digits', (string) $res->json('data.message'));
    }

    public function test_sales_user_sees_own_lead_and_not_another_agents_lead(): void
    {
        $mine = $this->insertLead($this->sales->id, [
            'lead_name' => 'SmartSearch Own Alpha',
            'lead_number' => 'SS-OWN-'.uniqid(),
            'work_phone' => '0501112233',
        ]);
        $theirs = $this->insertLead($this->otherSales->id, [
            'lead_name' => 'SmartSearch Other Alpha',
            'lead_number' => 'SS-OTH-'.uniqid(),
            'work_phone' => '0501112233',
        ]);

        $res = $this->asUser($this->sales)->getJson('/api/smart-search?q=0501112233&types=leads');
        $res->assertOk();
        $ids = collect($res->json('data.groups.0.results'))->pluck('id')->all();
        $this->assertContains($mine, $ids);
        $this->assertNotContains($theirs, $ids);
        $payload = json_encode($res->json('data.groups'));
        $this->assertStringNotContainsString('0501112233', (string) $payload);
        $this->assertSame('••••2233', $res->json('data.query'));
        foreach ($res->json('data.groups.0.results') as $row) {
            $this->assertArrayNotHasKey('email', $row['meta'] ?? []);
            $this->assertArrayNotHasKey('phone', $row['meta'] ?? []);
            $this->assertArrayNotHasKey('work_phone', $row['meta'] ?? []);
        }
    }

    public function test_super_admin_sees_both_leads_for_same_phone(): void
    {
        $a = $this->insertLead($this->sales->id, [
            'lead_name' => 'SmartSearch Admin A',
            'lead_number' => 'SS-ADA-'.uniqid(),
            'work_phone' => '0504445566',
        ]);
        $b = $this->insertLead($this->otherSales->id, [
            'lead_name' => 'SmartSearch Admin B',
            'lead_number' => 'SS-ADB-'.uniqid(),
            'work_phone' => '0504445566',
        ]);

        $res = $this->asUser($this->superAdmin)->getJson('/api/smart-search?q=0504445566&types=leads');
        $res->assertOk();
        $ids = collect($res->json('data.groups.0.results'))->pluck('id')->all();
        $this->assertContains($a, $ids);
        $this->assertContains($b, $ids);
    }

    public function test_exact_lead_number_is_flagged(): void
    {
        $number = 'SS-EXACT-'.strtoupper(substr(uniqid(), -6));
        $id = $this->insertLead($this->sales->id, [
            'lead_name' => 'SmartSearch Exact Lead',
            'lead_number' => $number,
        ]);

        $res = $this->asUser($this->sales)->getJson('/api/smart-search?q='.urlencode($number).'&types=leads');
        $res->assertOk();
        $row = collect($res->json('data.groups.0.results'))->firstWhere('id', $id);
        $this->assertNotNull($row);
        $this->assertTrue($row['exact']);
        $this->assertSame('open_lead', $row['action']);
    }

    public function test_create_offer_phrase_does_not_write_and_blocks_action(): void
    {
        $before = DB::table('property_offers')->count();
        $res = $this->asUser($this->superAdmin)->getJson('/api/smart-search?q='.urlencode('create offer for Yas Island'));
        $res->assertOk();
        $this->assertSame('create_offer', $res->json('data.interpretation.blocked_action'));
        $this->assertSame($before, DB::table('property_offers')->count());
    }

    public function test_leads_created_today_uses_uae_day_chip(): void
    {
        $today = Carbon::now(UaeDateRange::TIMEZONE)->toDateString();
        $id = $this->insertLead($this->sales->id, [
            'lead_name' => 'SmartSearch Today Lead',
            'lead_number' => 'SS-TODAY-'.uniqid(),
            'created_at' => UaeDateRange::start($today),
        ]);

        $res = $this->asUser($this->sales)->getJson('/api/smart-search?q='.urlencode('leads created today').'&types=leads');
        $res->assertOk();
        $this->assertTrue($res->json('data.executed'));
        $chips = collect($res->json('data.interpretation.chips'));
        $this->assertTrue($chips->contains(fn ($c) => ($c['key'] ?? '') === 'created'));
        $ids = collect($res->json('data.groups.0.results'))->pluck('id')->all();
        $this->assertContains($id, $ids);
    }

    public function test_arabic_today_leads_phrase_runs(): void
    {
        $res = $this->asUser($this->sales)->getJson('/api/smart-search?q='.urlencode('الليدات اللي جت النهارده').'&types=leads');
        $res->assertOk();
        $this->assertTrue($res->json('data.executed'));
        $this->assertTrue(
            collect($res->json('data.interpretation.chips'))->contains(fn ($c) => ($c['key'] ?? '') === 'created')
        );
    }

    public function test_limit_is_capped_at_five(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $this->insertLead($this->sales->id, [
                'lead_name' => 'SmartSearch Cap '.$i.' UniqueZZ',
                'lead_number' => 'SS-CAP-'.$i.'-'.uniqid(),
            ]);
        }

        $res = $this->asUser($this->sales)->getJson('/api/smart-search?q=UniqueZZ&types=leads&limit=5');
        $res->assertOk();
        $this->assertLessThanOrEqual(5, count($res->json('data.groups.0.results') ?? []));
        $this->assertSame(5, $res->json('data.limit'));
    }

    public function test_invalid_type_is_rejected(): void
    {
        $this->asUser($this->sales)
            ->getJson('/api/smart-search?q=test&types=hackers')
            ->assertStatus(422);
    }

    public function test_deal_search_when_template_available(): void
    {
        if (! $this->dealTemplate) {
            $this->markTestSkipped('No deal template row');
        }

        $number = 'SS-DEAL-'.strtoupper(substr(uniqid(), -6));
        $id = $this->insertDeal($this->sales->id, [
            'deal_number' => $number,
            'deal_name' => 'SmartSearch Deal Fixture',
        ]);

        $res = $this->asUser($this->sales)->getJson('/api/smart-search?q='.urlencode($number).'&types=deals');
        $res->assertOk();
        $row = collect($res->json('data.groups.0.results'))->firstWhere('id', $id);
        $this->assertNotNull($row);
        $this->assertTrue($row['exact']);
        $this->assertSame('open_deal', $row['action']);
    }

    public function test_listing_reference_when_template_available(): void
    {
        if (! $this->listingTemplate) {
            $this->markTestSkipped('No listing template row');
        }

        $ref = 'SS-REF-'.strtoupper(substr(uniqid(), -6));
        $id = $this->insertListing([
            'reference_number' => $ref,
            'title' => 'SmartSearch Listing Fixture',
            'agent_id' => $this->sales->id,
        ]);

        $res = $this->asUser($this->superAdmin)->getJson('/api/smart-search?q='.urlencode($ref).'&types=listings');
        $res->assertOk();
        $row = collect($res->json('data.groups.0.results'))->firstWhere('id', $id);
        $this->assertNotNull($row);
        $this->assertTrue($row['exact']);
        $this->assertSame('open_listing', $row['action']);
    }

    public function test_bare_word_offer_needs_clarification(): void
    {
        $res = $this->asUser($this->sales)->getJson('/api/smart-search?q=offer');
        $res->assertOk();
        $this->assertFalse($res->json('data.executed'));
        $this->assertTrue($res->json('data.interpretation.needs_confirmation'));
    }

    public function test_name_query_keeps_response_contract_without_requiring_voice_parse(): void
    {
        $mine = $this->insertLead($this->sales->id, [
            'lead_name' => 'SmartSearch NameOnly Zebra',
            'lead_number' => 'SS-NAME-'.uniqid(),
        ]);

        $res = $this->asUser($this->sales)->getJson('/api/smart-search?q=Zebra&types=leads');
        $res->assertOk();
        $this->assertTrue($res->json('data.executed'));
        $this->assertIsArray($res->json('data.groups'));
        $this->assertSame('leads', $res->json('data.groups.0.type'));
        $ids = collect($res->json('data.groups.0.results'))->pluck('id')->all();
        $this->assertContains($mine, $ids);
        $this->assertArrayHasKey('interpretation', $res->json('data'));
        $this->assertArrayHasKey('chips', $res->json('data.interpretation'));
    }

    public function test_property_phrase_still_searches_listings(): void
    {
        if (! $this->listingTemplate) {
            $this->markTestSkipped('No listing template row');
        }

        $res = $this->asUser($this->superAdmin)->getJson(
            '/api/smart-search?q='.urlencode('2 bedroom apartment').'&types=listings&confirmed=1'
        );
        $res->assertOk();
        $this->assertSame('listings', $res->json('data.groups.0.type'));
        // Contract: groups array present whether or not inventory matches the phrase.
        $this->assertIsArray($res->json('data.groups.0.results'));
    }

    public function test_deal_visibility_hides_other_agents_deal(): void
    {
        if (! $this->dealTemplate) {
            $this->markTestSkipped('No deal template row');
        }

        $number = 'SS-DEAL-VIS-'.strtoupper(substr(uniqid(), -6));
        $theirs = $this->insertDeal($this->otherSales->id, [
            'deal_number' => $number,
            'deal_name' => 'SmartSearch Hidden Deal',
        ]);

        $res = $this->asUser($this->sales)->getJson('/api/smart-search?q='.urlencode($number).'&types=deals');
        $res->assertOk();
        $ids = collect($res->json('data.groups.0.results') ?? [])->pluck('id')->all();
        $this->assertNotContains($theirs, $ids);
    }

    public function test_all_types_response_includes_expected_group_keys(): void
    {
        $res = $this->asUser($this->superAdmin)->getJson('/api/smart-search?q=test&types=all');
        $res->assertOk();
        $types = collect($res->json('data.groups'))->pluck('type')->all();
        foreach (['leads', 'deals', 'listings', 'places'] as $expected) {
            $this->assertContains($expected, $types);
        }
    }

    private function asUser(User $user)
    {
        return $this->withHeader('Authorization', 'Bearer '.JWTAuth::fromUser($user));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function insertLead(int $ownerId, array $overrides): int
    {
        $now = now();
        $row = [
            'lead_number' => $overrides['lead_number'] ?? ('SS-'.uniqid()),
            'lead_name' => $overrides['lead_name'] ?? 'SmartSearch Lead',
            'first_name' => 'Smart',
            'stage_id' => $overrides['stage_id'] ?? $this->leadTemplate->stage_id,
            'added_by' => $this->leadTemplate->added_by,
            'responsible_person_id' => $ownerId,
            'work_phone' => $overrides['work_phone'] ?? null,
            'email' => $overrides['email'] ?? null,
            'area_id' => $overrides['area_id'] ?? $this->leadTemplate->area_id,
            'extra_client_requirements' => isset($overrides['extra_client_requirements'])
                ? json_encode($overrides['extra_client_requirements'])
                : null,
            'created_at' => $overrides['created_at'] ?? $now,
            'updated_at' => $now,
        ];

        return (int) DB::table('leads')->insertGetId($row);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function insertDeal(int $ownerId, array $overrides): int
    {
        $now = now();

        return (int) DB::table('deals')->insertGetId([
            'lead_id' => $overrides['lead_id'] ?? null,
            'deal_number' => $overrides['deal_number'] ?? ('SSD-'.uniqid()),
            'deal_name' => $overrides['deal_name'] ?? 'SmartSearch Deal',
            'deal_type' => $this->dealTemplate->deal_type ?? 'secondary',
            'stage_id' => $this->dealTemplate->stage_id,
            'status' => $this->dealTemplate->status ?? 'draft',
            'responsible_person_id' => $ownerId,
            'created_by' => $this->dealTemplate->created_by ?? $ownerId,
            'added_by' => $this->dealTemplate->added_by ?? $ownerId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function insertListing(array $overrides): int
    {
        $now = now();
        $row = (array) $this->listingTemplate;
        unset($row['id']);
        $row['reference_number'] = $overrides['reference_number'];
        $row['title'] = $overrides['title'] ?? 'SmartSearch Listing';
        $row['unit_number'] = $overrides['unit_number'] ?? ('U-'.substr(uniqid(), -4));
        $row['agent_id'] = $overrides['agent_id'] ?? $this->listingTemplate->agent_id;
        $row['is_active'] = 1;
        $row['is_archived'] = 0;
        $row['approved'] = 1;
        $row['status'] = $overrides['status'] ?? 'published';
        $row['created_at'] = $now;
        $row['updated_at'] = $now;

        return (int) DB::table('listings')->insertGetId($row);
    }
}
