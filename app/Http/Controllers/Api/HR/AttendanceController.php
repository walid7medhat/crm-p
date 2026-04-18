<?php

namespace App\Http\Controllers\Api\HR;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller
{
    public function today(Request $request)
    {
        return $this->respondWithAttendance($request, true);
    }

    public function index(Request $request)
    {
        return $this->respondWithAttendance($request, false);
    }

   private function respondWithAttendance(Request $request, bool $todayOnly)
{
    $date = $request->query('date');
    $statusFilter = strtolower((string) $request->query('status', 'all'));
    $targetDate = $todayOnly
        ? Carbon::today('Asia/Dubai')->toDateString()
        : ($date ?: Carbon::today('Asia/Dubai')->toDateString());
  $attendanceRows = $this->fetchFromLocalSnapshot($targetDate);

    $normalized = collect($attendanceRows)
        ->map(function (array $row) {
            $status = $this->resolveStatus($row);
            return [
                'employee_id' => $row['employee_id'] ?? $row['user_id'] ?? null,
                'employee_name' => $row['employee_name'] ?? 'Unknown',
                'status' => $status,
                'check_in' => $row['check_in'] ?? null,
                'check_out' => $row['check_out'] ?? null,
                'date' => $row['date'] ?? null,
                'department' => $row['department'] ?? null,
                'email' => $row['email'] ?? null,
            ];
        })
        ->when(in_array($statusFilter, ['present', 'absent', 'late'], true), function ($collection) use ($statusFilter) {
            return $collection->where('status', $statusFilter)->values();
        })
        ->values();

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
            'employees' => $normalized,  // ← تأكد من أن هذا هو المفتاح المستخدم
        ],
    ]);
}

    private function fetchFromRemoteApi(string $targetDate): ?array
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
                return null;
            }

            $rows = $response->json();
            if (!is_array($rows)) return null;

            $usersByBioCode = User::query()
                ->whereNotNull('biometric_code')
                ->get(['id', 'name', 'email', 'biometric_code'])
                ->keyBy('biometric_code');

            $safeRows = collect($rows)->filter(fn ($row) => is_array($row))->values();
            $rowsForDate = $safeRows
                ->filter(fn ($row) => ($row['attendance_date'] ?? null) === $targetDate)
                ->values();

            // Some integrations lag/lead by date; if target day is missing, show latest available.
            if ($rowsForDate->isEmpty()) {
                $latestAvailableDate = $safeRows
                    ->pluck('attendance_date')
                    ->filter()
                    ->sort()
                    ->last();

                if ($latestAvailableDate) {
                    $rowsForDate = $safeRows
                        ->filter(fn ($row) => ($row['attendance_date'] ?? null) === $latestAvailableDate)
                        ->values();
                }
            }

            $mapped = [];
            foreach ($rowsForDate as $row) {
                $bioCode = (string) ($row['emp_code'] ?? '');
                $user = $usersByBioCode->get($bioCode);

                $fallbackName = trim(
                    (string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? '')
                );

                $mapped[] = [
                    'employee_id' => $user?->id ?? $bioCode,
                    'employee_name' => $user?->name ?: ($fallbackName ?: ($bioCode ?: 'Unknown')),
                    'email' => $user?->email,
                    'department' => data_get($user, 'department') ?: ($row['department'] ?? null),
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
            return null;
        }
    }

  private function fetchFromLocalSnapshot(string $targetDate): array
{
    $users = User::query()
        ->get(['id', 'name', 'email', 'biometric_code']);

    $usersByNormBio = $users
        ->filter(fn ($u) => !empty($u->biometric_code))
        ->keyBy(fn ($u) => Attendance::normalizeEmployeeId((string) $u->biometric_code));

    $usersById = $users->keyBy('id');

    return Attendance::query()
        ->with('user:id,name,email,biometric_code')
        ->whereDate('date', $targetDate)
        ->get()
        ->map(function (Attendance $attendance) use ($usersByNormBio, $usersById) {
            $normEmployeeId = Attendance::normalizeEmployeeId((string) ($attendance->employee_id ?? ''));
            $matchedByBio = $normEmployeeId ? $usersByNormBio->get($normEmployeeId) : null;
            $matchedByUserId = $attendance->user_id ? $usersById->get($attendance->user_id) : null;
            $resolvedUser = $attendance->user ?: $matchedByBio ?: $matchedByUserId;

            $name = trim((string) ($attendance->employee_name ?? ''));
            if ($name === '' || strcasecmp($name, 'unknown') === 0) {
                $name = (string) ($resolvedUser?->name ?: '');
            }
            if ($name === '') {
                $name = $normEmployeeId ?: ('USER-' . ($attendance->user_id ?? $attendance->id));
            }

            return [
                'employee_id' => $normEmployeeId ?: ($attendance->employee_id ?? $attendance->user_id),
                'employee_name' => $name,
                'email' => $resolvedUser?->email,
                'department' => data_get($resolvedUser, 'department'),
                'status' => $attendance->status, // use stored status first, fallback handled in resolveStatus
                'check_in' => $attendance->check_in,
                'check_out' => $attendance->check_out,
                'date' => $attendance->date,
            ];
        })
        ->all();
}

    private function resolveStatus(array $row): string
    {
        $status = strtolower((string) ($row['status'] ?? ''));
        if (in_array($status, ['present', 'absent', 'late'], true)) return $status;

        $checkInRaw = $row['check_in'] ?? null;
        if (!$checkInRaw) return 'absent';

        try {
            $checkIn = Carbon::parse($checkInRaw);
            $lateBoundary = Carbon::parse($checkIn->toDateString() . ' 09:15:00');
            return $checkIn->gt($lateBoundary) ? 'late' : 'present';
        } catch (\Throwable $e) {
            return 'present';
        }
    }
}

