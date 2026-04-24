<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AttendanceCheckinController extends Controller
{
    public function departments(Request $request): JsonResponse
    {
        if (!$this->isSuperAdmin($request->user())) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!Schema::hasTable('departments')) {
            return response()->json([]);
        }

        $query = DB::table('departments')->select(['id', 'name']);
        if (Schema::hasColumn('departments', 'is_active')) {
            $query->where('is_active', 1);
        }

        $rows = $query->orderBy('name')->get();

        return response()->json($rows);
    }

    public function settings(Request $request): JsonResponse
    {
        if (!$this->isSuperAdmin($request->user())) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $resolved = $this->resolveSettingsSource();
        if (!$resolved['table']) {
            return response()->json([
                'day_of_week' => 6,
                'start_time' => '09:00:00',
                'end_time' => '10:00:00',
                'department_ids' => [],
            ]);
        }

        $settings = $resolved['row'];
        if (!$settings && $resolved['table']) {
            $this->persistSettingsToExistingTables(6, '09:00:00', '10:00:00');
            $resolved = $this->resolveSettingsSource();
            $settings = $resolved['row'];
        }
        if (!$settings) {
            return response()->json([
                'day_of_week' => 6,
                'start_time' => '09:00:00',
                'end_time' => '10:00:00',
                'department_ids' => [],
            ]);
        }

        $normalized = $this->normalizeSettingsRow($settings);
        return response()->json($normalized);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        if (!$this->isSuperAdmin($request->user())) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $resolved = $this->resolveSettingsSource();
        if (!$resolved['table']) {
            return response()->json(['message' => 'attendance settings table not found'], 422);
        }

        $validated = $request->validate([
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['integer', 'min:1'],
        ]);

        $start = $validated['start_time'] . ':00';
        $end = $validated['end_time'] . ':00';
        if ($end <= $start) {
            return response()->json(['message' => 'End time must be after start time'], 422);
        }

        $departmentIds = array_values(array_unique(array_map('intval', (array) ($validated['department_ids'] ?? []))));
        $this->persistSettingsToExistingTables((int) $validated['day_of_week'], $start, $end, $departmentIds);

        return response()->json([
            'success' => true,
            'day_of_week' => (int) $validated['day_of_week'],
            'start_time' => $start,
            'end_time' => $end,
            'department_ids' => $departmentIds,
            'saved_tables' => $this->existingSettingsTables(),
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        return response()->json($this->buildStatusPayload((int) $request->user()->id));
    }

    public function checkIn(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:4'],
        ]);

        $userId = (int) $request->user()->id;
        $payload = $this->buildStatusPayload($userId);

        if (!$payload['is_active_day']) {
            return response()->json(['success' => false, 'message' => 'Check-in not active today'], 422);
        }
        if (!$payload['is_department_active']) {
            return response()->json(['success' => false, 'message' => 'Check-in not required for your department'], 422);
        }
        if (!$payload['is_within_time_window']) {
            return response()->json(['success' => false, 'message' => 'Check-in not available now'], 422);
        }
        if ($payload['already_checked_in']) {
            return response()->json(['success' => false, 'message' => 'Already checked in today at '.$payload['check_in_at']], 422);
        }

        $providedCode = strtoupper((string) $request->input('code'));
        if ($providedCode !== (string) $payload['today_code']) {
            return response()->json(['success' => false, 'message' => 'Invalid code'], 422);
        }

        $timezone = config('app.timezone', 'Asia/Dubai');
        $now = Carbon::now($timezone);
        $todayDate = $now->toDateString();

       $duplicate = DB::table('attendance_checkins')
        ->where('user_id', $userId)
        ->whereDate($this->checkinDateColumn(), $todayDate)
            ->first();
        
        if ($duplicate) {
            return response()->json([
                'success' => false,
                'message' => 'Already checked in today at '.$duplicate->checked_in_at,
                'checked_in_at' => $duplicate->checked_in_at,
            ], 422);
        }
        DB::table('attendance_checkins')->insert([
            'user_id' => $userId,
            $this->checkinDateColumn() => $todayDate,
            'checked_in_at' => $now,
            'code_used' => $providedCode,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $updated = $this->buildStatusPayload($userId);
        return response()->json([
            'success' => true,
            'message' => 'Checked in successfully',
            'status' => $updated['status'],
            'checked_in_at' => $now->format('h:i A'),
        ]);
    }

    private function isSuperAdmin($user): bool
    {
          if (!$user) return false;

          return $user->hasRole(['super_admin', 'super admin', 'Super Admin']);
    }

    private function buildStatusPayload(int $userId): array
    {
        $timezone = config('app.timezone', 'Asia/Dubai');
        $now = Carbon::now($timezone);
        $todayDate = $now->toDateString();

        $resolved = $this->resolveSettingsSource();
        $tableName = $resolved['table'];
        if (!$tableName) {
            return [
                'is_active_day' => false,
                'is_within_time_window' => false,
                'already_checked_in' => false,
                'status' => 'Closed',
                'window_label' => 'Not configured',
                'today_code' => '',
            ];
        }

        $settings = $resolved['row'];
        if (!$settings && $resolved['table']) {
            $this->persistSettingsToExistingTables(6, '09:00:00', '10:00:00');
            $resolved = $this->resolveSettingsSource();
            $settings = $resolved['row'];
        }
        if (!$settings) {
            return [
                'is_active_day' => false,
                'is_within_time_window' => false,
                'already_checked_in' => false,
                'status' => 'Closed',
                'window_label' => 'Not configured',
                'today_code' => '',
            ];
        }

        $normalized = $this->normalizeSettingsRow($settings);
        $currentDayOfWeek = (int) $now->dayOfWeek;
        $isActiveDay = $currentDayOfWeek === (int) $normalized['day_of_week'];
        $userDepartmentId = $this->resolveUserDepartmentId($userId);
        $activeDepartmentIds = array_map('intval', (array) ($normalized['department_ids'] ?? []));
        $isSuperAdminUser = $this->isSuperAdminByUserId($userId);
        $isDepartmentActive = empty($activeDepartmentIds)
            || ($userDepartmentId !== null && in_array($userDepartmentId, $activeDepartmentIds, true))
            || ($userDepartmentId === null && $isSuperAdminUser);

        $currentTime = $now->format('H:i:s');
        $startTime = (string) $normalized['start_time'];
        $endTime = (string) $normalized['end_time'];
        $isWithinTimeWindow = $currentTime >= $startTime && $currentTime < $endTime;

        $alreadyCheckedIn = false;
        if (Schema::hasTable('attendance_checkins')) {
          
                $checkinRecord = DB::table('attendance_checkins')
                ->where('user_id', $userId)
                ->whereDate($this->checkinDateColumn(), $todayDate)
                ->first();
            
            $alreadyCheckedIn = $checkinRecord ? true : false;
            $checkedInAt = $checkinRecord->checked_in_at ?? null;
        }

        $status = $alreadyCheckedIn
            ? 'Checked In'
            : (($isActiveDay && $isDepartmentActive && $isWithinTimeWindow) ? 'Not Checked In' : 'Closed');

        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $dayLabel = $days[(int) $normalized['day_of_week']] ?? 'Unknown Day';
        $startLabel = $this->formatWindowTimeLabel($startTime, $timezone);
        $endLabel = $this->formatWindowTimeLabel($endTime, $timezone);

        return [
            'is_active_day' => $isActiveDay,
            'is_department_active' => $isDepartmentActive,
            'is_within_time_window' => $isWithinTimeWindow,
            'already_checked_in' => $alreadyCheckedIn,
            'check_in_at'=>$checkedInAt,
            'status' => $status,
            'window_label' => "{$dayLabel} • {$startLabel} - {$endLabel}",
            'today_code' => $this->generateCodeForDate($now),
            'department_ids' => $activeDepartmentIds,
            'user_department_id' => $userDepartmentId,
        ];
    }

    private function generateCodeForDate(Carbon $date): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $hash = hash('sha256', $date->toDateString() . '|' . (string) config('app.key'));
        $code = '';

        for ($i = 0; $i < 4; $i++) {
            $pair = substr($hash, $i * 2, 2);
            $index = hexdec($pair) % strlen($alphabet);
            $code .= $alphabet[$index];
        }

        return $code;
    }

    private function settingsTableName(): ?string
    {
        if (Schema::hasTable('attendance_settings')) {
            return 'attendance_settings';
        }
        if (Schema::hasTable('attendance_checkin_settings')) {
            return 'attendance_checkin_settings';
        }
        return null;
    }

    private function resolveSettingsSource(): array
    {
        $hasPrimary = Schema::hasTable('attendance_settings');
        $hasLegacy = Schema::hasTable('attendance_checkin_settings');

        $primaryRow = $hasPrimary ? DB::table('attendance_settings')->orderBy('id')->first() : null;
        $legacyRow = $hasLegacy ? DB::table('attendance_checkin_settings')->orderBy('id')->first() : null;

        // Prefer non-empty data source. If both have data, prefer primary table.
        if ($primaryRow) {
            return ['table' => 'attendance_settings', 'row' => $primaryRow];
        }
        if ($legacyRow) {
            return ['table' => 'attendance_checkin_settings', 'row' => $legacyRow];
        }

        // No rows yet: pick whichever table exists (prefer primary if present).
        if ($hasPrimary) {
            return ['table' => 'attendance_settings', 'row' => null];
        }
        if ($hasLegacy) {
            return ['table' => 'attendance_checkin_settings', 'row' => null];
        }

        return ['table' => null, 'row' => null];
    }

    private function existingSettingsTables(): array
    {
        $tables = [];
        if (Schema::hasTable('attendance_settings')) {
            $tables[] = 'attendance_settings';
        }
        if (Schema::hasTable('attendance_checkin_settings')) {
            $tables[] = 'attendance_checkin_settings';
        }
        return $tables;
    }

    private function persistSettingsToExistingTables(int $dayOfWeek, string $start, string $end, array $departmentIds = []): void
    {
        $now = now();
        $encodedDepartmentIds = json_encode(array_values(array_unique(array_map('intval', $departmentIds))));

        if (Schema::hasTable('attendance_settings')) {
            $existing = DB::table('attendance_settings')->orderBy('id')->first();
            $payload = [
                'day_of_week' => $dayOfWeek,
                'start_time' => $start,
                'end_time' => $end,
                'updated_at' => $now,
            ];
            if (Schema::hasColumn('attendance_settings', 'department_ids')) {
                $payload['department_ids'] = $encodedDepartmentIds;
            }
            if ($existing) {
                DB::table('attendance_settings')->where('id', $existing->id)->update($payload);
            } else {
                DB::table('attendance_settings')->insert(array_merge($payload, ['created_at' => $now]));
            }
        }

        if (Schema::hasTable('attendance_checkin_settings')) {
            $existing = DB::table('attendance_checkin_settings')->orderBy('id')->first();
            $payload = [
                'active_weekday' => $dayOfWeek,
                'window_start' => $start,
                'window_end' => $end,
                'updated_at' => $now,
            ];
            if (Schema::hasColumn('attendance_checkin_settings', 'active_department_ids')) {
                $payload['active_department_ids'] = $encodedDepartmentIds;
            }
            if ($existing) {
                DB::table('attendance_checkin_settings')->where('id', $existing->id)->update($payload);
            } else {
                DB::table('attendance_checkin_settings')->insert(array_merge($payload, ['created_at' => $now]));
            }
        }
    }

    private function normalizeSettingsRow(object $settings): array
    {
        $dayOfWeek = data_get($settings, 'day_of_week');
        if ($dayOfWeek === null) {
            $dayOfWeek = data_get($settings, 'active_weekday', 6);
        }

        $startTime = data_get($settings, 'start_time');
        if ($startTime === null) {
            $startTime = data_get($settings, 'window_start', '09:00:00');
        }

        $endTime = data_get($settings, 'end_time');
        if ($endTime === null) {
            $endTime = data_get($settings, 'window_end', '10:00:00');
        }

        $departmentIds = data_get($settings, 'department_ids');
        if ($departmentIds === null) {
            $departmentIds = data_get($settings, 'active_department_ids', []);
        }
        if (is_string($departmentIds)) {
            $decoded = json_decode($departmentIds, true);
            $departmentIds = is_array($decoded) ? $decoded : [];
        }

        return [
            'day_of_week' => (int) $dayOfWeek,
            'start_time' => (string) $startTime,
            'end_time' => (string) $endTime,
            'department_ids' => array_values(array_unique(array_map('intval', (array) $departmentIds))),
        ];
    }

    private function resolveUserDepartmentId(int $userId): ?int
    {
        if (Schema::hasTable('employee_profiles') && Schema::hasColumn('employee_profiles', 'department_id')) {
            $departmentId = DB::table('employee_profiles')
                ->where('user_id', $userId)
                ->value('department_id');

            if ($departmentId !== null) {
                return (int) $departmentId;
            }
        }

        // Fallback for setups where department is stored directly on users table.
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'department_id')) {
            $departmentId = DB::table('users')
                ->where('id', $userId)
                ->value('department_id');

            if ($departmentId !== null) {
                return (int) $departmentId;
            }
        }

        return null;
    }

    private function checkinDateColumn(): string
    {
        if (Schema::hasColumn('attendance_checkins', 'date')) {
            return 'date';
        }
        if (Schema::hasColumn('attendance_checkins', 'checkin_date')) {
            return 'checkin_date';
        }
        // Fallback for legacy/unknown schema
        return 'date';
    }

    private function formatWindowTimeLabel(string $time, string $timezone): string
    {
        $normalized = trim($time);

        // Normalize H:i to H:i:s if legacy rows were saved without seconds.
        if (preg_match('/^\d{1,2}:\d{2}$/', $normalized) === 1) {
            $normalized .= ':00';
        }

        try {
            return Carbon::createFromFormat('H:i:s', $normalized, $timezone)->format('g:i A');
        } catch (\Throwable $e) {
            return $normalized !== '' ? $normalized : '--:--';
        }
    }

    private function isSuperAdminByUserId(int $userId): bool
    {
        $user = \App\Models\User::find($userId);
    
        if (!$user) {
            return false;
        }
    
        return $user->hasRole('super_admin');
    }
}

