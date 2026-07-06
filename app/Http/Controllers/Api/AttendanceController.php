<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Helpers\ApiResponse;

class AttendanceController extends Controller
{
    public function today(Request $request)
    {
        return $this->respondFromDatabase($request, true);
    }

    public function index(Request $request)
    {
        return $this->respondFromDatabase($request, false);
    }

    /**
     * Sync remote attendance into `attendances` for a calendar date (Y-m-d).
     *
     * @return array{api_count: int, saved_count: int}
     */
    public function syncAttendanceFromApi(string $date): array
    {
        $targetDate = Carbon::parse($date)->toDateString();
        $remoteRows = $this->fetchRemoteRowsMapped($targetDate);
        $apiCount = count($remoteRows);
        $savedCount = 0;

        foreach ($remoteRows as $row) {
            $rawKey = (string) ($row['employee_key'] ?? $row['employee_id'] ?? '');
            $normId = Attendance::normalizeEmployeeId($rawKey);
            if ($normId === null || $normId === '') {
                continue;
            }

            $status = $this->resolveStatus($row);
            $checkIn = $this->parseDateTime($row['check_in'] ?? null);
            $checkOut = $this->parseDateTime($row['check_out'] ?? null);

            Attendance::updateOrCreate(
                [
                    'employee_id' => $normId,
                    'date' => $targetDate,
                ],
                [
                    'employee_name' => $row['employee_name'] ?? 'Unknown',
                    'status' => $status,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'user_id' => $row['user_id'] ?? null,
                ]
            );
            $savedCount++;
        }

        Log::info('Attendance syncAttendanceFromApi', [
            'date' => $targetDate,
            'api_count' => $apiCount,
            'db_saved_count' => $savedCount,
        ]);

        return ['api_count' => $apiCount, 'saved_count' => $savedCount];
    }

