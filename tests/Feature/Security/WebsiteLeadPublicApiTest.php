<?php

namespace Tests\Feature\Security;

use App\Events\LeadUpdated;
use App\Jobs\ProcessLeadAutoAssignmentJob;
use App\Jobs\ProcessLeadIntelligenceJob;
use App\Models\Lead;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Public website / WordPress lead capture: shared secret + ignore client assignee.
 */
class WebsiteLeadPublicApiTest extends TestCase
{
    private const SECRET = 'test-website-lead-secret';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.website_lead.secret', self::SECRET);
        Config::set('services.wordpress_lead.secret', self::SECRET);
        Event::fake([LeadUpdated::class]);
        Bus::fake([ProcessLeadAutoAssignmentJob::class, ProcessLeadIntelligenceJob::class]);
    }

    public function test_website_lead_rejects_missing_secret_header(): void
    {
        $this->postJson('/api/website-lead', [
            'lead_name' => 'Secret Missing',
            'name' => 'Test',
            'email' => 'website-lead-missing-secret@example.com',
        ])->assertStatus(401)
            ->assertJsonPath('status', 'error');
    }

    public function test_website_lead_rejects_wrong_secret(): void
    {
        $this->withHeader('X-Website-Lead-Secret', 'wrong-secret')
            ->postJson('/api/website-lead', [
                'lead_name' => 'Wrong Secret',
                'name' => 'Test',
                'email' => 'website-lead-wrong-secret@example.com',
            ])->assertStatus(401)
            ->assertJsonPath('status', 'error');
    }

    public function test_website_lead_fails_closed_when_secret_not_configured(): void
    {
        Config::set('services.website_lead.secret', '');

        $this->withHeader('X-Website-Lead-Secret', self::SECRET)
            ->postJson('/api/website-lead', [
                'lead_name' => 'Unconfigured',
                'email' => 'website-lead-unconfigured@example.com',
            ])->assertStatus(401)
            ->assertJsonPath('status', 'error');
    }

    public function test_website_lead_ignores_client_assignee_and_queues_assignment(): void
    {
        $attackerAssigneeId = 999999;

        $response = $this->withHeader('X-Website-Lead-Secret', self::SECRET)
            ->postJson('/api/website-lead', [
                'lead_name' => 'Website Assignee Guard',
                'name' => 'Web',
                'email' => 'website-lead-assignee-guard@example.com',
                'phone' => '0500000001',
                'responsible_person_id' => $attackerAssigneeId,
                'responsible_person' => $attackerAssigneeId,
            ]);

        $response->assertOk()->assertJsonPath('status', 'success');

        $lead = Lead::query()
            ->where('email', 'website-lead-assignee-guard@example.com')
            ->latest('id')
            ->first();

        $this->assertNotNull($lead);
        // Client assignee ignored; DB requires NOT NULL so system placeholder (id 1) is used until assignment job runs.
        $this->assertSame(1, (int) $lead->responsible_person_id);
        $this->assertNotSame($attackerAssigneeId, (int) $lead->responsible_person_id);
        $this->assertSame('Oiaproperties.com', $lead->lead_source);

        Bus::assertDispatched(ProcessLeadAutoAssignmentJob::class, function ($job) use ($lead) {
            return (int) $job->leadId === (int) $lead->id;
        });

        $lead->delete();
    }

    public function test_wordpress_lead_ignores_client_assignee_and_queues_assignment(): void
    {
        $attackerAssigneeId = 2911;

        $response = $this->withHeader('X-Website-Lead-Secret', self::SECRET)
            ->postJson('/api/website-lead/wordpress', [
                'Page_Name' => 'WP Assignee Guard',
                'No_Label_name' => 'Wp User',
                'No_Label_email' => 'wordpress-lead-assignee-guard@example.com',
                'No_Label_phone' => '0500000002',
                'responsible_person' => $attackerAssigneeId,
                'responsible_person_id' => 59,
            ]);

        $response->assertOk()->assertJsonPath('status', 'success');

        $lead = Lead::query()
            ->where('email', 'wordpress-lead-assignee-guard@example.com')
            ->latest('id')
            ->first();

        $this->assertNotNull($lead);
        $this->assertSame(1, (int) $lead->responsible_person_id);
        $this->assertNotSame($attackerAssigneeId, (int) $lead->responsible_person_id);
        $this->assertNotSame(59, (int) $lead->responsible_person_id);
        $this->assertSame('Allproperties.ae', $lead->lead_source);

        Bus::assertDispatched(ProcessLeadAutoAssignmentJob::class, function ($job) use ($lead) {
            return (int) $job->leadId === (int) $lead->id;
        });

        $lead->delete();
    }
}
