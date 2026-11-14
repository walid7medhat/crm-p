<?php

namespace Tests\Feature\Security;

use App\Events\ForeignAccessDetected;
use App\Jobs\EvaluateForeignAccessJob;
use App\Models\ForeignAccessEvent;
use App\Models\User;
use App\Services\Security\ForeignAccessMonitorService;
use App\Support\Brand\BrandContext;
use App\Support\Security\CloudflareTrustedProxies;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ForeignAccessMonitorTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('foreign_access_events')) {
            $this->fail('Run php artisan migrate before Foreign Access Monitor tests (local only).');
        }

        $this->withoutMiddleware(\App\Http\Middleware\ResolveBrandFromHost::class);
        app(BrandContext::class)->set(config('brands.brands.oia'));
        config(['foreign_access.enabled' => true]);
        Cache::flush();
        ForeignAccessEvent::query()->delete();
        CloudflareTrustedProxies::fake(null);
    }

    protected function tearDown(): void
    {
        CloudflareTrustedProxies::fake(null);
        parent::tearDown();
    }

    public function test_uae_login_does_not_generate_foreign_alert(): void
    {
        Event::fake([ForeignAccessDetected::class]);
        $this->fakeGeoIp('AE', 'United Arab Emirates', 'Dubai');

        $user = $this->makeUser('sales');
        $event = app(ForeignAccessMonitorService::class)->evaluate(
            $user->id,
            '185.3.12.10',
            'oia',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X) Chrome/120.0.0.0',
            'session-uae-1',
            'login',
        );

        $this->assertNull($event);
        $this->assertSame(0, ForeignAccessEvent::query()->count());
        Event::assertNotDispatched(ForeignAccessDetected::class);
    }

    public function test_foreign_login_generates_alert(): void
    {
        Event::fake([ForeignAccessDetected::class]);
        $this->fakeGeoIp('EG', 'Egypt', 'Cairo');

        $user = $this->makeUser('sales');
        $event = app(ForeignAccessMonitorService::class)->evaluate(
            $user->id,
            '41.33.10.20',
            'oia',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
            'session-eg-1',
            'login',
        );

        $this->assertNotNull($event);
        $this->assertSame('EG', $event->country_code);
        $this->assertSame('Cairo', $event->city);
        $this->assertSame('41.33.10.20', $event->ip);
        $this->assertSame(ForeignAccessMonitorService::REASON_FOREIGN_LOGIN, $event->detection_reason);
        $this->assertSame('new', $event->status);
        $this->assertSame('Chrome', $event->browser);
        $this->assertSame('Windows', $event->operating_system);
        Event::assertDispatched(ForeignAccessDetected::class);
    }

    public function test_country_change_during_session_is_detected(): void
    {
        Event::fake([ForeignAccessDetected::class]);
        $user = $this->makeUser('sales');

        Http::fake([
            'ip-api.com/*' => Http::sequence()
                ->push([
                    'status' => 'success',
                    'country' => 'United Arab Emirates',
                    'countryCode' => 'AE',
                    'city' => 'Dubai',
                    'isp' => 'Synthetic ISP',
                ])
                ->push([
                    'status' => 'success',
                    'country' => 'Egypt',
                    'countryCode' => 'EG',
                    'city' => 'Cairo',
                    'isp' => 'Synthetic ISP',
                ]),
        ]);

        $this->assertNull(app(ForeignAccessMonitorService::class)->evaluate(
            $user->id,
            '185.3.12.10',
            'oia',
            'Mozilla/5.0',
            'session-travel-1',
            'session',
            null,
        ));

        // New IP outside UAE while previous fingerprint was AE → country_change.
        $event = app(ForeignAccessMonitorService::class)->evaluate(
            $user->id,
            '41.33.10.99',
            'oia',
            'Mozilla/5.0',
            'session-travel-1',
            'session',
            'AE',
        );

        $this->assertNotNull($event);
        $this->assertSame(ForeignAccessMonitorService::REASON_COUNTRY_CHANGE, $event->detection_reason);
        $this->assertSame('EG', $event->country_code);
    }

    public function test_ip_change_within_uae_does_not_alert(): void
    {
        Event::fake([ForeignAccessDetected::class]);
        $user = $this->makeUser('sales');

        $this->fakeGeoIp('AE', 'United Arab Emirates', 'Dubai');
        $this->assertNull(app(ForeignAccessMonitorService::class)->evaluate(
            $user->id,
            '185.3.12.10',
            'oia',
            null,
            'session-uae-ip',
            'session',
        ));

        $this->fakeGeoIp('AE', 'United Arab Emirates', 'Abu Dhabi');
        $this->assertNull(app(ForeignAccessMonitorService::class)->evaluate(
            $user->id,
            '185.3.12.55',
            'oia',
            null,
            'session-uae-ip',
            'session',
            'AE',
        ));

        $this->assertSame(0, ForeignAccessEvent::query()->count());
        Event::assertNotDispatched(ForeignAccessDetected::class);
    }

    public function test_repeated_requests_do_not_create_duplicate_alerts(): void
    {
        Event::fake([ForeignAccessDetected::class]);
        $this->fakeGeoIp('TR', 'Turkey', 'Istanbul');
        $user = $this->makeUser('sales');
        $monitor = app(ForeignAccessMonitorService::class);

        $first = $monitor->evaluate($user->id, '88.1.2.3', 'oia', 'Mozilla/5.0', 'session-dup', 'login');
        $second = $monitor->evaluate($user->id, '88.1.2.3', 'oia', 'Mozilla/5.0', 'session-dup', 'login');

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertSame(1, ForeignAccessEvent::query()->count());
        Event::assertDispatchedTimes(ForeignAccessDetected::class, 1);
    }

    public function test_request_ip_is_used_for_cloudflare_trusted_proxy_path(): void
    {
        Event::fake([ForeignAccessDetected::class]);
        Queue::fake();

        // Ensure TrustProxies uses the real Cloudflare snapshot (104.16.0.0/13).
        \Illuminate\Http\Middleware\TrustProxies::at(CloudflareTrustedProxies::cidrs());

        Http::fake([
            'ip-api.com/json/203.0.113.50*' => Http::response([
                'status' => 'success',
                'country' => 'Germany',
                'countryCode' => 'DE',
                'regionName' => 'Berlin',
                'city' => 'Berlin',
                'isp' => 'CF Client',
            ], 200),
            '*' => Http::response(['status' => 'fail'], 200),
        ]);

        $user = $this->makeUser('sales');

        // Cloudflare edge as REMOTE_ADDR; real client in X-Forwarded-For.
        $this->withServerVariables([
            'REMOTE_ADDR' => '104.16.10.20',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.50',
        ])->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        Queue::assertPushed(EvaluateForeignAccessJob::class, function (EvaluateForeignAccessJob $job) use ($user) {
            return $job->userId === $user->id && $job->ip === '203.0.113.50' && $job->trigger === 'login';
        });

        (new EvaluateForeignAccessJob(
            $user->id,
            '203.0.113.50',
            'oia',
            'Symfony',
            null,
            'login',
        ))->handle(app(ForeignAccessMonitorService::class));

        $row = ForeignAccessEvent::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('203.0.113.50', $row->ip);
        $this->assertSame('DE', $row->country_code);
    }

    public function test_login_dispatches_async_queue_job_not_after_response(): void
    {
        Queue::fake();
        $user = $this->makeUser('sales');

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        Queue::assertPushed(EvaluateForeignAccessJob::class, function (EvaluateForeignAccessJob $job) use ($user) {
            return $job->userId === $user->id && $job->trigger === 'login';
        });
        $this->assertSame(3, (new EvaluateForeignAccessJob($user->id, '1.1.1.1'))->tries);
        $this->assertSame([10, 60, 180], (new EvaluateForeignAccessJob($user->id, '1.1.1.1'))->backoff);
    }

    public function test_job_exceptions_propagate_for_retries(): void
    {
        $monitor = Mockery::mock(ForeignAccessMonitorService::class);
        $monitor->shouldReceive('evaluate')->once()->andThrow(new \RuntimeException('geo unavailable'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('geo unavailable');

        (new EvaluateForeignAccessJob(1, '41.33.10.20', 'oia'))->handle($monitor);
    }

    public function test_job_failed_logs_permanent_failure(): void
    {
        \Illuminate\Support\Facades\Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $message === 'EvaluateForeignAccessJob permanently failed'
                    && ($context['user_id'] ?? null) === 99
                    && ($context['ip'] ?? null) === '8.8.8.8';
            });

        (new EvaluateForeignAccessJob(99, '8.8.8.8', 'oia'))
            ->failed(new \RuntimeException('permanent'));
    }

    public function test_brand_isolation_hides_cross_brand_events(): void
    {
        Event::fake([ForeignAccessDetected::class]);
        $this->fakeGeoIp('EG', 'Egypt', 'Cairo');

        $user = $this->makeUser('sales');
        $oiaEvent = app(ForeignAccessMonitorService::class)->evaluate(
            $user->id,
            '41.33.10.20',
            'oia',
            'Mozilla/5.0',
            'session-brand-oia',
            'login',
        );
        $this->assertNotNull($oiaEvent);

        // Simulate a Luxvant-stamped event for the same user.
        $luxEvent = ForeignAccessEvent::query()->create([
            'user_id' => $user->id,
            'brand_key' => 'luxvant',
            'ip' => '41.33.10.21',
            'country' => 'Egypt',
            'country_code' => 'EG',
            'city' => 'Cairo',
            'detection_reason' => 'foreign_login',
            'status' => 'new',
            'session_key' => 'session-brand-lux',
            'occurred_at' => now(),
        ]);

        $sa = $this->makeUser('super_admin');
        app(BrandContext::class)->set(config('brands.brands.oia'));

        $list = $this->asUser($sa)->getJson('/api/admin/foreign-access')->assertOk();
        $ids = collect($list->json('data.events'))->pluck('id')->all();
        $this->assertContains($oiaEvent->id, $ids);
        $this->assertNotContains($luxEvent->id, $ids);

        $this->asUser($sa)->getJson('/api/admin/foreign-access/'.$luxEvent->id)->assertNotFound();
    }

    public function test_only_super_admin_can_access_monitor_apis(): void
    {
        $this->fakeGeoIp('EG', 'Egypt', 'Cairo');
        $target = $this->makeUser('sales');
        $event = app(ForeignAccessMonitorService::class)->evaluate(
            $target->id,
            '41.33.10.20',
            'oia',
            'Mozilla/5.0',
            'session-authz',
            'login',
        );
        $this->assertNotNull($event);

        $roles = ['sales', 'admin', 'branch_admin', 'manager', 'team_lead', 'marketing', 'accountant'];
        foreach ($roles as $role) {
            $actor = $this->makeUser($role);
            $this->asUser($actor)->getJson('/api/admin/foreign-access')->assertForbidden();
            $this->asUser($actor)->getJson('/api/admin/foreign-access/'.$event->id)->assertForbidden();
            $this->asUser($actor)->postJson('/api/admin/foreign-access/'.$event->id.'/review')->assertForbidden();
        }

        $sa = $this->makeUser('super_admin');
        $this->asUser($sa)->getJson('/api/admin/foreign-access')
            ->assertOk()
            ->assertJsonPath('data.events.0.id', $event->id)
            ->assertJsonPath('data.events.0.ip', '41.33.10.20');

        $this->asUser($sa)->postJson('/api/admin/foreign-access/'.$event->id.'/review')
            ->assertOk()
            ->assertJsonPath('data.status', 'reviewed');

        $event->refresh();
        $this->assertSame('reviewed', $event->status);
        $this->assertSame($sa->id, $event->reviewed_by);
    }

    public function test_foreign_access_broadcast_channel_is_super_admin_only(): void
    {
        $sa = $this->makeUser('super_admin');
        $sales = $this->makeUser('sales');

        $this->asUser($sales)->postJson('/broadcasting/auth', [
            'channel_name' => 'private-foreign-access',
            'socket_id' => '1.1',
        ])->assertForbidden();

        $saResponse = $this->asUser($sa)->postJson('/broadcasting/auth', [
            'channel_name' => 'private-foreign-access',
            'socket_id' => '1.1',
        ]);
        $this->assertNotSame(403, $saResponse->status(), 'Super Admin must not receive 403 on foreign-access channel');
        $this->assertNotSame(401, $saResponse->status(), 'Super Admin must be authenticated for foreign-access channel');
    }

    public function test_admin_routes_use_role_super_admin_middleware(): void
    {
        $found = collect(app('router')->getRoutes())->first(function ($r) {
            return str_contains($r->uri(), 'admin/foreign-access')
                && in_array('GET', $r->methods(), true)
                && ! str_contains($r->uri(), '{');
        });

        $this->assertNotNull($found);
        $middleware = $found->gatherMiddleware();
        $this->assertTrue(
            collect($middleware)->contains(fn ($m) => str_contains((string) $m, 'role:super_admin')),
            'Expected role:super_admin on foreign-access index route'
        );
    }

    public function test_login_still_succeeds_when_foreign_access_job_fails_to_queue(): void
    {
        $user = $this->makeUser('sales');

        // Even with GeoIP failing, login must remain unaffected.
        Http::fake([
            'ip-api.com/*' => Http::response(['status' => 'fail', 'message' => 'reserved range'], 200),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->assertJsonPath('message', 'Login successful');

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertNotNull($user->last_login_ip);
    }

    public function test_probe_skips_duplicate_ip_without_geo_lookup(): void
    {
        $user = $this->makeUser('sales');
        $monitor = app(ForeignAccessMonitorService::class);

        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'success',
                'country' => 'Egypt',
                'countryCode' => 'EG',
                'city' => 'Cairo',
            ], 200),
        ]);

        // Seed fingerprint as already on this IP.
        Cache::put('foreign_access:fp:'.$user->id, [
            'ip' => '41.33.10.20',
            'country_code' => 'EG',
        ], 3600);

        $scheduled = $monitor->probe($user->id, '41.33.10.20', 'Mozilla/5.0', 'sess-1', 'session');
        $this->assertFalse($scheduled);
        Http::assertNothingSent();
    }

    private function fakeGeoIp(string $code, string $country, string $city): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'success',
                'country' => $country,
                'countryCode' => $code,
                'regionName' => $city,
                'city' => $city,
                'isp' => 'Synthetic ISP',
                'org' => 'Synthetic Org',
                'timezone' => 'Asia/Dubai',
            ], 200),
        ]);
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create([
            'status' => 'active',
            'email' => uniqid($role, true).'@example.test',
        ]);
        Role::findOrCreate($role, 'api');
        $user->assignRole($role);

        return $user;
    }

    private function asUser(User $user): self
    {
        return $this->withHeader('Authorization', 'Bearer '.JWTAuth::fromUser($user));
    }
}