 private function respondFromDatabase(Request $request, bool $todayOnly): \Illuminate\Http\JsonResponse
{
    $date = $request->query('date');
    $statusFilter = strtolower((string) $request->query('status', 'all'));
    $employeeIdFilter = $request->query('employee_id');
    $page = (int) $request->query('page', 1);
    $perPage = (int) $request->query('per_page', 15);

    $defaultDate = Carbon::today('Asia/Dubai')->toDateString();
    $targetDate = $todayOnly
        ? ($date ? Carbon::parse($date)->toDateString() : $defaultDate)
        : ($date ?: $defaultDate);

    try {
        if (Attendance::query()->whereDate('date', $targetDate)->count() === 0) {
            $sync = $this->syncAttendanceFromApi($targetDate);
            Log::info('Attendance auto-sync (empty date)', [
                'date' => $targetDate,
                'api_count' => $sync['api_count'],
                'db_saved_count' => $sync['saved_count'],
            ]);
        }
    } catch (\Throwable $e) {
        Log::warning('Attendance auto-sync failed; serving DB only', [
            'date' => $targetDate,
            'error' => $e->getMessage(),
        ]);
    }

    $query = Attendance::query()
        ->with([
            'user:id,name,email,avatar,status',
            'user.employeeProfile.department',
            'user.employeeProfile.companyBranch',
        ])
        ->whereDate('date', $targetDate);

    if ($employeeIdFilter !== null && $employeeIdFilter !== '') {
        $norm = Attendance::normalizeEmployeeId((string) $employeeIdFilter);
        if ($norm) {
            $query->where('employee_id', $norm);
        }
    }

    if (in_array($statusFilter, ['present', 'absent', 'late'], true)) {
        $query->where('status', $statusFilter);
    }

    // ✅ استخدام paginate بدلاً من get
    $paginated = $query->orderBy('employee_name')->paginate($perPage, ['*'], 'page', $page);
    
    Log::info('Attendance GET (database)', [
        'date' => $targetDate,
        'total' => $paginated->total(),
        'status_filter' => $statusFilter,
        'page' => $page,
        'per_page' => $perPage,
    ]);

    // ✅ تحويل البيانات
    $normalized = $paginated->getCollection()->map(function (Attendance $attendance) {
        return [
            'id' => $attendance->id,
            'employee_id' => $attendance->employee_id,
            'biometric_code' => $attendance->user?->biometric_code,
            'employee_name' => $attendance->employee_name ?? $attendance->user?->name ?? 'Unknown',
            'status' => $attendance->status ?: $this->resolveStatus([
                'status' => null,
                'check_in' => $attendance->check_in,
            ]),
            'check_in' => $attendance->check_in?->timezone('Asia/Dubai')->toDateTimeString(),
            'check_out' => $attendance->check_out?->timezone('Asia/Dubai')->toDateTimeString(),
            'date' => $attendance->date ? Carbon::parse($attendance->date)->toDateString() : null,
            'department' => $attendance->user?->employeeProfile?->department?->name,
            'branch' => $attendance->user?->employeeProfile?->companyBranch?->name,
            'employee_code' => $attendance->user?->employeeProfile?->employee_code ?? $attendance->employee_id,
            'attendance_type' => $this->resolveAttendanceType($attendance),
            'email' => $attendance->user?->email,
            'user' => $attendance->user ? [
                'id' => $attendance->user->id,
                'name' => $attendance->user->name,
                'email' => $attendance->user->email,
                'avatar' => $attendance->user->avatar ?? 'users/user.png',
                'status' => $attendance->user->status ?? 'active',
            ] : null,
        ];
    })->values();

    $resolvedDate = (string) ($normalized->first()['date'] ?? $targetDate);

    $summary = [
        'total_employees' => $paginated->total(),
        'present_today' => $normalized->where('status', 'present')->count(),
        'absent_today' => $normalized->where('status', 'absent')->count(),
        'late_today' => $normalized->where('status', 'late')->count(),
    ];

    // ✅ إرجاع البيانات مع هيكل pagination متوافق مع الـ Frontend
    return response()->json([
        'success' => true,
        'data' => [
            'date' => $resolvedDate,
            'summary' => $summary,
            'employees' => $normalized,
            // معلومات pagination
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
            'per_page' => $paginated->perPage(),
            'from' => $paginated->firstItem(),
            'to' => $paginated->lastItem(),
            'first_page_url' => $paginated->url(1),
            'last_page_url' => $paginated->url($paginated->lastPage()),
            'next_page_url' => $paginated->nextPageUrl(),
            'prev_page_url' => $paginated->previousPageUrl(),
            'path' => $paginated->path(),
            'links' => $paginated->linkCollection()->toArray(),
        ],
    ]);
}

    private function resolveAttendanceType(Attendance $attendance): string
    {
        return match ($attendance->status) {
            'absent' => '—',
            'late', 'present' => 'Office',
            default => 'Office',
        };
    }

