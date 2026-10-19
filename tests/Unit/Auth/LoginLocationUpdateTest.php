<?php

namespace Tests\Unit\Auth;

use App\Models\User;
use App\Services\Auth\LoginLocationUpdate;
use Carbon\Carbon;
use Tests\TestCase;

class LoginLocationUpdateTest extends TestCase
{
    private LoginLocationUpdate $recorder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recorder = new LoginLocationUpdate;
        Carbon::setTestNow('2026-10-18 15:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_missing_or_invalid_coordinates_are_not_a_fix(): void
    {
        $user = new User;

        $this->assertFalse($this->recorder->decision($user, null, null)['apply']);
        $this->assertFalse($this->recorder->decision($user, 25.2, null)['apply']);
        $this->assertFalse($this->recorder->decision($user, 91, 55)['apply']);
        $this->assertFalse($this->recorder->decision($user, 25, 181)['apply']);
        $this->assertFalse($this->recorder->decision($user, 'nope', 55)['apply']);
        $this->assertFalse($this->recorder->decision($user, INF, 55)['apply']);
    }

    public function test_a_device_fix_keeps_full_precision_accuracy_and_capture_time(): void
    {
        $decision = $this->recorder->decision(
            new User,
            '25.2048493',
            '55.2707825',
            18.4,
            '2026-10-18 14:59:00',
        );

        $this->assertTrue($decision['apply']);
        $this->assertEqualsWithDelta(25.2048493, $decision['latitude'], 0.0000001);
        $this->assertEqualsWithDelta(55.2707825, $decision['longitude'], 0.0000001);
        $this->assertSame(18, $decision['accuracy']);
        $this->assertTrue($decision['captured_at']->equalTo(Carbon::parse('2026-10-18 14:59:00')));
        $this->assertTrue(LoginLocationUpdate::isPrecise(18));
        $this->assertFalse(LoginLocationUpdate::isPrecise(1500));
        $this->assertFalse(LoginLocationUpdate::isPrecise(null));
    }

    public function test_an_older_capture_cannot_replace_a_newer_one(): void
    {
        $user = $this->storedFix('2026-10-18 14:50:00', 20);

        $decision = $this->recorder->decision($user, 25.1, 55.1, 5, '2026-10-18 14:40:00');

        $this->assertFalse($decision['apply']);
    }

    public function test_a_coarse_reading_cannot_replace_a_recent_precise_fix(): void
    {
        $user = $this->storedFix('2026-10-18 14:55:00', 20);

        $decision = $this->recorder->decision($user, 24.4539, 54.3773, 5000, '2026-10-18 15:00:00');

        $this->assertFalse($decision['apply']);
    }

    public function test_a_later_coarse_reading_can_replace_an_old_precise_fix(): void
    {
        $user = $this->storedFix('2026-10-18 14:30:00', 20);

        $decision = $this->recorder->decision($user, 25.3, 55.4, 1800, '2026-10-18 15:00:00');

        $this->assertTrue($decision['apply']);
        $this->assertSame(1800, $decision['accuracy']);
        $this->assertFalse(LoginLocationUpdate::isPrecise($decision['accuracy']));
    }

    public function test_ip_labelled_coordinates_are_not_treated_as_a_device_fix(): void
    {
        $ip = new User;
        $ip->forceFill([
            'last_login_lat' => 25.2,
            'last_login_lng' => 55.2,
            'last_login_location' => '(approx. by IP) Dubai',
        ]);

        $device = new User;
        $device->forceFill([
            'last_login_lat' => 25.2048493,
            'last_login_lng' => 55.2707825,
            'last_login_location' => 'Marina, Dubai',
        ]);

        $this->assertSame('ip', LoginLocationUpdate::source($ip));
        $this->assertSame('device', LoginLocationUpdate::source($device));
        $this->assertNull(LoginLocationUpdate::source(new User));
    }

    private function storedFix(string $capturedAt, int $accuracy): User
    {
        $user = new User;
        $user->forceFill([
            'last_login_lat' => 25.2048493,
            'last_login_lng' => 55.2707825,
            'last_login_accuracy' => $accuracy,
            'last_login_location_at' => $capturedAt,
        ]);

        return $user;
    }
}
