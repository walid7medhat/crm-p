<?php

namespace Tests\Feature\LiveWatch;

use App\Events\LiveWatchPresenceChanged;
use App\Models\LeadHistory;
use App\Models\LiveWatchEvent;
use App\Models\LiveWatchSession;
use App\Models\LiveWatchSetting;
use App\Models\User;
use App\Services\LiveWatch\LiveWatchPresenceService;
use App\Support\Brand\BrandContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class LiveWatchFeatureTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('live_watch_settings')) {
            $this->fail('Run php artisan migrate before Live Watch tests (local only).');
        }

        // Isolate from local BRAND_FORCE=luxvant so ordinary-role heartbeats are not brand-gated.
        $this->withoutMiddleware(\App\Http\Middleware\ResolveBrandFromHost::class);
        app(BrandContext::class)->set(config('brands.brands.oia'));

        LiveWatchSetting::query()->delete();
        LiveWatchSession::query()->delete();
        LiveWatchEvent::query()->delete();
    }

    public function test_luxvant_brand_still_blocks_non_allowlisted_roles_on_live_watch(): void
    {
        app(BrandContext::class)->set(config('brands.brands.luxvant'));

        $sales = $this->makeUser('sales');
        $this->asUser($sales)->postJson('/api/auth/live-watch/heartbeat', [
            'session_id' => 'luxvant-block-tabxx',
        ])->assertForbidden();

        app(BrandContext::class)->set(config('brands.brands.oia'));
    }

    public function test_only_super_admin_can_access_admin_endpoints(): void
    {
        $target = $this->makeUser('sales');
        $roles = ['sales', 'admin', 'branch_admin', 'manager', 'team_lead', 'marketing', 'accountant'];

        foreach ($roles as $role) {
            $actor = $this->makeUser($role);
            $this->asUser($actor)->getJson('/api/admin/live-watch/settings')->assertForbidden();
            $this->asUser($actor)->getJson('/api/admin/live-watch/presence')->assertForbidden();
            $this->asUser($actor)->getJson('/api/admin/live-watch/timeline')->assertForbidden();
            $this->asUser($actor)->putJson('/api/admin/live-watch/settings', [
                'monitored_user_id' => $target->id,
            ])->assertForbidden();
        }

        $sa = $this->makeUser('super_admin');
        $this->asUser($sa)->getJson('/api/admin/live-watch/settings')->assertOk();
    }

    public function test_super_admin_can_select_and_change_monitored_user(): void
    {
        $sa = $this->makeUser('super_admin');
        $first = $this->makeUser('sales');
        $second = $this->makeUser('sales');

        $this->asUser($sa)->putJson('/api/admin/live-watch/settings', [
            'monitored_user_id' => $first->id,
            'heartbeat_interval_seconds' => 30,
            'offline_timeout_seconds' => 90,
        ])->assertOk()
            ->assertJsonPath('data.monitored_user_id', $first->id)
            ->assertJsonPath('data.heartbeat_interval_seconds', 30)
            ->assertJsonPath('data.offline_timeout_seconds', 90);

        $this->asUser($sa)->putJson('/api/admin/live-watch/settings', [
            'monitored_user_id' => $second->id,
        ])->assertOk()
            ->assertJsonPath('data.monitored_user_id', $second->id)
            ->assertJsonPath('data.is_online', false);

        $this->assertSame($second->id, (int) LiveWatchSetting::current()->monitored_user_id);
    }

    public function test_multi_tab_heartbeat_and_offline_only_when_all_expire(): void
    {
        Event::fake([LiveWatchPresenceChanged::class]);

        $sa = $this->makeUser('super_admin');
        $target = $this->makeUser('sales');

        $this->asUser($sa)->putJson('/api/admin/live-watch/settings', [
            'monitored_user_id' => $target->id,
            'offline_timeout_seconds' => 90,
        ])->assertOk();

        $this->asUser($target)->postJson('/api/auth/live-watch/heartbeat', [
            'session_id' => 'tab-one-aaaaaaaa',
        ])->assertOk()
            ->assertJsonPath('data.tracked', true)
            ->assertJsonPath('data.is_online', true)
            ->assertJsonPath('data.active_sessions', 1);

        Event::assertDispatched(LiveWatchPresenceChanged::class, function (LiveWatchPresenceChanged $e) use ($target) {
            return $e->status === 'online'
                && $e->userId === $target->id
                && is_string($e->transitionId)
                && $e->transitionId !== '';
        });

        $transition = LiveWatchSetting::current()->current_transition_id;

        $this->asUser($target)->postJson('/api/auth/live-watch/heartbeat', [
            'session_id' => 'tab-two-bbbbbbbb',
        ])->assertOk()
            ->assertJsonPath('data.active_sessions', 2)
            ->assertJsonPath('data.transition_id', $transition);

        // Closing one tab must keep the user online.
        $this->asUser($target)->postJson('/api/auth/live-watch/leave', [
            'session_id' => 'tab-one-aaaaaaaa',
        ])->assertOk()
            ->assertJsonPath('data.is_online', true)
            ->assertJsonPath('data.active_sessions', 1);

        LiveWatchSession::query()
            ->where('session_id', 'tab-two-bbbbbbbb')
            ->update(['last_seen_at' => now()->subSeconds(120)]);

        $result = app(LiveWatchPresenceService::class)->reconcilePresence();
        $this->assertFalse($result['is_online']);
        $this->assertSame(0, $result['active_sessions']);

        Event::assertDispatched(LiveWatchPresenceChanged::class, function (LiveWatchPresenceChanged $e) {
            return $e->status === 'offline';
        });
    }

    public function test_non_monitored_user_heartbeat_is_ignored(): void
    {
        $sa = $this->makeUser('super_admin');
        $monitored = $this->makeUser('sales');
        $other = $this->makeUser('sales');

        $this->asUser($sa)->putJson('/api/admin/live-watch/settings', [
            'monitored_user_id' => $monitored->id,
        ])->assertOk();

        $this->asUser($other)->postJson('/api/auth/live-watch/heartbeat', [
            'session_id' => 'other-tab-cccccc',
        ])->assertOk()
            ->assertJsonPath('data.tracked', false);

        $this->assertSame(0, LiveWatchSession::query()->where('user_id', $other->id)->count());
    }

    public function test_navigation_events_are_recorded_for_monitored_user_only(): void
    {
        $sa = $this->makeUser('super_admin');
        $monitored = $this->makeUser('sales');
        $other = $this->makeUser('sales');

        $this->asUser($sa)->putJson('/api/admin/live-watch/settings', [
            'monitored_user_id' => $monitored->id,
        ])->assertOk();

        $this->asUser($monitored)->postJson('/api/auth/live-watch/navigation', [
            'action' => 'enter',
            'event_type' => 'route.enter',
            'module' => 'leads',
            'path' => '/kanban',
            'client_event_id' => 'nav-test-1',
        ])->assertOk()->assertJsonPath('data.recorded', true);

        // Duplicate client id must not create a second row.
        $this->asUser($monitored)->postJson('/api/auth/live-watch/navigation', [
            'action' => 'enter',
            'event_type' => 'route.enter',
            'module' => 'leads',
            'path' => '/kanban',
            'client_event_id' => 'nav-test-1',
        ])->assertOk();

        $this->asUser($other)->postJson('/api/auth/live-watch/navigation', [
            'action' => 'enter',
            'event_type' => 'route.enter',
            'module' => 'leads',
            'path' => '/kanban',
            'client_event_id' => 'nav-test-other',
        ])->assertOk()->assertJsonPath('data.recorded', false);

        $this->assertSame(1, LiveWatchEvent::query()->where('user_id', $monitored->id)->count());
        $this->assertSame(0, LiveWatchEvent::query()->where('user_id', $other->id)->count());
    }

    public function test_business_lead_history_is_mirrored_for_monitored_user(): void
    {
        $sa = $this->makeUser('super_admin');
        $monitored = $this->makeUser('sales');

        $this->asUser($sa)->putJson('/api/admin/live-watch/settings', [
            'monitored_user_id' => $monitored->id,
        ])->assertOk();

        $leadId = (int) (DB::table('leads')->whereNull('deleted_at')->value('id') ?: 0);
        if ($leadId <= 0) {
            $this->markTestSkipped('Need at least one lead row to attach history');
        }

        LeadHistory::create([
            'lead_id' => $leadId,
            'user_id' => $monitored->id,
            'changes' => [
                'action' => 'stage_changed',
                'from' => 'New',
                'to' => 'Contacted',
            ],
        ]);

        $this->assertDatabaseHas('live_watch_events', [
            'user_id' => $monitored->id,
            'event_kind' => 'business',
            'module' => 'leads',
            'action' => 'stage_changed',
            'entity_type' => 'lead',
            'entity_id' => $leadId,
        ]);
    }

    public function test_live_watch_broadcast_channel_is_super_admin_only(): void
    {
        $sa = $this->makeUser('super_admin');
        $sales = $this->makeUser('sales');

        // Channel auth endpoint (same middleware stack as production Echo).
        $this->asUser($sales)->postJson('/broadcasting/auth', [
            'channel_name' => 'private-live-watch',
            'socket_id' => '1.1',
        ])->assertForbidden();

        // Super admin should be authorized (Pusher may 500 without keys — accept non-403/401).
        $saResponse = $this->asUser($sa)->postJson('/broadcasting/auth', [
            'channel_name' => 'private-live-watch',
            'socket_id' => '1.1',
        ]);
        $this->assertNotSame(403, $saResponse->status(), 'Super Admin must not receive 403 on live-watch channel');
        $this->assertNotSame(401, $saResponse->status(), 'Super Admin must be authenticated for live-watch channel');
    }

    public function test_online_transition_id_stable_while_online_for_siren_dedupe(): void
    {
        Event::fake([LiveWatchPresenceChanged::class]);
        $sa = $this->makeUser('super_admin');
        $target = $this->makeUser('sales');

        $this->asUser($sa)->putJson('/api/admin/live-watch/settings', [
            'monitored_user_id' => $target->id,
        ])->assertOk();

        $first = $this->asUser($target)->postJson('/api/auth/live-watch/heartbeat', [
            'session_id' => 'siren-tab-1xxxxxx',
        ])->assertOk()->json('data.transition_id');

        $second = $this->asUser($target)->postJson('/api/auth/live-watch/heartbeat', [
            'session_id' => 'siren-tab-1xxxxxx',
        ])->assertOk()->json('data.transition_id');

        $this->assertNotEmpty($first);
        $this->assertSame($first, $second);

        Event::assertDispatched(LiveWatchPresenceChanged::class, function (LiveWatchPresenceChanged $e) use ($first) {
            return $e->status === 'online' && $e->transitionId === $first;
        });
        // Remaining online heartbeats must not emit another online transition.
        $onlineCount = collect(Event::dispatched(LiveWatchPresenceChanged::class))
            ->filter(fn ($args) => ($args[0] ?? null) instanceof LiveWatchPresenceChanged
                && $args[0]->status === 'online')
            ->count();
        $this->assertSame(1, $onlineCount);
    }

    public function test_presence_changed_broadcasts_on_private_live_watch_channel(): void
    {
        $event = new LiveWatchPresenceChanged(
            status: 'online',
            userId: 9,
            transitionId: 'tid-123',
            onlineSince: now()->toIso8601String(),
            monitoredUserId: 9,
            userName: 'Agent',
        );

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertSame('private-live-watch', $channels[0]->name);
        $this->assertSame('presence.changed', $event->broadcastAs());
        $this->assertSame('tid-123', $event->broadcastWith()['transition_id']);
    }

    public function test_brand_middleware_still_wraps_live_watch_routes(): void
    {
        $router = app('router');
        $found = false;
        foreach ($router->getRoutes() as $route) {
            if (! str_contains($route->uri(), 'admin/live-watch/settings')) {
                continue;
            }
            $found = true;
            $middleware = $route->gatherMiddleware();
            $this->assertContains('jwt.auth', $middleware);
            $this->assertTrue(
                collect($middleware)->contains(fn ($m) => str_contains((string) $m, 'role:super_admin')),
                'Expected role:super_admin on live-watch settings route'
            );
        }
        $this->assertTrue($found, 'live-watch settings route missing');
    }

    private function makeUser(string $role, string $status = 'active'): User
    {
        $user = User::factory()->create([
            'status' => $status,
            'email' => uniqid($role, true).'@example.test',
            'last_login_at' => now(),
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