    /**
     * Remote API rows mapped to local shape (employee_id here is RAW — normalized inside sync).
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchRemoteRowsMapped(string $targetDate): array
    {
        try {
            $baseUrl = rtrim((string) env('ATTENDANCE_BASE_URL', 'https://oiahead.fortidyndns.com/api'), '/');
            $rows = $this->fetchRemoteAttendanceJson($baseUrl . '/attendance/today');

            $rowsForDate = collect($rows)
                ->filter(fn ($row) => is_array($row))
                ->filter(fn ($row) => ($row['attendance_date'] ?? null) === $targetDate)
                ->values()
                ->all();

            if ($rowsForDate === []) {
                $rangeUrl = $baseUrl . '/attendance/range?from=' . urlencode($targetDate) . '&to=' . urlencode($targetDate);
                $rows = $this->fetchRemoteAttendanceJson($rangeUrl);
                $rowsForDate = collect($rows)
                    ->filter(fn ($row) => is_array($row))
                    ->filter(fn ($row) => ($row['attendance_date'] ?? null) === $targetDate)
                    ->values()
                    ->all();
            }

            return $this->mapRemoteAttendanceRows($rowsForDate, $targetDate);
        } catch (\Throwable $e) {
            Log::warning('Attendance remote API exception', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchRemoteAttendanceJson(string $url): array
    {
        $response = Http::withBasicAuth(
            (string) env('ATTENDANCE_BASIC_USER', 'admin'),
            (string) env('ATTENDANCE_BASIC_PASSWORD', 'admin1234')
        )
            ->withHeaders([
                'x-api-key' => (string) env('ATTENDANCE_API_KEY', 'zkbio_secure_2026'),
                'Accept' => 'application/json',
            ])
            ->withoutVerifying()
            ->timeout(30)
            ->get($url);

        if (!$response->successful()) {
            Log::warning('Attendance remote API failed', [
                'status' => $response->status(),
                'url' => $url,
            ]);

            return [];
        }

        $rows = $response->json();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rowsForDate
     * @return array<int, array<string, mixed>>
     */
    private function mapRemoteAttendanceRows(array $rowsForDate, string $targetDate): array
    {
        if ($rowsForDate === []) {
            return [];
        }

        $usersByBioCode = User::query()
            ->whereNotNull('biometric_code')
            ->with(['employeeProfile.department:id,name'])
            ->get(['id', 'name', 'email', 'biometric_code'])
            ->keyBy('biometric_code');

        $mapped = [];
        foreach ($rowsForDate as $row) {
            $bioCode = (string) ($row['emp_code'] ?? '');
            $user = $usersByBioCode->get($bioCode);

            $fallbackName = trim(
                (string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? '')
            );

            $employeeKey = $user?->biometric_code ?: $bioCode ?: (string) ($user?->id ?? '');

            $mapped[] = [
                'employee_key' => $employeeKey,
                'employee_id' => $user?->id ?? $bioCode,
                'biometric_code' => $user?->biometric_code,
                'user_id' => $user?->id,
                'employee_name' => $user?->name ?: ($fallbackName ?: ($bioCode ?: 'Unknown')),
                'email' => $user?->email,
                'department' => $user?->employeeProfile?->department?->name,
                'status' => !empty($row['status']) ? strtolower((string) $row['status']) : null,
                'check_in' => $row['first_checkin'] ?? null,
                'check_out' => $row['last_checkout'] ?? null,
                'date' => $row['attendance_date'] ?? $targetDate,
            ];
        }

        return $mapped;
    }

