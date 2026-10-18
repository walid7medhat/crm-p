<?php

namespace Tests\Feature\LeadScan;

use App\Http\Middleware\JwtAuthMiddleware;
use App\Jobs\RunLeadIntelligenceScan;
use App\Models\Lead;
use App\Models\LeadIntelligenceScan;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadIntelligenceScanTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite') {
            $this->markTestSkipped('Lead intelligence scan tests run only on sqlite.');
        }

        $this->withoutMiddleware([JwtAuthMiddleware::class, ThrottleRequests::class]);
        $this->createSchema();
        Config::set('services.openai.api_key', '');
        Config::set('lead_scan.provider', 'brave');
        Config::set('lead_scan.api_key', 'test-key');
        Config::set('lead_scan.endpoint', 'https://api.search.brave.com/res/v1/web/search');
        Config::set('lead_scan.fresh_hours', 24);
    }

    public function test_routes_require_jwt_and_missing_lead_is_rejected(): void
    {
        $route = collect(app('router')->getRoutes())->first(
            fn ($candidate) => $candidate->uri() === 'api/leads/{lead}/intelligence-scans'
                && in_array('POST', $candidate->methods(), true)
        );

        $this->assertNotNull($route);
        $this->assertTrue(collect($route->gatherMiddleware())->contains(
            fn ($middleware) => $middleware === 'jwt.auth' || str_contains((string) $middleware, 'JwtAuth')
        ));

        $this->actingAs($this->superAdmin(), 'api')
            ->postJson('/api/leads/999999/intelligence-scans')
            ->assertNotFound();
    }

    public function test_guest_and_unauthorized_user_cannot_scan_or_read_history(): void
    {
        $owner = $this->superAdmin();
        $other = $this->user(5);
        $lead = $this->lead(['responsible_person_id' => 30, 'email' => 'sara@example.com']);

        $this->postJson("/api/leads/{$lead->id}/intelligence-scans")->assertStatus(401);

        $this->actingAs($other, 'api')
            ->postJson("/api/leads/{$lead->id}/intelligence-scans")
            ->assertStatus(403);

        $this->actingAs($owner, 'api');
        Http::fake([
            'api.search.brave.com/*' => Http::response(['web' => ['results' => []]]),
        ]);
        $this->postJson("/api/leads/{$lead->id}/intelligence-scans")->assertStatus(202);
        $scanId = LeadIntelligenceScan::query()->value('id');

        $this->actingAs($other, 'api')
            ->getJson("/api/leads/{$lead->id}/intelligence-scans/{$scanId}")
            ->assertStatus(403);
    }

    public function test_missing_provider_fails_clearly_and_does_not_change_the_lead(): void
    {
        Config::set('lead_scan.provider', '');
        Config::set('lead_scan.api_key', '');
        Http::preventStrayRequests();
        $lead = $this->lead(['lead_name' => 'Sara Ahmed', 'email' => 'sara@example.com', 'company_name' => 'Acme Realty']);
        $before = (array) DB::table('leads')->where('id', $lead->id)->first();

        $this->actingAs($this->superAdmin(), 'api')
            ->postJson("/api/leads/{$lead->id}/intelligence-scans")
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.provider', 'unconfigured');

        $this->assertSame($before, (array) DB::table('leads')->where('id', $lead->id)->first());
        $this->assertStringContainsString('not configured', (string) LeadIntelligenceScan::query()->value('status_message'));
        $stored = json_encode(LeadIntelligenceScan::query()->value('queries_meta'));
        $this->assertStringNotContainsString('sara@example.com', (string) $stored);
    }

    public function test_successful_scan_stores_sources_reuses_the_result_and_leaves_the_lead_alone(): void
    {
        Http::fake([
            'api.search.brave.com/*' => Http::response([
                'web' => [
                    'results' => [[
                        'title' => 'Sara Ahmed',
                        'url' => 'https://example.com/sara',
                        'description' => 'Public page for sara@example.com at Acme Realty',
                    ]],
                ],
            ]),
        ]);
        $lead = $this->lead([
            'lead_name' => 'Sara Ahmed',
            'email' => 'sara@example.com',
            'company_name' => 'Acme Realty',
        ]);
        $before = (array) DB::table('leads')->where('id', $lead->id)->first();

        $this->actingAs($this->superAdmin(), 'api')
            ->postJson("/api/leads/{$lead->id}/intelligence-scans")
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.candidates.0.tier', 'strong');

        $sent = Http::recorded()->count();
        $this->postJson("/api/leads/{$lead->id}/intelligence-scans")
            ->assertOk()
            ->assertJsonPath('data.reused', true);
        $this->assertSame($sent, Http::recorded()->count());
        $this->assertSame(1, LeadIntelligenceScan::query()->count());
        $this->assertSame($before, (array) DB::table('leads')->where('id', $lead->id)->first());
        $this->assertDatabaseHas('lead_intelligence_sources', ['url' => 'https://example.com/sara']);
    }

    public function test_similar_names_are_reported_as_not_confirmed(): void
    {
        Http::fake([
            'api.search.brave.com/*' => Http::response([
                'web' => [
                    'results' => [
                        [
                            'title' => 'Sara Ahmed, engineer',
                            'url' => 'https://one.example/sara',
                            'description' => 'Sara Ahmed engineer in Dubai',
                        ],
                        [
                            'title' => 'Sara Ahmed, designer',
                            'url' => 'https://two.example/sara',
                            'description' => 'Sara Ahmed designer in Abu Dhabi',
                        ],
                    ],
                ],
            ]),
        ]);
        $lead = $this->lead(['lead_name' => 'Sara Ahmed']);

        $this->actingAs($this->superAdmin(), 'api')
            ->postJson("/api/leads/{$lead->id}/intelligence-scans")
            ->assertStatus(202)
            ->assertJsonPath('data.identity_status', 'not_confirmed')
            ->assertJsonPath('data.identity_message', 'Identity not confirmed — multiple people may match these details.');
    }

    public function test_timeout_does_not_invent_a_profile(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('timed out');
        });
        $lead = $this->lead(['lead_name' => 'Sara Ahmed', 'email' => 'sara@example.com']);

        $this->actingAs($this->superAdmin(), 'api')
            ->postJson("/api/leads/{$lead->id}/intelligence-scans")
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'failed');

        $this->assertSame(0, DB::table('lead_intelligence_candidates')->count());
    }

    public function test_malformed_results_finish_as_not_found(): void
    {
        Http::fake([
            'api.search.brave.com/*' => Http::response(['web' => ['results' => 'nope']], 200),
        ]);
        $lead = $this->lead(['lead_name' => 'Sara Ahmed', 'email' => 'sara@example.com']);

        $this->actingAs($this->superAdmin(), 'api')
            ->postJson("/api/leads/{$lead->id}/intelligence-scans")
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.identity_status', 'not_found');
    }

    public function test_rate_limit_is_reported(): void
    {
        Http::fake([
            'api.search.brave.com/*' => Http::response(['message' => 'slow down'], 429),
        ]);
        $lead = $this->lead(['lead_name' => 'Sara Ahmed', 'email' => 'sara@example.com']);

        $this->actingAs($this->superAdmin(), 'api')
            ->postJson("/api/leads/{$lead->id}/intelligence-scans")
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'failed');

        $this->assertStringContainsString('rate limit', strtolower((string) LeadIntelligenceScan::query()->value('status_message').json_encode(LeadIntelligenceScan::query()->value('report'))));
    }

    public function test_job_failure_records_a_safe_message_and_does_not_change_the_lead(): void
    {
        $lead = $this->lead(['lead_name' => 'Sara Ahmed', 'company_name' => 'Acme Realty']);
        $before = (array) DB::table('leads')->where('id', $lead->id)->first();
        $scan = LeadIntelligenceScan::query()->create([
            'lead_id' => $lead->id,
            'user_id' => $this->user(30)->id,
            'status' => 'processing',
            'progress' => 20,
        ]);

        (new RunLeadIntelligenceScan($scan->id))->failed(new \RuntimeException('provider token sk-test leaked'));

        $scan->refresh();
        $this->assertSame('failed', $scan->status);
        $this->assertStringNotContainsString('sk-test', (string) $scan->status_message);
        $this->assertSame($before, (array) DB::table('leads')->where('id', $lead->id)->first());
    }

    public function test_a_lead_viewer_who_is_not_super_admin_cannot_scan(): void
    {
        $lead = $this->lead(['responsible_person_id' => 30, 'email' => 'sara@example.com']);

        $this->actingAs($this->user(30), 'api')
            ->postJson("/api/leads/{$lead->id}/intelligence-scans")
            ->assertStatus(403);
    }

    private function superAdmin(): User
    {
        $user = $this->user(30);
        Role::findOrCreate('super_admin', 'api');
        $user->assignRole('super_admin');

        return $user;
    }

    private function user(int $id): User
    {
        return User::withoutEvents(function () use ($id) {
            return User::query()->create([
                'id' => $id,
                'name' => 'User '.$id,
                'email' => "user{$id}@example.com",
                'password' => 'secret',
            ]);
        });
    }

    private function lead(array $overrides = []): Lead
    {
        return Lead::withoutEvents(function () use ($overrides) {
            return Lead::query()->create(array_merge([
                'lead_name' => 'Sara Ahmed',
                'email' => null,
                'company_name' => null,
                'responsible_person_id' => 30,
                'stage_id' => 1,
                'added_by' => 30,
            ], $overrides));
        });
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();
        });
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('lead_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->string('work_phone')->nullable();
            $table->string('company_name')->nullable();
            $table->string('position')->nullable();
            $table->string('nationality')->nullable();
            $table->string('branch')->nullable();
            $table->string('website')->nullable();
            $table->unsignedBigInteger('responsible_person_id')->nullable();
            $table->unsignedBigInteger('stage_id')->nullable();
            $table->unsignedBigInteger('added_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $migration = require base_path('database/migrations/2025_10_29_114431_create_permission_tables.php');
        $migration->up();
        $scans = require base_path('database/migrations/2026_10_18_191500_create_lead_intelligence_scan_tables.php');
        $scans->up();
    }
}
