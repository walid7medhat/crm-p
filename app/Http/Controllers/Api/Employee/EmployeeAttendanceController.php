<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EmployeeAttendanceController extends Controller
{
    /**
     * Display a listing of attendance records.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $query = Attendance::with(['user' => function($q) {
                $q->select('id', 'name', 'email', 'avatar', 'status');
            }]);

            // Filter by date
            if ($request->has('date')) {
                $query->whereDate('date', $request->date);
            }

            // Filter by date range
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('date', [$request->start_date, $request->end_date]);
            }

            // Filter by user
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by department
            if ($request->has('department')) {
                $query->whereHas('user.employeeProfile.department', function($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->department . '%');
                });
            }

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->whereHas('user', function($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhere('email', 'like', '%' . $search . '%');
                });
            }

            $perPage = $request->input('per_page', 15);
            $attendance = $query->orderBy('date', 'desc')
                               ->orderBy('created_at', 'desc')
                               ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $attendance
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attendance records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created attendance record.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'date' => 'required|date',
                'check_in' => 'nullable|date_format:H:i',
                'check_out' => 'nullable|date_format:H:i|after:check_in',
                'status' => 'required|in:present,absent,late,half_day',
                'attendance_type' => 'nullable|string|in:office,remote,visit,call,work_from_home,out_of_office',
                'break_duration' => 'nullable|integer|min:0',
                'overtime' => 'nullable|integer|min:0',
                'description' => 'nullable|string|max:500',
                'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check for duplicate attendance on the same date
            $existing = Attendance::where('user_id', $request->user_id)
                ->whereDate('date', $request->date)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attendance record already exists for this date'
                ], 422);
            }

            $data = $request->all();

            // Handle attachment upload
            if ($request->hasFile('attachment')) {
                $path = $request->file('attachment')->store("attendance/{$request->user_id}", 'public');
                $data['attachment'] = $path;
            }

            $attendance = Attendance::create($data);

            return response()->json([
                'success' => true,
                'data' => $attendance->load('user'),
                'message' => 'Attendance record created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create attendance record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified attendance record.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $attendance = Attendance::with(['user' => function($q) {
                $q->select('id', 'name', 'email', 'avatar', 'status');
            }])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $attendance
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance record not found'
            ], 404);
        }
    }

    /**
     * Update the specified attendance record.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $attendance = Attendance::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'date' => 'sometimes|required|date',
                'check_in' => 'nullable|date_format:H:i',
                'check_out' => 'nullable|date_format:H:i|after:check_in',
                'status' => 'sometimes|required|in:present,absent,late,half_day',
                'attendance_type' => 'nullable|string|in:office,remote,visit,call,work_from_home,out_of_office',
                'break_duration' => 'nullable|integer|min:0',
                'overtime' => 'nullable|integer|min:0',
                'description' => 'nullable|string|max:500',
                'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->all();

            // Handle attachment upload
            if ($request->hasFile('attachment')) {
                // Delete old attachment if exists
                if ($attendance->attachment) {
                    \Storage::disk('public')->delete($attendance->attachment);
                }
                $path = $request->file('attachment')->store("attendance/{$attendance->user_id}", 'public');
                $data['attachment'] = $path;
            }

            $attendance->update($data);

            return response()->json([
                'success' => true,
                'data' => $attendance->load('user'),
                'message' => 'Attendance record updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update attendance record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified attendance record.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $attendance = Attendance::findOrFail($id);

            // Delete attachment if exists
            if ($attendance->attachment) {
                \Storage::disk('public')->delete($attendance->attachment);
            }

            $attendance->delete();

            return response()->json([
                'success' => true,
                'message' => 'Attendance record deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete attendance record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get today's attendance for the authenticated user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function today(Request $request)
    {
        try {
            $user = Auth::user();
            $today = Carbon::now()->toDateString();

            $attendance = Attendance::where('user_id', $user->id)
                ->whereDate('date', $today)
                ->first();

            return response()->json([
                'success' => true,
                'data' => $attendance,
                'is_checked_in' => $attendance && $attendance->check_in && !$attendance->check_out,
                'is_checked_out' => $attendance && $attendance->check_in && $attendance->check_out,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch today\'s attendance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attendance summary for a specific date.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function summary(Request $request)
    {
        try {
            $date = $request->input('date', Carbon::now()->toDateString());

            // Get all active employees
            $employees = User::where('status', 'active')
                ->orWhere('status', 'on_leave')
                ->get();

            // Get attendance records for this date
            $attendanceRecords = Attendance::whereDate('date', $date)->get();

            $totalEmployees = $employees->count();
            $present = 0;
            $absent = 0;
            $late = 0;
            $onLeave = 0;
            $halfDay = 0;
            $holiday = 0;

            foreach ($employees as $employee) {
                $record = $attendanceRecords->where('user_id', $employee->id)->first();

                if ($record) {
                    $status = strtolower($record->status ?? '');

                    switch ($status) {
                        case 'present':
                            $present++;
                            break;
                        case 'absent':
                            $absent++;
                            break;
                        case 'late':
                            $late++;
                            break;
                        case 'half_day':
                            $halfDay++;
                            break;
                        default:
                            break;
                    }
                } else {
                    // If no attendance record, consider them absent
                    $absent++;
                }
            }

            // Count employees on leave
            $onLeave = $employees->where('status', 'on_leave')->count();

            // Check if it's a holiday
            $holiday = $this->checkHoliday($date) ? $totalEmployees : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_employees' => $totalEmployees,
                    'present' => $present,
                    'absent' => $absent,
                    'late' => $late,
                    'on_leave' => $onLeave,
                    'half_day' => $halfDay,
                    'holiday' => $holiday,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attendance summary: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get daily attendance statistics.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function dailyStats(Request $request)
    {
        try {
            $date = $request->input('date', Carbon::now()->toDateString());

            // Get attendance records for this date
            $attendanceRecords = Attendance::with('user')
                ->whereDate('date', $date)
                ->get();

            $checkInCount = 0;
            $checkOutCount = 0;
            $totalCheckInMinutes = 0;
            $totalCheckOutMinutes = 0;
            $earlyCheckIns = 0;
            $lateCheckIns = 0;

            $companyCheckInTime = '09:00'; // Default company check-in time
            $companyCheckInMinutes = 9 * 60; // 9:00 AM in minutes

            foreach ($attendanceRecords as $record) {
                if ($record->check_in) {
                    $checkInCount++;
                    $checkInTime = Carbon::parse($record->check_in);
                    $checkInMinutes = $checkInTime->hour * 60 + $checkInTime->minute;
                    $totalCheckInMinutes += $checkInMinutes;

                    // Check if early or late
                    if ($checkInMinutes <= $companyCheckInMinutes) {
                        $earlyCheckIns++;
                    } else {
                        $lateCheckIns++;
                    }
                }

                if ($record->check_out) {
                    $checkOutCount++;
                    $checkOutTime = Carbon::parse($record->check_out);
                    $totalCheckOutMinutes += $checkOutTime->hour * 60 + $checkOutTime->minute;
                }
            }

            // Calculate average check-in time
            $averageCheckIn = '--';
            if ($checkInCount > 0) {
                $avgMinutes = round($totalCheckInMinutes / $checkInCount);
                $hours = floor($avgMinutes / 60);
                $minutes = $avgMinutes % 60;
                $averageCheckIn = sprintf('%02d:%02d', $hours, $minutes);
            }

            // Calculate average check-out time
            $averageCheckOut = '--';
            if ($checkOutCount > 0) {
                $avgMinutes = round($totalCheckOutMinutes / $checkOutCount);
                $hours = floor($avgMinutes / 60);
                $minutes = $avgMinutes % 60;
                $averageCheckOut = sprintf('%02d:%02d', $hours, $minutes);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'check_in_count' => $checkInCount,
                    'check_out_count' => $checkOutCount,
                    'average_check_in' => $averageCheckIn,
                    'average_check_out' => $averageCheckOut,
                    'early_check_ins' => $earlyCheckIns,
                    'late_check_ins' => $lateCheckIns,
                    'total_records' => $attendanceRecords->count(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch daily stats: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if a date is a holiday.
     *
     * @param string $date
     * @return bool
     */
    private function checkHoliday($date)
    {
        $carbonDate = Carbon::parse($date);
        $dayOfWeek = $carbonDate->dayOfWeek;

        // Friday = 5, Saturday = 6 (Carbon: Sunday = 0)
        if ($dayOfWeek == 5 || $dayOfWeek == 6) {
            return true;
        }

        // You can add a Holiday model check here
        // return Holiday::where('date', $date)->exists();

        return false;
    }

    /**
     * Get attendance history for the authenticated user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function myAttendanceHistory(Request $request)
    {
        try {
            $user = Auth::user();
            $months = $request->input('months', 6);

            $startDate = Carbon::now()->subMonths($months);

            $attendance = Attendance::where('user_id', $user->id)
                ->where('date', '>=', $startDate)
                ->orderBy('date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $attendance
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attendance history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attendance history for a specific user (HR/Admin only).
     *
     * @param Request $request
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function userAttendanceHistory(Request $request, $userId)
    {
        try {
            // Check if user has permission
            if (!Auth::user()->hasRole('super_admin') && !Auth::user()->hasRole('hr')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view this user\'s attendance'
                ], 403);
            }

            $months = $request->input('months', 6);
            $startDate = Carbon::now()->subMonths($months);

            $attendance = Attendance::where('user_id', $userId)
                ->where('date', '>=', $startDate)
                ->orderBy('date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $attendance
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user attendance history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attendance statistics for the dashboard.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistics(Request $request)
    {
        try {
            $date = $request->input('date', Carbon::now()->toDateString());
            $month = $request->input('month', Carbon::now()->format('Y-m'));

            // Today's statistics
            $todayStats = [
                'total' => Attendance::whereDate('date', $date)->count(),
                'present' => Attendance::whereDate('date', $date)->where('status', 'present')->count(),
                'absent' => Attendance::whereDate('date', $date)->where('status', 'absent')->count(),
                'late' => Attendance::whereDate('date', $date)->where('status', 'late')->count(),
                'half_day' => Attendance::whereDate('date', $date)->where('status', 'half_day')->count(),
            ];

            // Monthly statistics
            $monthlyStats = Attendance::select(
                DB::raw('DATE(date) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present'),
                DB::raw('SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent'),
                DB::raw('SUM(CASE WHEN status = "late" THEN 1 ELSE 0 END) as late'),
                DB::raw('SUM(CASE WHEN status = "half_day" THEN 1 ELSE 0 END) as half_day')
            )
            ->whereYear('date', Carbon::parse($month)->year)
            ->whereMonth('date', Carbon::parse($month)->month)
            ->groupBy('date')
            ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'today' => $todayStats,
                    'monthly' => $monthlyStats,
                    'summary' => $this->summary($request)->getData(true),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attendance statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export attendance records.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export(Request $request)
    {
        try {
            $query = Attendance::with('user');

            if ($request->has('date')) {
                $query->whereDate('date', $request->date);
            }

            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('date', [$request->start_date, $request->end_date]);
            }

            $records = $query->get();

            // Generate CSV
            $filename = 'attendance_' . Carbon::now()->format('Y-m-d_H-i-s') . '.csv';
            $path = storage_path('app/exports/' . $filename);

            // Create directory if not exists
            if (!is_dir(storage_path('app/exports'))) {
                mkdir(storage_path('app/exports'), 0777, true);
            }

            $file = fopen($path, 'w');
            fputcsv($file, [
                'Date',
                'Employee Name',
                'Employee Email',
                'Check In',
                'Check Out',
                'Status',
                'Attendance Type',
                'Break Duration',
                'Overtime',
                'Description'
            ]);

            foreach ($records as $record) {
                fputcsv($file, [
                    $record->date,
                    $record->user->name ?? 'N/A',
                    $record->user->email ?? 'N/A',
                    $record->check_in ?? '--',
                    $record->check_out ?? '--',
                    $record->status ?? '--',
                    $record->attendance_type ?? 'office',
                    $record->break_duration ?? 0 . ' min',
                    $record->overtime ?? 0 . ' min',
                    $record->description ?? '--'
                ]);
            }

            fclose($file);

            return response()->download($path, $filename)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export attendance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate monthly report for attendance.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateMonthlyReport(Request $request)
    {
        try {
            $month = $request->input('month', Carbon::now()->format('Y-m'));
            $startDate = Carbon::parse($month)->startOfMonth();
            $endDate = Carbon::parse($month)->endOfMonth();

            $attendance = Attendance::with('user')
                ->whereBetween('date', [$startDate, $endDate])
                ->get();

            $report = [];
            foreach ($attendance as $record) {
                $userId = $record->user_id;
                if (!isset($report[$userId])) {
                    $report[$userId] = [
                        'user' => $record->user,
                        'total_days' => 0,
                        'present' => 0,
                        'absent' => 0,
                        'late' => 0,
                        'half_day' => 0,
                        'total_hours' => 0,
                    ];
                }

                $report[$userId]['total_days']++;
                $status = strtolower($record->status ?? '');
                if ($status === 'present') $report[$userId]['present']++;
                elseif ($status === 'absent') $report[$userId]['absent']++;
                elseif ($status === 'late') $report[$userId]['late']++;
                elseif ($status === 'half_day') $report[$userId]['half_day']++;

                // Calculate hours if check_in and check_out exist
                if ($record->check_in && $record->check_out) {
                    $checkIn = Carbon::parse($record->check_in);
                    $checkOut = Carbon::parse($record->check_out);
                    $hours = $checkIn->diffInHours($checkOut);
                    $report[$userId]['total_hours'] += $hours;
                }
            }

            return response()->json([
                'success' => true,
                'data' => array_values($report),
                'meta' => [
                    'month' => $month,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate monthly report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync attendance from last month (for missing records).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncLastMonth(Request $request)
    {
        try {
            $lastMonth = Carbon::now()->subMonth();
            $startDate = $lastMonth->startOfMonth();
            $endDate = $lastMonth->endOfMonth();

            // Get all active employees
            $employees = User::where('status', 'active')->get();

            $created = 0;
            $skipped = 0;

            foreach ($employees as $employee) {
                $date = clone $startDate;
                while ($date <= $endDate) {
                    // Skip weekends (Friday and Saturday)
                    if ($date->dayOfWeek != 5 && $date->dayOfWeek != 6) {
                        $exists = Attendance::where('user_id', $employee->id)
                            ->whereDate('date', $date)
                            ->exists();

                        if (!$exists) {
                            Attendance::create([
                                'user_id' => $employee->id,
                                'date' => $date->toDateString(),
                                'status' => 'absent',
                                'attendance_type' => 'office',
                            ]);
                            $created++;
                        } else {
                            $skipped++;
                        }
                    }
                    $date->addDay();
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Attendance sync completed",
                'data' => [
                    'created' => $created,
                    'skipped' => $skipped,
                    'month' => $lastMonth->format('Y-m'),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync attendance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate period report for attendance.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generatePeriodReport(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);

            $attendance = Attendance::with('user')
                ->whereBetween('date', [$startDate, $endDate])
                ->get();

            // Group by user
            $report = [];
            foreach ($attendance as $record) {
                $userId = $record->user_id;
                if (!isset($report[$userId])) {
                    $report[$userId] = [
                        'user' => $record->user,
                        'total_days' => 0,
                        'present' => 0,
                        'absent' => 0,
                        'late' => 0,
                        'half_day' => 0,
                        'total_hours' => 0,
                        'attendance_records' => [],
                    ];
                }

                $report[$userId]['total_days']++;
                $status = strtolower($record->status ?? '');
                if ($status === 'present') $report[$userId]['present']++;
                elseif ($status === 'absent') $report[$userId]['absent']++;
                elseif ($status === 'late') $report[$userId]['late']++;
                elseif ($status === 'half_day') $report[$userId]['half_day']++;

                // Calculate hours
                if ($record->check_in && $record->check_out) {
                    $checkIn = Carbon::parse($record->check_in);
                    $checkOut = Carbon::parse($record->check_out);
                    $hours = $checkIn->diffInHours($checkOut);
                    $report[$userId]['total_hours'] += $hours;
                }

                $report[$userId]['attendance_records'][] = $record;
            }

            return response()->json([
                'success' => true,
                'data' => array_values($report),
                'meta' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'total_days' => $startDate->diffInDays($endDate) + 1,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate period report: ' . $e->getMessage()
            ], 500);
        }
    }
}