    private function parseDateTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function resolveStatus(array $row): string
    {
        $status = strtolower((string) ($row['status'] ?? ''));
        if (in_array($status, ['present', 'absent', 'late'], true)) {
            return $status;
        }

        $checkInRaw = $row['check_in'] ?? null;
        if (!$checkInRaw) {
            return 'absent';
        }

        try {
            $checkIn = Carbon::parse($checkInRaw, 'Asia/Dubai');
            $lateBoundary = Carbon::parse($checkIn->toDateString() . ' 09:15:00');

            return $checkIn->gt($lateBoundary) ? 'late' : 'present';
        } catch (\Throwable $e) {
            return 'present';
        }
    }
     public function syncLastMonth()
{
 
    // $this->info('Syncing last month attendance...');

    try {
      $from = now('Asia/Dubai')->startOfMonth()->toDateString();
        $to = now('Asia/Dubai')->toDateString();

        $url = "https://oiahead.fortidyndns.com/api/attendance/range?from={$from}&to={$to}";

        $response = Http::withBasicAuth('admin', 'admin1234')
            ->withHeaders([
                'x-api-key' => 'zkbio_secure_2026',
                'Accept' => 'application/json',
            ])
            ->withoutVerifying()
            ->timeout(60)
            ->get($url);

        if (!$response->successful()) {
            $this->error('API Error: ' . $response->status());
            return;
        }

        $data = $response->json() ?? [];

        \Log::info('Monthly Attendance API', ['count' => count($data)]);

        // ✔️ users map
        $users = User::whereNotNull('biometric_code')
            ->pluck('id', 'biometric_code');

        $count = 0;

        foreach ($data as $item) {

            $date = $item['attendance_date'] ?? null;

            if (!$date) continue;

            if (empty($item['emp_code'])) continue;

            $userId = $users[$item['emp_code']] ?? null;

            if (!$userId) {
                // $this->warn('User not found: ' . $item['emp_code']);
                continue;
            }

            $checkIn = !empty($item['first_checkin'])
                ? Carbon::parse($item['first_checkin'], 'Asia/Dubai')
                : null;
            
            $checkOut = !empty($item['last_checkout'])
                ? Carbon::parse($item['last_checkout'], 'Asia/Dubai')
                : null;

            Attendance::updateOrCreate(
                [
                    'user_id' => $userId,
                    'date' => $date,
                ],
                [
                    'employee_id' => $item['emp_code'],
                    'employee_name' => trim(
                        ($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? '')
                    ),
                    'status' => !empty($item['status'])
                        ? strtolower($item['status'])
                        : null,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                ]
            );

            $count++;
        }

        // $this->info("Last month synced: {$count} records.");

    } catch (\Exception $e) {
        dd($e->getMessage());
        // $this->error($e->getMessage());
    }
}

public function generatePeriodReport(Request $request)
{
    $request->validate([
        'start_date' => 'nullable|date',
        'end_date' => 'nullable|date|after_or_equal:start_date',
    ]);

    $startDate = $request->start_date ? Carbon::parse($request->start_date) : Carbon::now('Asia/Dubai')->startOfMonth();
    $endDate = $request->end_date ? Carbon::parse($request->end_date) : Carbon::now('Asia/Dubai')->endOfMonth();

    $users = User::whereHas('attendances')->get();
    $reports = [];

    foreach ($users as $user) {
        $report = $this->buildUserPeriodReport($user, $startDate, $endDate);
        $reports[] = array_merge([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'biometric_code' => $user?->biometric_code,
            'department' => $user?->employeeProfile?->department?->name,
            'employee_id' => $user->employee_id ?? null,
            'period_start' => $startDate->format('Y-m-d'),
            'period_end' => $endDate->format('Y-m-d'),
        ], $report);
    }

    return response()->json([
        'success' => true,
        'data' => $reports,
        'meta' => [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'total_employees' => count($reports),
            'generated_at' => Carbon::now('Asia/Dubai')->format('Y-m-d H:i:s')
        ]
    ]);
}

/**
 * Monthly attendance history for the authenticated user (My Profile).
 */
public function myAttendanceHistory(Request $request)
{
    $user = $request->user();
    if (!$user) {
        return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
    }

    return $this->userAttendanceHistory($request, $user);
}

/**
 * Monthly attendance history for a single user (profile carousel).
 */
public function userAttendanceHistory(Request $request, User $user)
{
    $monthsBack = min(24, max(1, (int) $request->query('months', 12)));
    $now = Carbon::now('Asia/Dubai');

    // Pull latest biometric attendance for this user (same source as HR).
    $syncStart = $now->copy()->subMonths($monthsBack - 1)->startOfMonth();
    $this->syncUserAttendanceFromRemote($user, $syncStart, $now->copy()->endOfMonth());

    $months = [];

    for ($i = 0; $i < $monthsBack; $i++) {
        $startDate = $now->copy()->subMonths($i)->startOfMonth();
        $endDate = $now->copy()->subMonths($i)->endOfMonth();
        $report = $this->buildUserPeriodReport($user, $startDate, $endDate);

        $months[] = array_merge([
            'month' => $startDate->format('Y-m'),
            'label' => $startDate->format('F Y'),
            'period_start' => $startDate->format('Y-m-d'),
            'period_end' => $endDate->format('Y-m-d'),
        ], $report);
    }

    return response()->json([
        'success' => true,
        'data' => $months,
        'meta' => [
            'user_id' => $user->id,
            'months' => $monthsBack,
            'has_biometric' => !empty($user->biometric_code),
            'biometric_code' => $user->biometric_code,
            'generated_at' => Carbon::now('Asia/Dubai')->format('Y-m-d H:i:s'),
        ],
    ]);
}

/**
 * Normalized employee keys for matching (same rules as HR dashboard).
 *
 * @return list<string>
 */
private function employeeMatchKeysForUser(User $user): array
{
    $keys = [];
    foreach ([$user->biometric_code, (string) $user->id] as $raw) {
        if ($raw === null || $raw === '') {
            continue;
        }
        $norm = Attendance::normalizeEmployeeId((string) $raw);
        if ($norm) {
            $keys[] = $norm;
        }
        $trim = strtoupper(trim((string) $raw));
        if ($trim !== '') {
            $keys[] = $trim;
        }
    }

    return array_values(array_unique($keys));
}

private function attendanceMatchesUser(Attendance $attendance, User $user): bool
{
    if ((int) $attendance->user_id === (int) $user->id) {
        return true;
    }

    $userKeys = $this->employeeMatchKeysForUser($user);
    if (empty($userKeys)) {
        return false;
    }

    $attKey = Attendance::normalizeEmployeeId((string) $attendance->employee_id);

    return $attKey !== null && $attKey !== '' && in_array($attKey, $userKeys, true);
}

/**
 * Attendance rows for a user in a date range (user_id and/or biometric employee_id).
 */
private function attendancesForUserInRange(User $user, Carbon $startDate, Carbon $endDate)
{
    $userKeys = $this->employeeMatchKeysForUser($user);

    $query = Attendance::query()
        ->whereBetween('date', [
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d'),
        ]);

    if (!empty($userKeys) || $user->biometric_code) {
        $query->where(function ($q) use ($user, $userKeys) {
            $q->where('user_id', $user->id);
            if (!empty($userKeys)) {
                $q->orWhereIn('employee_id', $userKeys);
            }
            if ($user->biometric_code) {
                $q->orWhere('employee_id', trim((string) $user->biometric_code));
            }
        });
    } else {
        $query->where('user_id', $user->id);
    }

    return $query->get()
        ->filter(fn (Attendance $a) => $this->attendanceMatchesUser($a, $user))
        ->values();
}

/**
 * Sync remote biometric attendance for one user across a date range.
 */
private function syncUserAttendanceFromRemote(User $user, Carbon $startDate, Carbon $endDate): void
{
    $bio = trim((string) ($user->biometric_code ?? ''));
    if ($bio === '') {
        return;
    }

    $userNorm = Attendance::normalizeEmployeeId($bio);
    if (!$userNorm) {
        return;
    }

    try {
        $baseUrl = rtrim((string) env('ATTENDANCE_BASE_URL', 'https://oiahead.fortidyndns.com/api'), '/');
        $from = $startDate->format('Y-m-d');
        $to = $endDate->format('Y-m-d');
        $url = $baseUrl . "/attendance/range?from={$from}&to={$to}";

        $response = Http::withBasicAuth(
            (string) env('ATTENDANCE_BASIC_USER', 'admin'),
            (string) env('ATTENDANCE_BASIC_PASSWORD', 'admin1234')
        )
            ->withHeaders([
                'x-api-key' => (string) env('ATTENDANCE_API_KEY', 'zkbio_secure_2026'),
                'Accept' => 'application/json',
            ])
            ->withoutVerifying()
            ->timeout(60)
            ->get($url);

        if (!$response->successful()) {
            Log::warning('Profile attendance remote sync failed', [
                'user_id' => $user->id,
                'status' => $response->status(),
            ]);
            return;
        }

        $rows = $response->json();
        if (!is_array($rows)) {
            return;
        }

        foreach ($rows as $item) {
            if (!is_array($item)) {
                continue;
            }

            $empCode = (string) ($item['emp_code'] ?? '');
            if ($empCode === '') {
                continue;
            }

            $itemNorm = Attendance::normalizeEmployeeId($empCode);
            if ($itemNorm !== $userNorm) {
                continue;
            }

            $date = $item['attendance_date'] ?? null;
            if (!$date) {
                continue;
            }

            $checkIn = !empty($item['first_checkin'])
                ? Carbon::parse($item['first_checkin'], 'Asia/Dubai')
                : null;
            $checkOut = !empty($item['last_checkout'])
                ? Carbon::parse($item['last_checkout'], 'Asia/Dubai')
                : null;

            $status = !empty($item['status'])
                ? strtolower((string) $item['status'])
                : $this->resolveStatus([
                    'status' => null,
                    'check_in' => $checkIn?->toDateTimeString(),
                ]);

            Attendance::updateOrCreate(
                [
                    'employee_id' => $itemNorm,
                    'date' => $date,
                ],
                [
                    'user_id' => $user->id,
                    'employee_name' => $user->name,
                    'status' => $status,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                ]
            );
        }
    } catch (\Throwable $e) {
        Log::warning('Profile attendance remote sync exception', [
            'user_id' => $user->id,
            'error' => $e->getMessage(),
        ]);
    }
}

/**
 * Build attendance stats and daily breakdown for one user in a date range.
 */
private function buildUserPeriodReport(User $user, Carbon $startDate, Carbon $endDate): array
{
    $attendances = $this->attendancesForUserInRange($user, $startDate, $endDate);

    $present = 0;
    $late = 0;
    $absent = 0;
    $totalDeductionPercent = 0;
    $daysWithDeduction = 0;

    $workingDays = $this->getWorkingDaysInRange($startDate, $endDate);
    $dailyBreakdown = [];

    // If duplicate rows exist for the same day, prefer the one with check-in data.
    $attendanceMap = $attendances
        ->sortByDesc(fn (Attendance $a) => $a->check_in ? 1 : 0)
        ->keyBy(function ($a) {
            return Carbon::parse($a->date)->timezone('Asia/Dubai')->toDateString();
        });

    foreach ($workingDays as $date) {
        $attendance = $attendanceMap->get($date);

        $checkIn = null;
        $checkOut = null;
        $checkInTime = null;
        $checkOutTime = null;
        $dayDeductionPercent = 0;
        $status = 'Absent';

        if ($attendance) {
            if ($attendance->check_in) {
                $checkIn = Carbon::parse($attendance->check_in)->timezone('Asia/Dubai');
                $checkInTime = $checkIn->format('H:i:s');
            }
            if ($attendance->check_out) {
                $checkOut = Carbon::parse($attendance->check_out)->timezone('Asia/Dubai');
                $checkOutTime = $checkOut->format('H:i:s');
            }

            $storedStatus = strtolower((string) ($attendance->status ?? ''));

            if ($checkIn) {
                $dayDeductionPercent = $this->calculateDayDeduction($checkIn);
                if ($storedStatus === 'late' || ($storedStatus !== 'present' && $dayDeductionPercent > 0)) {
                    $status = 'Late';
                    $late++;
                    $totalDeductionPercent += $dayDeductionPercent > 0 ? $dayDeductionPercent : 10;
                    $daysWithDeduction++;
                } else {
                    $status = 'Present';
                    $present++;
                }
            } elseif (in_array($storedStatus, ['present', 'late'], true)) {
                $status = ucfirst($storedStatus);
                if ($storedStatus === 'late') {
                    $late++;
                    $dayDeductionPercent = 10;
                    $totalDeductionPercent += 10;
                    $daysWithDeduction++;
                } else {
                    $present++;
                }
            } else {
                $status = 'Absent';
                $absent++;
                $dayDeductionPercent = 100;
                $totalDeductionPercent += 100;
                $daysWithDeduction++;
            }
        } else {
            $absent++;
            $dayDeductionPercent = 100;
            $totalDeductionPercent += 100;
            $daysWithDeduction++;
        }

        $dailyBreakdown[] = [
            'date' => $date,
            'check_in' => $checkInTime,
            'check_out' => $checkOutTime,
            'status' => $status,
            'deduction_percent' => $dayDeductionPercent,
        ];
    }

    $avgDeductionPercent = $daysWithDeduction > 0
        ? round($totalDeductionPercent / $daysWithDeduction, 2)
        : 0;

    $totalWorkingDays = count($workingDays);
    $overallDeductionPercent = $totalWorkingDays > 0
        ? round(($totalDeductionPercent / $totalWorkingDays), 2)
        : 0;

    return [
        'present' => $present,
        'late' => $late,
        'absent' => $absent,
        'total_working_days' => $totalWorkingDays,
        'avg_deduction_percent' => $avgDeductionPercent,
        'total_deduction_percent' => $overallDeductionPercent,
        'total_deduction_sum' => $totalDeductionPercent,
        'days_with_deduction' => $daysWithDeduction,
        'daily_breakdown' => $dailyBreakdown,
    ];
}

/**
 * Get day status based on attendance and deduction
 */
private function getDayStatus($attendance, float $deductionPercent): string
{
    if (!$attendance) {
        return 'Absent';
    }
    
    if ($deductionPercent == 0) {
        return 'Present';
    }
    
    return 'Late';
}

/**
 * Calculate deduction percentage for a single day
 * Returns percentage value (0-100) for that specific day only
 */
private function calculateDayDeduction($checkIn = null): float
{
    // If no check-in, full day deduction
    if (!$checkIn) {
        return 100;
    }

    // Ensure $checkIn is Carbon instance
    if (!($checkIn instanceof \Carbon\Carbon)) {
        try {
            $checkIn = Carbon::parse($checkIn)->timezone('Asia/Dubai');
        } catch (\Exception $e) {
            return 100;
        }
    }

    $time = $checkIn->format('H:i');

    // Before 09:16 - No deduction
    if ($time < '09:16') {
        return 0;
    }
    
    // Between 09:16 and 10:00 - 10% deduction for this day
    if ($time >= '09:16' && $time <= '10:00') {
        return 10;
    }

    // Between 10:01 and 12:00 - 25% deduction for this day
    if ($time >= '10:01' && $time <= '12:00') {
        return 25;
    }

    // After 12:01 - Full day deduction
    if ($time >= '12:01') {
        return 100;
    }

    return 0;
}

/**
 * Get total working days in date range
 */
private function getWorkingDaysCount(Carbon $startDate, Carbon $endDate): int
{
    $workingDays = 0;
    $current = $startDate->copy();
    
    while ($current <= $endDate) {
        // !$current->isSaturday() &&
        if ( !$current->isSunday()) {
            $workingDays++;
        }
        $current->addDay();
    }
    
    return $workingDays;
}

/**
 * Get array of working days in date range (Monday to Friday only)
 */
private function getWorkingDaysInRange(Carbon $startDate, Carbon $endDate): array
{
    $days = [];
    $current = $startDate->copy();

    while ($current <= $endDate) {
        // Monday (1) to Friday (5) only, skip Saturday (6) and Sunday (0)
        // !$current->isSaturday() &&
        if ( !$current->isSunday()) {
            $days[] = $current->format('Y-m-d');
        }
        $current->addDay();
    }

    return $days;
}

/**
 * Keep old method for backward compatibility
 */
public function generateMonthlyReport($month = null)
{
    $month = $month ?? now('Asia/Dubai')->subMonth()->format('Y-m');
    $startDate = Carbon::parse($month . '-01');
    $endDate = $startDate->copy()->endOfMonth();
    
    $request = new Request([
        'start_date' => $startDate->format('Y-m-d'),
        'end_date' => $endDate->format('Y-m-d')
    ]);
    
    return $this->generatePeriodReport($request);
}
}
