<?php

namespace App\Http\Controllers\Api;

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

        $defaultDate = Carbon::today('Africa/Cairo')->toDateString();
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
                'employee_name' => $attendance->employee_name ?? $attendance->user?->name ?? 'Unknown',
                'status' => $attendance->status ?: $this->resolveStatus([
                    'status' => null,
                    'check_in' => $attendance->check_in,
                ]),
                'check_in' => $attendance->check_in,
                'check_out' => $attendance->check_out,
                'date' => $attendance->date ? Carbon::parse($attendance->date)->toDateString() : null,
                'department' => data_get($attendance->user, 'department'),
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
                    'user_id' => $user?->id,
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
            $checkIn = Carbon::parse($checkInRaw);
            $lateBoundary = Carbon::parse($checkIn->toDateString() . ' 09:15:00');

            return $checkIn->gt($lateBoundary) ? 'late' : 'present';
        } catch (\Throwable $e) {
            return 'present';
        }
    }
}
