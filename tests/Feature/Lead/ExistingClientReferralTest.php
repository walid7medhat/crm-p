<?php

namespace Tests\Feature\Lead;

use App\Http\Resources\Lead\KanbanLeadCardResource;
use App\Models\Lead;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Referral from an existing client: source_relation = "existing_client:<id>", and the card
 * carries the referring client's own lead source (shown next to the referral icon).
 */
class ExistingClientReferralTest extends TestCase
{
    use DatabaseTransactions;

    private object $template;

    protected function setUp(): void
    {
        parent::setUp();

        $template = DB::table('leads')->whereNull('deleted_at')->first();
        if (! $template) {
            $this->markTestSkipped('Need one lead row to copy required foreign keys');
        }
        $this->template = $template;
        KanbanLeadCardResource::clearKanbanMeta();
        KanbanLeadCardResource::clearEngagementUsers();
    }

    public function test_relation_helpers(): void
    {
        $this->assertSame('existing_client:42', Lead::existingClientRelation(42));
        $this->assertSame('existing_client', Lead::existingClientRelation(null));

        $this->assertTrue(Lead::isExistingClientRelation('existing_client:42'));
        $this->assertTrue(Lead::isExistingClientRelation('existing_client'));
        $this->assertFalse(Lead::isExistingClientRelation(null));
        $this->assertFalse(Lead::isExistingClientRelation('Friend'));
        $this->assertFalse(Lead::isExistingClientRelation('existing_clientele'));

        $this->assertSame(42, Lead::referrerLeadIdFromRelation('existing_client:42'));
        $this->assertNull(Lead::referrerLeadIdFromRelation('existing_client'));
        $this->assertNull(Lead::referrerLeadIdFromRelation('existing_client:abc'));
        $this->assertNull(Lead::referrerLeadIdFromRelation('Friend'));
    }

    /** One referral per client source: each card must carry its own client's source. */
    public function test_card_carries_each_clients_source(): void
    {
        $sources = [
            'Lead Form',
            'Social Media-Facebook',
            'Bayut',
            'PropertyFinder',
            'Company Website',
            'WhatsApp',
            'oiaproperties.com',
            'Call From Dubizzle',
        ];

        foreach ($sources as $source) {
            $clientId = $this->insertLead(['lead_name' => "Client {$source}", 'lead_source' => $source]);
            $referralId = $this->insertLead([
                'lead_source' => 'referral',
                'source_client_name' => "Client {$source}",
                'source_relation' => Lead::existingClientRelation($clientId),
            ]);

            $card = $this->card($referralId);
            $this->assertTrue($card['is_existing_client_referral'], "[{$source}] card not marked");
            $this->assertSame($source, $card['referrer_lead_source'], "[{$source}] card has wrong client source");
            $this->assertSame("Client {$source}", $card['source_client_name']);
        }
    }

    public function test_other_referral_is_not_marked(): void
    {
        $card = $this->card($this->insertLead([
            'lead_source' => 'referral',
            'source_client_name' => 'A Friend',
            'source_client_phone' => '0521112233',
        ]));

        $this->assertFalse($card['is_existing_client_referral']);
        $this->assertNull($card['referrer_lead_source']);
    }

    public function test_marked_without_client_id_shows_icon_but_no_client_source(): void
    {
        $card = $this->card($this->insertLead([
            'lead_source' => 'referral',
            'source_client_name' => 'Unknown client',
            'source_relation' => 'existing_client',
        ]));

        $this->assertTrue($card['is_existing_client_referral']);
        $this->assertNull($card['referrer_lead_source']);
    }

    /** @return array<string, mixed> */
    private function card(int $leadId): array
    {
        $lead = Lead::withoutGlobalScopes()->findOrFail($leadId);

        return (new KanbanLeadCardResource($lead))->toArray(request());
    }

    /** @param  array<string, mixed>  $overrides */
    private function insertLead(array $overrides): int
    {
        $now = now();

        return (int) DB::table('leads')->insertGetId(array_merge([
            'lead_number' => 'REFT-'.uniqid(),
            'lead_name' => 'Referral fixture',
            'first_name' => 'Referral',
            'stage_id' => $this->template->stage_id,
            'added_by' => $this->template->added_by,
            'responsible_person_id' => $this->template->responsible_person_id ?: $this->template->added_by,
            'brand_key' => $this->template->brand_key ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));
    }
}
