<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\UserLocationHistory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class LocationTrackingHistoryTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('user_location_history')) {
            $this->fail('Run php artisan migrate before location history tests.');
        }

        config([
            'location_tracking.write_cooldown_seconds' => 0,
            'location_tracking.min_move_meters' => 75,
            'location_tracking.fresh_minutes' => 10,
        ]);
    }

    public function test_device_fix_updates_latest_and_appends_history(): void
    {
        $this->fakeGeocoder();
        $user = $this->makeUser('sales');

        $this->asUser($user)->postJson('/api/auth/location', [
            'latitude' => 25.2048493,
            'longitude' => 55.2707825,
            'accuracy' => 18,
            'captured_at' => now()->toIso8601String(),
        ])->assertOk()
            ->assertJsonPath('data.location_updated', true)
            ->assertJsonPath('data.history_recorded', true);

        $user->refresh();
        $this->assertEqualsWithDelta(25.2048493, (float) $user->last_login_lat, 0.0000001);
        $this->assertSame(1, UserLocationHistory::where('user_id', $user->id)->count());
    }

    public function test_small_movement_does_not_duplicate_history(): void
    {
        $this->fakeGeocoder();
        $user = $this->makeUser('sales');

        $this->asUser($user)->postJson('/api/auth/location', [
            'latitude' => 25.2048493,
            'longitude' => 55.2707825,
            'accuracy' => 15,
            'captured_at' => now()->subMinutes(2)->toIso8601String(),
        ])->assertOk()->assertJsonPath('data.history_recorded', true);

        // ~11 metres — below the 75 m history threshold
        $this->asUser($user)->postJson('/api/auth/location', [
            'latitude' => 25.2049493,
            'longitude' => 55.2707825,
            'accuracy' => 12,
            'captured_at' => now()->toIso8601String(),
        ])->assertOk()
            ->assertJsonPath('data.location_updated', true)
            ->assertJsonPath('data.history_recorded', false);

        $this->assertSame(1, UserLocationHistory::where('user_id', $user->id)->count());
        $user->refresh();
        $this->assertEqualsWithDelta(25.2049493, (float) $user->last_login_lat, 0.0000001);
    }

    public function test_meaningful_move_appends_another_history_row(): void
    {
        $this->fakeGeocoder();
        $user = $this->makeUser('sales');

        $this->asUser($user)->postJson('/api/auth/location', [
            'latitude' => 25.2048493,
            'longitude' => 55.2707825,
            'accuracy' => 15,
            'captured_at' => now()->subMinutes(5)->toIso8601String(),
        ])->assertOk()->assertJsonPath('data.history_recorded', true);

        // ~1.1 km away
        $this->asUser($user)->postJson('/api/auth/location', [
            'latitude' => 25.2148493,
            'longitude' => 55.2707825,
            'accuracy' => 20,
            'captured_at' => now()->toIso8601String(),
        ])->assertOk()->assertJsonPath('data.history_recorded', true);

        $this->assertSame(2, UserLocationHistory::where('user_id', $user->id)->count());
    }

    public function test_becoming_online_again_appends_history_without_login_or_move(): void
    {
        $this->fakeGeocoder();
        config(['location_tracking.online_window_minutes' => 15]);
        $user = $this->makeUser('sales');

        // Prior online session ended (history older than the 15-minute online window).
        UserLocationHistory::create([
            'user_id' => $user->id,
            'latitude' => 25.2048493,
            'longitude' => 55.2707825,
            'accuracy' => 18,
            'captured_at' => now()->subMinutes(20),
            'recorded_at' => now()->subMinutes(20),
            'source' => 'device',
        ]);
        $user->forceFill([
            'last_login_lat' => 25.2048493,
            'last_login_lng' => 55.2707825,
            'last_login_accuracy' => 18,
            'last_login_location_at' => now()->subMinutes(20),
            'last_login_at' => now()->subMinutes(20),
        ])->save();

        // Same desk, already authenticated — no new login, no 75 m move.
        $this->asUser($user)->postJson('/api/auth/location', [
            'latitude' => 25.2048493,
            'longitude' => 55.2707825,
            'accuracy' => 16,
            'captured_at' => now()->toIso8601String(),
            'session_start' => true,
        ])->assertOk()
            ->assertJsonPath('data.location_updated', true)
            ->assertJsonPath('data.history_recorded', true);

        $this->assertSame(2, UserLocationHistory::where('user_id', $user->id)->count());
        $latest = UserLocationHistory::where('user_id', $user->id)->orderByDesc('id')->first();
        $this->assertNotNull($latest->session_started_at);
    }

    public function test_session_start_within_online_window_still_records_when_forced(): void
    {
        $this->fakeGeocoder();
        $user = $this->makeUser('sales');

        $this->asUser($user)->postJson('/api/auth/location', [
            'latitude' => 25.2048493,
            'longitude' => 55.2707825,
            'accuracy' => 15,
            'captured_at' => now()->subMinutes(2)->toIso8601String(),
        ])->assertOk()->assertJsonPath('data.history_recorded', true);

        // Forced session_start (e.g. client detected reconnect) at same coords.
        $this->asUser($user)->postJson('/api/auth/location', [
            'latitude' => 25.2048493,
            'longitude' => 55.2707825,
            'accuracy' => 14,
            'captured_at' => now()->toIso8601String(),
            'session_start' => true,
        ])->assertOk()->assertJsonPath('data.history_recorded', true);

        $this->assertSame(2, UserLocationHistory::where('user_id', $user->id)->count());
    }

    public function test_super_admin_can_read_online_and_history_others_cannot(): void
    {
        $this->fakeGeocoder();
        $super = $this->makeUser('super_admin');
        $admin = $this->makeUser('admin');
        $sales = $this->makeUser('sales');

        $sales->forceFill([
            'last_login_at' => now(),
            'last_login_lat' => 25.2048493,
            'last_login_lng' => 55.2707825,
            'last_login_accuracy' => 18,
            'last_login_location_at' => now(),
            'last_login_location' => 'Marina, Dubai',
        ])->save();

        UserLocationHistory::create([
            'user_id' => $sales->id,
            'latitude' => 25.2048493,
            'longitude' => 55.2707825,
            'accuracy' => 18,
            'captured_at' => now()->subMinutes(3),
            'recorded_at' => now()->subMinutes(3),
            'source' => 'device',
            'address' => 'Marina, Dubai',
        ]);

        $this->asUser($super)->getJson('/api/admin/location/online')
            ->assertOk()
            ->assertJsonPath('data.background_tracking', false);

        $this->asUser($super)->getJson('/api/admin/location/users/'.$sales->id)
            ->assertOk()
            ->assertJsonPath('data.history.0.latitude', 25.2048493);

        $this->asUser($admin)->getJson('/api/admin/location/online')->assertForbidden();
        $this->asUser($admin)->getJson('/api/admin/location/users/'.$sales->id)->assertForbidden();
        $this->asUser($sales)->getJson('/api/admin/location/online')->assertForbidden();
    }

    public function test_user_resource_hides_location_from_non_super_admins(): void
    {
        $admin = $this->makeUser('admin');
        $super = $this->makeUser('super_admin');
        $sales = $this->makeUser('sales');
        $sales->forceFill([
            'last_login_lat' => 25.2048493,
            'last_login_lng' => 55.2707825,
            'last_login_accuracy' => 18,
            'last_login_location_at' => now(),
            'last_login_location' => 'Marina, Dubai',
            'last_login_ip' => '1.2.3.4',
        ])->save();

        $this->asUser($admin)->getJson('/api/users/'.$sales->id)
            ->assertOk()
            ->assertJsonPath('data.last_login_lat', null)
            ->assertJsonPath('data.last_login_lng', null)
            ->assertJsonPath('data.last_login_location', null)
            ->assertJsonPath('data.last_login_ip', null);

        $this->asUser($super)->getJson('/api/users/'.$sales->id)
            ->assertOk()
            ->assertJsonPath('data.last_login_location', 'Marina, Dubai');
        $this->assertEqualsWithDelta(
            25.2048493,
            (float) $this->asUser($super)->getJson('/api/users/'.$sales->id)->json('data.last_login_lat'),
            0.0000001
        );
    }

    private function fakeGeocoder(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                'lat' => '25.2048493',
                'lon' => '55.2707825',
                'display_name' => 'Marina, Dubai',
                'address' => ['road' => 'Marina', 'city' => 'Dubai'],
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
