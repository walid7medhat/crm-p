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
            ->with('user:id,name,email')
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

        $rows = $query->orderBy('employee_name')->get();
        $returnedCount = $rows->count();

        Log::info('Attendance GET (database)', [
            'date' => $targetDate,
            'returned_db_count' => $returnedCount,
            'status_filter' => $statusFilter,
        ]);

        $normalized = $rows->map(function (Attendance $attendance) {
            $row = [
                'employee_id' => $attendance->employee_id,
                'biometric_code'=>$attendance->user?->biometric_code,
                'employee_name' => $attendance->employee_name ?? $attendance->user?->name ?? 'Unknown',
                'status' => $attendance->status ?: $this->resolveStatus([
                    'status' => null,
                    'check_in' => $attendance->check_in,
                ]),
                'check_in' => $attendance->check_in?->timezone('Asia/Dubai')->toDateTimeString(),
                'check_out' => $attendance->check_out?->timezone('Asia/Dubai')->toDateTimeString(),
                'date' => $attendance->date ? Carbon::parse($attendance->date)->toDateString() : null,
                'department' => $attendance->user?->employeeProfile?->department?->name,
                'email' => $attendance->user?->email,
            ];

            return $row;
        })->values();

        $resolvedDate = (string) ($normalized->first()['date'] ?? $targetDate);

        $summary = [
            'total_employees' => $normalized->count(),
            'present_today' => $normalized->where('status', 'present')->count(),
            'absent_today' => $normalized->where('status', 'absent')->count(),
            'late_today' => $normalized->where('status', 'late')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $resolvedDate,
                'summary' => $summary,
                'employees' => $normalized,
            ],
        ]);
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
            $url = $baseUrl . '/attendance/today';

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
            if (!is_array($rows)) {
                return [];
            }

            $usersByBioCode = User::query()
                ->whereNotNull('biometric_code')
                ->with(['employeeProfile.department:id,name'])
                ->get(['id', 'name', 'email', 'biometric_code'])
                ->keyBy('biometric_code');

            $safeRows = collect($rows)->filter(fn ($row) => is_array($row))->values();
            $rowsForDate = $safeRows
                ->filter(fn ($row) => ($row['attendance_date'] ?? null) === $targetDate)
                ->values();

            if ($rowsForDate->isEmpty()) {
                return [];
            }

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
                    'biometric_code'=>$user?->biometric_code,
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
        } catch (\Throwable $e) {
            Log::warning('Attendance remote API exception', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
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

    // Get all active users
    $users = User::whereHas('attendances')->get();
    $reports = [];

    foreach ($users as $user) {
        $attendances = Attendance::where('user_id', $user->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get();

        $present = 0;
        $late = 0;
        $absent = 0;
        $totalDeductionPercent = 0;  // مجموع نسب الخصم لجميع الأيام
        $daysWithDeduction = 0;      // عدد الأيام التي تم فيها خصم (غياب + تأخير)

        $workingDays = $this->getWorkingDaysInRange($startDate, $endDate);
        $dailyBreakdown = [];

        // map attendance by date
        $attendanceMap = $attendances->keyBy(function ($a) {
            return Carbon::parse($a->date)->format('Y-m-d');
        });

        foreach ($workingDays as $date) {
            $attendance = $attendanceMap->get($date);
            
            $checkIn = null;
            $checkOut = null;
            $checkInTime = null;
            $checkOutTime = null;
            $dayDeductionPercent = 0;
            $status = '';
            
            if ($attendance) {
                if ($attendance->check_in) {
                    $checkIn = Carbon::parse($attendance->check_in)->timezone('Asia/Dubai');
                    $checkInTime = $checkIn->format('H:i:s');
                }
                if ($attendance->check_out) {
                    $checkOut = Carbon::parse($attendance->check_out)->timezone('Asia/Dubai');
                    $checkOutTime = $checkOut->format('H:i:s');
                }
                
                // حساب الخصم بناءً على وقت الحضور (لليوم فقط)
                $dayDeductionPercent = $this->calculateDayDeduction($checkIn);
                
                if ($dayDeductionPercent == 0) {
                    $status = 'Present';
                    $present++;
                } else {
                    $status = 'Late';
                    $late++;
                    $totalDeductionPercent += $dayDeductionPercent;
                    $daysWithDeduction++;
                }
            } else {
                $status = 'Absent';
                $absent++;
                $dayDeductionPercent = 100;
                $totalDeductionPercent += 100;
                $daysWithDeduction++;
            }

            // Build daily breakdown
            $dailyBreakdown[] = [
                'date' => $date,
                'check_in' => $checkInTime,
                'check_out' => $checkOutTime,
                'status' => $status,
                'deduction_percent' => $dayDeductionPercent,
            ];
        }

        // حساب متوسط الخصم اليومي (على الأيام التي تم خصم منها فقط)
        $avgDeductionPercent = $daysWithDeduction > 0 
            ? round($totalDeductionPercent / $daysWithDeduction, 2)
            : 0;
        
        // حساب إجمالي الخصم المئوي (على إجمالي أيام العمل)
        $totalWorkingDays = count($workingDays);
        $overallDeductionPercent = $totalWorkingDays > 0
            ? round(($totalDeductionPercent / $totalWorkingDays), 2)
            : 0;

        $reports[] = [
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'biometric_code'=>$user?->biometric_code,
            'department'=>$user?->employeeProfile?->department?->name,
            'employee_id' => $user->employee_id ?? null,
            'period_start' => $startDate->format('Y-m-d'),
            'period_end' => $endDate->format('Y-m-d'),
            'present' => $present,
            'late' => $late,
            'absent' => $absent,
            'total_working_days' => $totalWorkingDays,
            'avg_deduction_percent' => $avgDeductionPercent,      // متوسط الخصم في الأيام المخصومة
            'total_deduction_percent' => $overallDeductionPercent, // إجمالي الخصم على كل أيام العمل
            'total_deduction_sum' => $totalDeductionPercent,       // مجموع نسب الخصم (قد يتجاوز 100)
            'days_with_deduction' => $daysWithDeduction,
            'daily_breakdown' => $dailyBreakdown,
        ];
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
        if (!$current->isSaturday() && !$current->isSunday()) {
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
        if (!$current->isSaturday() && !$current->isSunday()) {
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
