<?php

namespace Tests\Feature\Listing;

use App\Models\Deal;
use App\Models\DealDocument;
use App\Models\DealParty;
use App\Models\Listing;
use App\Models\Stage;
use App\Models\User;
use App\Support\Listing\ListingSellerParty;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Seller captured on "Sold Out" is saved on the listing (deal_parties / deal_documents with
 * listing_id, deal_id NULL) and the SAME rows are linked to the deal created on that listing.
 * The deal's sales agent can't see it; admins and the listing side can.
 *
 * Runs on the local DB inside a transaction (never RefreshDatabase).
 */
class ListingSellerPartyTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['public', 'company', 'luxvant_public', 'luxvant_company'] as $disk) {
            Storage::fake($disk);
        }
        ListingSellerParty::resetMemo();
    }

    // Chapter 1 — A marks the listing sold with seller details + documents.
    public function test_sold_out_saves_seller_and_documents_on_the_listing(): void
    {
        $t = $this->scenario();

        $this->markSold($t['agentA'], $t['listing1'], [
            'sold_by' => 'another_agent',
            'agent_id' => $t['salesB']->id,
        ], 'Seller', 'One', '0501111111')->assertOk();

        $listing = $t['listing1']->fresh();
        $this->assertSame('converted', $listing->status);
        $this->assertSame($t['salesB']->id, (int) $listing->sold_by_agent_id);

        $party = DealParty::query()->where('listing_id', $listing->id)->sole();
        $this->assertNull($party->deal_id);
        $this->assertSame('seller', $party->party_type);
        $this->assertSame('Seller', $party->first_name);
        $this->assertSame('0501111111', $party->phone);

        $docs = DealDocument::query()->where('deal_party_id', $party->id)->get();
        $this->assertCount(3, $docs);
        $this->assertTrue($docs->every(fn ($d) => $d->deal_id === null && (int) $d->listing_id === $listing->id));
        $this->assertEqualsCanonicalizing(['passport', 'national_id', 'title_deed'], $docs->pluck('document_type')->all());

        // Listing side sees it; the buyer-side sales agent doesn't.
        $this->asUser($t['agentA'])->getJson("/api/listings/properties/{$listing->id}/seller-party")
            ->assertOk()->assertJsonPath('data.party.first_name', 'Seller');
        $this->asUser($t['salesB'])->getJson("/api/listings/properties/{$listing->id}/seller-party")
            ->assertStatus(403);
    }

    // Seller data and documents are required — the sale is not saved without them.
    public function test_sold_out_is_rejected_without_required_seller_data_or_files(): void
    {
        $t = $this->scenario();
        $url = "/api/listings/properties/{$t['listing1']->id}/mark-converted";
        $headers = ['Accept' => 'application/json'];
        $details = [
            'first_name' => 'Seller', 'last_name' => 'One', 'phone' => '0501111111',
            'nationality' => 'Egypt', 'residency_status' => 'resident',
        ];
        $passport = fn () => ['document_type' => 'passport', 'file' => UploadedFile::fake()->create('p.pdf', 10, 'application/pdf')];
        $nationalId = fn () => ['document_type' => 'national_id', 'file' => UploadedFile::fake()->create('n.pdf', 10, 'application/pdf')];

        // No seller at all.
        $this->asUser($t['agentA'])->patch($url, ['sold_by' => 'me'], $headers)->assertStatus(422);

        // Missing phone.
        $this->asUser($t['agentA'])->patch($url, [
            'sold_by' => 'me',
            'seller_details' => array_merge($details, ['phone' => '']),
            'seller_documents' => [$passport(), $nationalId()],
        ], $headers)->assertStatus(422)->assertJsonValidationErrors(['seller_details.phone']);

        // No documents.
        $this->asUser($t['agentA'])->patch($url, [
            'sold_by' => 'me',
            'seller_details' => $details,
        ], $headers)->assertStatus(422);

        // Resident without National ID.
        $this->asUser($t['agentA'])->patch($url, [
            'sold_by' => 'me',
            'seller_details' => $details,
            'seller_documents' => [$passport()],
        ], $headers)->assertStatus(422)->assertJsonValidationErrors(['seller_documents.national_id']);

        // Non-resident without passport.
        $this->asUser($t['agentA'])->patch($url, [
            'sold_by' => 'me',
            'seller_details' => array_merge($details, ['residency_status' => 'non_resident']),
            'seller_documents' => [$nationalId()],
        ], $headers)->assertStatus(422)->assertJsonValidationErrors(['seller_documents.passport']);

        // Nothing was saved.
        $this->assertNotSame('converted', $t['listing1']->fresh()->status);
        $this->assertSame(0, DealParty::query()->where('listing_id', $t['listing1']->id)->count());

        // Non-resident with passport only is enough.
        $this->asUser($t['agentA'])->patch($url, [
            'sold_by' => 'me',
            'seller_details' => array_merge($details, ['residency_status' => 'non_resident']),
            'seller_documents' => [$passport()],
        ], $headers)->assertOk();
        $this->assertSame('converted', $t['listing1']->fresh()->status);
    }

    // "Sold by me" now records the selling agent.
    public function test_sold_by_me_sets_sold_by_agent(): void
    {
        $t = $this->scenario();

        $this->markSold($t['agentA'], $t['listing1'], ['sold_by' => 'me'], 'Me', 'Seller')->assertOk();

        $this->assertSame($t['agentA']->id, (int) $t['listing1']->fresh()->sold_by_agent_id);
    }

    // Owner / A-to-A sales have no OIA deal → no seller step, nothing required or saved.
    public function test_owner_and_a_to_a_sales_need_no_seller(): void
    {
        $t = $this->scenario();
        $headers = ['Accept' => 'application/json'];

        $this->asUser($t['agentA'])->patch("/api/listings/properties/{$t['listing1']->id}/mark-converted", [
            'sold_by' => 'owner',
        ], $headers)->assertOk();

        $this->asUser($t['agentA'])->patch("/api/listings/properties/{$t['listing2']->id}/mark-converted", [
            'sold_by' => 'a_to_a',
            'a_to_a_details' => [
                'company_name' => 'Other Co', 'agent_name' => 'Agent X',
                'agent_phone' => '0503333333', 'commercial_license' => 'CL-1',
            ],
        ], $headers)->assertOk();

        $this->assertSame('converted', $t['listing1']->fresh()->status);
        $this->assertSame('converted', $t['listing2']->fresh()->status);
        $this->assertSame(0, DealParty::query()
            ->whereIn('listing_id', [$t['listing1']->id, $t['listing2']->id])->count());
    }

    // Chapter 2 + 3 — B creates the deal; same rows are linked; B can't see them, admin can.
    public function test_deal_links_same_seller_rows_and_hides_them_from_the_deal_agent(): void
    {
        $t = $this->scenario();
        $this->markSold($t['agentA'], $t['listing1'], [
            'sold_by' => 'another_agent', 'agent_id' => $t['salesB']->id,
        ], 'Seller', 'One')->assertOk();
        $party = DealParty::query()->where('listing_id', $t['listing1']->id)->sole();

        $dealId = $this->createDeal($t['salesB'], $t['listing1']);

        // Same row, now on the deal — no copy.
        $party->refresh();
        $this->assertSame($dealId, (int) $party->deal_id);
        $this->assertNotNull($party->deal_property_id);
        $this->assertSame('primary', $party->party_role);
        $this->assertSame(1, DealParty::query()->where('deal_id', $dealId)->where('party_type', 'seller')->count());
        $this->assertSame(3, DealDocument::query()->where('deal_party_id', $party->id)->where('deal_id', $dealId)->count());

        // B (deal agent): hidden.
        $asB = $this->asUser($t['salesB'])->getJson("/api/deals/{$dealId}")->assertOk();
        $this->assertTrue($asB->json('data.seller_hidden'));
        $this->assertNull($asB->json('data.seller_name'));
        $this->assertFalse(collect($asB->json('data.parties'))->contains('party_type', 'seller'));
        $this->assertFalse(collect($asB->json('data.documents'))->contains('document_category', 'seller'));

        // B can't delete a seller document.
        $doc = DealDocument::query()->where('deal_party_id', $party->id)->first();
        $this->asUser($t['salesB'])->deleteJson("/api/deals/documents/{$doc->id}")->assertStatus(403);
        $this->assertNotNull($doc->fresh());

        // B can't overwrite the seller.
        $this->asUser($t['salesB'])->postJson("/api/deals/{$dealId}/update-partial", [
            'seller_first_name' => 'Hacked',
            'seller_last_name' => 'Name',
        ]);
        $this->assertSame('Seller', $party->fresh()->first_name);

        // Super admin: full seller + docs.
        ListingSellerParty::resetMemo();
        $asAdmin = $this->asUser($t['super'])->getJson("/api/deals/{$dealId}")->assertOk();
        $this->assertFalse($asAdmin->json('data.seller_hidden'));
        $this->assertSame('Seller One', $asAdmin->json('data.seller_name'));
        $seller = collect($asAdmin->json('data.parties'))->firstWhere('party_type', 'seller');
        $this->assertTrue($seller['is_listing_seller']);
        $this->assertCount(3, $seller['documents']);

        // admin / accountant roles always see listing sellers.
        $this->assertTrue(ListingSellerParty::canSeeListingSeller($t['admin'], $t['listing1']->id));
        $this->assertFalse(ListingSellerParty::canSeeListingSeller($t['salesB'], $t['listing1']->id));
    }

    // Chapter 4 — multi-property: one seller per sold unit, main unit's seller primary.
    public function test_multi_property_deal_gets_one_seller_per_unit(): void
    {
        $t = $this->scenario();
        $this->markSold($t['agentA'], $t['listing1'], ['sold_by' => 'another_agent', 'agent_id' => $t['salesB']->id], 'First', 'Seller')->assertOk();
        $this->markSold($t['agentA'], $t['listing2'], ['sold_by' => 'another_agent', 'agent_id' => $t['salesB']->id], 'Second', 'Seller')->assertOk();

        $dealId = $this->createDeal($t['salesB'], $t['listing1']);
        $deal = Deal::query()->findOrFail($dealId);
        $deal->properties()->create(['sort_order' => 1, 'listing_id' => $t['listing2']->id]);
        ListingSellerParty::reconcile($deal);

        $first = DealParty::query()->where('listing_id', $t['listing1']->id)->sole();
        $second = DealParty::query()->where('listing_id', $t['listing2']->id)->sole();
        $this->assertSame($dealId, (int) $first->deal_id);
        $this->assertSame($dealId, (int) $second->deal_id);
        $this->assertSame('primary', $first->party_role);
        $this->assertSame('secondary', $second->party_role);

        // Remove the main unit → its seller goes back to the listing, the other becomes primary.
        $deal->properties()->where('listing_id', $t['listing1']->id)->delete();
        $deal->forceFill(['listing_id' => null])->save();
        ListingSellerParty::reconcile($deal->fresh());

        $first->refresh();
        $this->assertNull($first->deal_id);
        $this->assertNull($first->deal_property_id);
        $this->assertSame(0, DealDocument::query()->where('deal_party_id', $first->id)->whereNotNull('deal_id')->count());
        $this->assertSame('primary', $second->fresh()->party_role);
    }

    // Chapter 5 — revert deletes an unlinked seller, keeps a linked one.
    public function test_revert_deletes_unlinked_seller_but_keeps_deal_data(): void
    {
        $t = $this->scenario();
        $this->markSold($t['agentA'], $t['listing1'], ['sold_by' => 'me'], 'Free', 'Seller')->assertOk();
        $this->markSold($t['agentA'], $t['listing2'], ['sold_by' => 'another_agent', 'agent_id' => $t['salesB']->id], 'Linked', 'Seller')->assertOk();
        $dealId = $this->createDeal($t['salesB'], $t['listing2']);

        $free = DealParty::query()->where('listing_id', $t['listing1']->id)->sole();
        $freeDocIds = DealDocument::query()->where('deal_party_id', $free->id)->pluck('id');

        $this->asUser($t['agentA'])->patchJson("/api/listings/properties/{$t['listing1']->id}/revert-converted")->assertOk();
        $this->assertNull(DealParty::query()->find($free->id));
        $this->assertSame(0, DealDocument::query()->whereIn('id', $freeDocIds)->count());
        $this->assertNull($t['listing1']->fresh()->sold_by_agent_id);

        $this->asUser($t['agentA'])->patchJson("/api/listings/properties/{$t['listing2']->id}/revert-converted")->assertOk();
        $linked = DealParty::query()->where('listing_id', $t['listing2']->id)->sole();
        $this->assertSame($dealId, (int) $linked->deal_id);
    }

    // Chapter 6 — old sold listing (no seller row) falls back to the owner.
    public function test_old_sold_listing_falls_back_to_owner(): void
    {
        $t = $this->scenario();
        $owner = \App\Models\Owner::query()->create([
            'added_by' => $t['agentA']->id,
            'first_name' => 'Owner',
            'last_name' => 'Fallback',
            'email' => uniqid('owner', true).'@example.test',
            'phone_number' => '0509999999',
            'nationality' => 'Egypt',
            'residency_status' => 'resident',
        ]);
        $t['listing1']->forceFill([
            'owner_id' => $owner->id,
            'previous_owner_id' => null,
            'status' => 'converted',
            'sold_by' => 'another_agent',
            'sold_by_agent_id' => $t['salesB']->id,
        ])->save();

        $dealId = $this->createDeal($t['salesB'], $t['listing1']);

        $party = DealParty::query()->where('listing_id', $t['listing1']->id)->sole();
        $this->assertSame($dealId, (int) $party->deal_id);
        $this->assertSame('Owner', $party->first_name);
        $this->assertSame('0509999999', $party->phone);
    }

    // ------------------------------------------------------------------

    private function markSold(User $user, Listing $listing, array $payload, string $first, string $last, string $phone = '0500000000')
    {
        return $this->asUser($user)->patch("/api/listings/properties/{$listing->id}/mark-converted", array_merge($payload, [
            'seller_details' => [
                'first_name' => $first,
                'last_name' => $last,
                'phone' => $phone,
                'email' => strtolower($first).'@example.test',
                'nationality' => 'Egypt',
                'residency_status' => 'resident',
            ],
            'seller_documents' => [
                ['document_type' => 'passport', 'file' => UploadedFile::fake()->create('passport.pdf', 20, 'application/pdf')],
                ['document_type' => 'national_id', 'file' => UploadedFile::fake()->create('eid.pdf', 20, 'application/pdf')],
                ['document_type' => 'title_deed', 'file' => UploadedFile::fake()->create('deed.pdf', 20, 'application/pdf')],
            ],
        ]), ['Accept' => 'application/json']);
    }

    private function createDeal(User $user, Listing $listing): int
    {
        $stage = Stage::query()->where('stage_type', 'deal')->where('deal_type', 'secondary')->orderBy('order')->first();
        if (! $stage) {
            $this->markTestSkipped('Need a secondary deal stage');
        }

        $response = $this->asUser($user)->postJson('/api/deals/store/new', [
            'deal_type' => 'secondary',
            'stage_id' => $stage->id,
            'source' => 'Test',
            'deal_name' => 'Seller link test',
            'listing_id' => $listing->id,
            'responsible_person_id' => $user->id,
            'buyer_first_name' => 'Buyer',
            'buyer_last_name' => 'Person',
            'buyer_phone' => '0502222222',
            'buyer_email' => 'buyer@example.test',
            'buyer_dob' => '1990-01-01',
            'buyer_nationality' => 'Egypt',
            'buyer_residency_status' => 'resident',
            'buyer_city' => 'Dubai',
            'buyer_language' => 'English',
        ]);
        $response->assertStatus(201);
        ListingSellerParty::resetMemo();

        return (int) $response->json('data.id');
    }

    private function scenario(): array
    {
        $super = $this->makeUser('super_admin');
        $admin = $this->makeUser('admin', ['parent_id' => $super->id]);
        $managerA = $this->makeUser('manager', ['parent_id' => $admin->id]);
        $managerB = $this->makeUser('manager', ['parent_id' => $admin->id]);
        $agentA = $this->makeUser('sales', ['parent_id' => $managerA->id]);
        $salesB = $this->makeUser('sales', ['parent_id' => $managerB->id]);

        $listings = Listing::query()->whereNotNull('id')->limit(2)->get();
        if ($listings->count() < 2) {
            $this->markTestSkipped('Need two listings');
        }
        foreach ($listings as $listing) {
            $listing->forceFill([
                'approved' => true,
                'status' => 'published',
                'is_archived' => false,
                'agent_id' => $agentA->id,
                'sold_by' => null,
                'sold_by_agent_id' => null,
            ])->save();
            // Start clean for these listings (rolled back after the test).
            DealDocument::query()->where('listing_id', $listing->id)->delete();
            DealParty::query()->where('listing_id', $listing->id)->delete();
        }

        return [
            'super' => $super,
            'admin' => $admin,
            'agentA' => $agentA,
            'salesB' => $salesB,
            'listing1' => $listings[0]->fresh(),
            'listing2' => $listings[1]->fresh(),
        ];
    }

    private function makeUser(string $role, array $extra = []): User
    {
        $user = User::factory()->create(array_merge([
            'status' => 'active',
            'email' => uniqid($role, true).'@example.test',
        ], $extra));
        Role::findOrCreate($role, 'api');
        $user->assignRole($role);

        return $user;
    }

    private function asUser(User $user): self
    {
        return $this->withHeader('Authorization', 'Bearer '.JWTAuth::fromUser($user));
    }
}
