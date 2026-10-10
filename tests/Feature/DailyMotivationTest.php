<?php

namespace Tests\Feature;

use App\Models\MotivationAssignment;
use App\Models\MotivationMessage;
use App\Models\MotivationSetting;
use App\Models\MotivationUserState;
use App\Models\User;
use App\Notifications\DailyMotivationNotification;
use App\Notifications\DailyMotivationTestNotification;
use App\Services\DailyMotivation\DailyMotivationService;
use App\Services\DailyMotivation\MotivationRotation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class DailyMotivationTest extends TestCase
{
    use DatabaseTransactions;

    private DailyMotivationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        if (! \Illuminate\Support\Facades\Schema::hasTable('motivation_settings')) {
            $this->fail('Run php artisan migrate before Daily Motivation tests.');
        }

        Carbon::setTestNow(Carbon::now('Asia/Dubai')->setTime(9, 30));
        $this->service = app(DailyMotivationService::class);
        $settings = MotivationSetting::query()->first();
        if ($settings && $settings->is_enabled) {
            $settings->forceFill(['is_enabled' => false])->save();
        }
        $this->service->forgetCaches();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_guests_and_ordinary_users_cannot_manage_the_feature(): void
    {
        $this->getJson('/api/daily-motivation/today')->assertUnauthorized();

        $sales = $this->makeUser('sales');

        $this->asUser($sales)->getJson('/api/daily-motivation/admin/overview')->assertForbidden();
        $this->asUser($sales)->postJson('/api/daily-motivation/admin/delivery', [
            'enabled' => true,
            'confirm' => true,
        ])->assertForbidden();
        $this->asUser($sales)->getJson('/api/daily-motivation/today')
            ->assertOk()
            ->assertJsonPath('data.delivery', 'disabled')
            ->assertJsonPath('data.message', null);

        $this->assertSame(0, MotivationAssignment::query()->where('user_id', $sales->id)->count());
    }

    public function test_overview_lists_only_activated_sales_agents(): void
    {
        $admin = $this->makeUser('super_admin');
        $agent = $this->makeUser('sales');
        $neverLoggedIn = $this->makeUser('sales');
        $neverLoggedIn->forceFill(['last_login_at' => null])->save();
        $inactive = $this->makeUser('sales', 'in_active');
        $staffWithSales = $this->makeUser('admin');
        Role::findOrCreate('sales', 'api');
        $staffWithSales->assignRole('sales');

        $names = $this->asUser($admin)->getJson('/api/daily-motivation/admin/overview')
            ->assertOk()
            ->json('data.sales');

        $ids = collect($names)->pluck('id')->all();
        $this->assertContains($agent->id, $ids);
        $this->assertNotContains($neverLoggedIn->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
        $this->assertNotContains($staffWithSales->id, $ids);
        $this->assertNotContains($admin->id, $ids);
    }

    public function test_enable_requires_confirmation_and_the_full_collection(): void
    {
        $admin = $this->makeUser('super_admin');

        $this->asUser($admin)->postJson('/api/daily-motivation/admin/delivery', [
            'enabled' => true,
        ])->assertStatus(422);

        $this->assertFalse((bool) MotivationSetting::query()->first()->is_enabled);

        $this->ensureMessages();

        $this->asUser($admin)->postJson('/api/daily-motivation/admin/delivery', [
            'enabled' => true,
            'confirm' => true,
        ])->assertOk()->assertJsonPath('data.is_enabled', true);

        $this->assertNotNull(MotivationSetting::query()->first()->anchor_date);
    }

    public function test_two_users_receive_different_messages_and_repeat_visits_do_not_duplicate(): void
    {
        $this->travelToDubai('2026-10-09');
        $this->ensureMessages();
        $admin = $this->makeUser('super_admin');
        $first = $this->makeUser('sales');
        $second = $this->makeUser('sales');

        $this->asUser($admin)->postJson('/api/daily-motivation/admin/delivery', [
            'enabled' => true,
            'confirm' => true,
        ])->assertOk();

        $firstPayload = $this->asUser($first)->getJson('/api/daily-motivation/today?user_id='.$second->id)
            ->assertOk()
            ->json('data');
        $secondPayload = $this->asUser($second)->getJson('/api/daily-motivation/today')
            ->assertOk()
            ->json('data');

        $this->assertNotSame($firstPayload['message']['number'], $secondPayload['message']['number']);
        $this->assertSame(
            $this->expectedNumber($first),
            $firstPayload['message']['number']
        );
        $this->assertSame(
            $this->expectedNumber($second),
            $secondPayload['message']['number']
        );

        $this->asUser($first)->getJson('/api/daily-motivation/today')->assertOk();
        $this->assertSame(1, MotivationAssignment::query()->where('user_id', $first->id)->count());
    }

    public function test_the_cycle_advances_by_calendar_day_wraps_and_ignores_missed_logins(): void
    {
        $this->travelToDubai('2026-10-01');
        $this->ensureMessages();
        $user = $this->makeUser('sales');
        $this->enable();

        $start = $this->service->presentFor($user);
        $offset = MotivationUserState::query()->where('user_id', $user->id)->value('rotation_offset');

        $this->travelToDubai('2026-10-04');
        $later = $this->service->presentFor($user);

        $this->assertSame(2, MotivationAssignment::query()->where('user_id', $user->id)->count());
        $this->assertSame((int) $offset, (int) MotivationUserState::query()->where('user_id', $user->id)->value('rotation_offset'));
        $this->assertSame((($offset + 3) % 120) + 1, $later['message']['canonical_number']);
        $this->assertNotSame($start['message']['canonical_number'], $later['message']['canonical_number']);

        $anchor = Carbon::parse('2026-10-01', 'Asia/Dubai')->startOfDay();
        $this->travelToDubai($anchor->copy()->addDays(120)->toDateString());
        $wrapped = $this->service->presentFor($user);

        $this->assertSame((($offset + 120) % 120) + 1, $wrapped['message']['canonical_number']);
    }

    public function test_dismiss_stops_the_repeat_interruption_and_inactive_users_are_skipped(): void
    {
        $this->travelToDubai('2026-10-09');
        $this->ensureMessages();
        $user = $this->makeUser('sales');
        $this->enable();

        $this->asUser($user)->getJson('/api/daily-motivation/today')->assertJsonPath('data.auto_open', true);
        $this->asUser($user)->postJson('/api/daily-motivation/today/dismiss')->assertOk()->assertJsonPath('data.dismissed', true);
        $this->asUser($user)->getJson('/api/daily-motivation/today')->assertJsonPath('data.auto_open', false);
        $this->assertSame(1, MotivationAssignment::query()->where('user_id', $user->id)->count());

        $inactive = $this->makeUser('sales', 'in_active');
        $before = MotivationAssignment::query()->count();
        $payload = $this->service->presentFor($inactive);

        $this->assertFalse($payload['eligible']);
        $this->assertNull($payload['message']);
        $this->assertSame($before, MotivationAssignment::query()->count());
        $this->asUser($inactive)->getJson('/api/daily-motivation/today')->assertForbidden();

        $offset = MotivationUserState::query()->where('user_id', $user->id)->value('rotation_offset');
        $user->forceFill(['status' => 'in_active'])->save();
        $user->forceFill(['status' => 'active'])->save();
        $this->assertSame((int) $offset, (int) MotivationUserState::query()->where('user_id', $user->id)->value('rotation_offset'));
    }

    public function test_private_test_does_not_write_assignments_and_disable_keeps_progress(): void
    {
        $this->travelToDubai('2026-10-09');
        $this->ensureMessages();
        $admin = $this->makeUser('super_admin');
        $agent = $this->makeUser('sales');
        $this->enable();

        $this->service->presentFor($agent);
        $assignments = MotivationAssignment::query()->where('user_id', $admin->id)->count();
        $reset = MotivationUserState::query()->where('user_id', $admin->id)->value('cycle_reset_day_index');

        $this->asUser($admin)->postJson('/api/daily-motivation/admin/preview', ['number' => 7])
            ->assertOk()
            ->assertJsonPath('data.message.test', true)
            ->assertJsonPath('data.message.number', 7);

        $this->asUser($admin)->postJson('/api/daily-motivation/admin/send-test', ['number' => 8])
            ->assertOk();

        $this->assertSame($assignments, MotivationAssignment::query()->where('user_id', $admin->id)->count());
        $this->assertSame(
            $reset,
            MotivationUserState::query()->where('user_id', $admin->id)->value('cycle_reset_day_index')
        );
        $this->assertSame(1, $admin->notifications()->where('type', DailyMotivationTestNotification::class)->count());
        $this->assertSame(0, $agent->notifications()->where('type', DailyMotivationTestNotification::class)->count());

        $kept = MotivationAssignment::query()->where('user_id', $agent->id)->count();
        $anchor = MotivationSetting::query()->first()->anchor_date->toDateString();

        $this->asUser($admin)->postJson('/api/daily-motivation/admin/delivery', [
            'enabled' => false,
            'confirm' => true,
        ])->assertOk();

        $this->asUser($agent)->getJson('/api/daily-motivation/today')
            ->assertOk()
            ->assertJsonPath('data.delivery', 'disabled')
            ->assertJsonPath('data.message', null);

        $this->assertSame($kept, MotivationAssignment::query()->where('user_id', $agent->id)->count());
        $this->assertSame($anchor, MotivationSetting::query()->first()->anchor_date->toDateString());
        $this->assertNotNull(MotivationUserState::query()->where('user_id', $agent->id)->first());
    }

    public function test_reset_restarts_one_user_without_moving_anyone_else(): void
    {
        $this->travelToDubai('2026-10-09');
        $this->ensureMessages();
        $admin = $this->makeUser('super_admin');
        $agent = $this->makeUser('sales');
        $other = $this->makeUser('sales');
        $this->enable();

        $this->service->presentFor($agent);
        $this->service->presentFor($other);
        $otherNumber = (int) MotivationAssignment::query()->where('user_id', $other->id)->value('message_number');

        $this->travelToDubai('2026-10-12');
        $this->asUser($admin)->postJson('/api/daily-motivation/admin/users/'.$agent->id.'/reset', [
            'confirm' => true,
        ])->assertOk();

        $fresh = $this->service->presentFor($agent);
        $offset = (int) MotivationUserState::query()->where('user_id', $agent->id)->value('rotation_offset');

        $this->assertSame($offset + 1, $fresh['message']['canonical_number']);
        $this->assertSame(
            $otherNumber,
            (int) MotivationAssignment::query()->where('user_id', $other->id)->where('assigned_on', '2026-10-09')->value('message_number')
        );
        $this->assertSame(0, MotivationAssignment::query()->where('user_id', $other->id)->where('assigned_on', '2026-10-12')->count());
    }

    public function test_the_nine_am_send_notifies_active_users_once_and_stop_ends_it(): void
    {
        $this->ensureMessages();
        $agent = $this->makeUser('sales');
        $other = $this->makeUser('sales');
        $inactive = $this->makeUser('sales', 'in_active');
        $manager = $this->makeUser('manager');
        $this->enable();

        $waiting = Carbon::parse('2026-10-02 08:30:00', 'Asia/Dubai');
        Carbon::setTestNow($waiting);
        $this->assertSame('waiting', $this->service->deliverToday()['status']);
        $this->assertNull($this->service->presentFor($agent)['message']);

        $this->travelToDubai('2026-10-02');
        $first = $this->service->deliverToday();
        $this->assertSame('sent', $first['status']);
        $this->assertSame(1, $agent->notifications()->where('type', DailyMotivationNotification::class)->count());
        $this->assertSame(1, $other->notifications()->where('type', DailyMotivationNotification::class)->count());
        $this->assertSame(0, $inactive->notifications()->where('type', DailyMotivationNotification::class)->count());
        $this->assertNull($this->service->presentFor($manager)['message']);
        $this->assertSame(0, $manager->notifications()->where('type', DailyMotivationNotification::class)->count());
        $this->assertNotSame('', (string) $agent->notifications()->first()->data['body_en']);

        $this->assertSame(0, $this->service->deliverToday()['sent']);
        $this->assertSame(1, $agent->notifications()->where('type', DailyMotivationNotification::class)->count());

        $this->service->setDelivery(false, $agent->id);
        $this->travelToDubai('2026-10-03');
        $this->assertSame('stopped', $this->service->deliverToday()['status']);
        $this->assertSame(1, $agent->notifications()->where('type', DailyMotivationNotification::class)->count());
    }

    public function test_import_keeps_numbers_and_arabic_text(): void
    {
        $admin = $this->makeUser('super_admin');
        $path = storage_path('app/motivation-test-'.uniqid().'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $sheet = new Spreadsheet();
        $rows = $sheet->getActiveSheet();
        $rows->fromArray(['Title row', '', ''], null, 'A1');
        $rows->fromArray(['No', 'English', 'Arabic'], null, 'A2');
        for ($n = 1; $n <= 120; $n++) {
            $rows->fromArray([$n, "English line {$n}", "النص {$n}"], null, 'A'.($n + 2));
        }
        (new Xlsx($sheet))->save($path);
        $sheet->disconnectWorksheets();

        $uploaded = new \Illuminate\Http\UploadedFile($path, 'OIA_Daily_Sales_Motivation_120.xlsx', null, null, true);

        $this->asUser($admin)->post('/api/daily-motivation/admin/import', [
            'file' => $uploaded,
        ])->assertOk()->assertJsonPath('data.total', 120);

        $this->assertSame('النص 1', MotivationMessage::query()->where('number', 1)->value('body_ar'));
        $this->assertSame('English line 120', MotivationMessage::query()->where('number', 120)->value('body_en'));
        $this->assertSame(120, MotivationMessage::query()->whereBetween('number', [1, 120])->count());

        @unlink($path);
    }

    private function enable(): void
    {
        $admin = $this->makeUser('super_admin');
        $this->asUser($admin)->postJson('/api/daily-motivation/admin/delivery', [
            'enabled' => true,
            'confirm' => true,
        ])->assertOk();
    }

    private function ensureMessages(): void
    {
        for ($n = 1; $n <= 120; $n++) {
            MotivationMessage::query()->firstOrCreate(
                ['number' => $n],
                [
                    'body_en' => "Fixture {$n}",
                    'body_ar' => "تجربة {$n}",
                    'is_enabled' => true,
                ]
            );
        }

        MotivationMessage::query()->whereBetween('number', [1, 120])->update(['is_enabled' => true]);
        $this->service->forgetCaches();
    }

    private function expectedNumber(User $user): int
    {
        $state = MotivationUserState::query()->where('user_id', $user->id)->firstOrFail();
        $settings = MotivationSetting::query()->firstOrFail();
        $dayIndex = MotivationRotation::dayIndex(
            Carbon::parse($settings->anchor_date->toDateString(), 'Asia/Dubai')->startOfDay(),
            $this->service->today()
        );
        $personal = MotivationRotation::personalIndex($dayIndex, $state->cycle_reset_day_index);

        return MotivationRotation::messageNumber((int) $state->rotation_offset, $personal);
    }

    private function travelToDubai(string $date): void
    {
        Carbon::setTestNow(Carbon::parse($date.' 09:00:00', 'Asia/Dubai'));
    }

    private function makeUser(string $role, string $status = 'active'): User
    {
        $user = User::factory()->create([
            'status' => $status,
            'email' => uniqid($role, true).'@example.test',
            'last_login_at' => $status === 'active' ? now() : null,
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
