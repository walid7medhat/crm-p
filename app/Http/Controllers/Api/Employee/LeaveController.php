<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\LeaveBalance;
use App\Helpers\ApiResponse;
use App\Notifications\LeaveRequestNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class LeaveController extends Controller
{
    // ==================== Leave Types CRUD ====================
    
    public function getLeaveTypes(Request $request)
    {
        try {
            $types = LeaveType::query();
            if (!$request->boolean('all')) {
                $types->where('is_active', true);
            }
          if ($request->has('search')) {
                $types->where('name', 'like', '%' . $request->search . '%');
            }
            $types=$types->orderBy('sort_order')->get();
            return ApiResponse::success($types, 'Leave types retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve leave types: ' . $e->getMessage());
        }
    }
    
    public function storeLeaveType(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:leave_types,name',
            'payment_type' => 'required|in:paid,half_paid,unpaid',
            'default_days' => 'required|integer|min:0',
            'requires_attachment' => 'boolean',
        ]);
        
        try {
            $type = LeaveType::create([
                'name' => $request->name,
                'slug' => str()->slug($request->name),
                'payment_type' => $request->payment_type,
                'default_days' => $request->default_days,
                'requires_attachment' => $request->requires_attachment ?? false,
                'is_active' => true,
                'sort_order' => LeaveType::max('sort_order') + 1,
            ]);
            
            return ApiResponse::success($type, 'Leave type created successfully', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create leave type: ' . $e->getMessage());
        }
    }
    
    public function updateLeaveType(Request $request, $id)
    {
        $request->validate([
            'name' => 'sometimes|string|max:100|unique:leave_types,name,' . $id,
            'payment_type' => 'sometimes|in:paid,half_paid,unpaid',
            'default_days' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ]);
        
        try {
            $type = LeaveType::findOrFail($id);
            $type->update($request->all());
            
            return ApiResponse::success($type, 'Leave type updated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update leave type: ' . $e->getMessage());
        }
    }
    
    public function destroyLeaveType($id)
    {
        try {
            $type = LeaveType::findOrFail($id);
            
            if ($type->leaveRequests()->count() > 0) {
                return ApiResponse::error('Cannot delete: This leave type has existing requests', 422);
            }
            
            $type->delete();
            return ApiResponse::success(null, 'Leave type deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete leave type: ' . $e->getMessage());
        }
    }
    
    // ==================== Leave Balance ====================
    
    public function getMyBalance()
    {
        try {
            $userId = Auth::id();
            $currentYear = date('Y');
            
            $balances = LeaveBalance::with('leaveType')
                ->where('user_id', $userId)
                ->where('year', $currentYear)
                ->get();
              if ($balances->isEmpty()) {
                $this->initializeBalance($userId);
                $balances = LeaveBalance::with('leaveType')
                    ->where('user_id', $userId)
                    ->where('year', $currentYear)
                    ->get();
            }
            return ApiResponse::success($balances, 'Leave balance retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve balance: ' . $e->getMessage());
        }
    }
    
    public function getEmployeeBalance($userId)
    {
        try {
            $currentYear = date('Y');

            $balances = LeaveBalance::with('leaveType')
                ->where('user_id', $userId)
                ->where('year', $currentYear)
                ->get();

            return ApiResponse::success($balances, 'Employee leave balance retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve balance: ' . $e->getMessage());
        }
    }

    // HR/Admin manually adjusts an employee's balance for one leave type.
    public function updateEmployeeBalance(Request $request, $userId, $leaveTypeId)
    {
        $user = Auth::user();
        if (!$user->hasRole('super_admin') && !$user->hasRole('hr')) {
            return ApiResponse::error('Only HR can update leave balances', 403);
        }

        $request->validate([
            'total_days' => 'required|numeric|min:0|max:9999',
            'used_days' => 'nullable|numeric|min:0|max:9999',
        ]);

        try {
            $year = date('Y');

            $balance = LeaveBalance::firstOrNew([
                'user_id' => $userId,
                'leave_type_id' => $leaveTypeId,
                'year' => $year,
            ]);

            $balance->total_days = $request->total_days;
            $balance->used_days = $request->filled('used_days') ? $request->used_days : ($balance->used_days ?? 0);
            $balance->remaining_days = max(0, $balance->total_days - $balance->used_days);
            $balance->save();

            return ApiResponse::success($balance->load('leaveType'), 'Leave balance updated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update leave balance: ' . $e->getMessage());
        }
    }
    
    // Initialize leave balance for new employee
     public function initializeBalance($userId)
    {
        $currentYear = date('Y');
        
        $basicLeaveTypes = ['Annual Leave - Paid Leave', 'Sick Leave - Fully Paid'];

        $leaveTypes = LeaveType::whereIn('name', $basicLeaveTypes)
            ->where('is_active', true)
            ->get();

        foreach ($leaveTypes as $type) {
            // Annual leave accrues over time (see AccrueAnnualLeave command) rather than
            // being granted in full at hire, so it starts at 0 here.
            $initialDays = $type->name === 'Annual Leave - Paid Leave' ? 0 : $type->default_days;

            LeaveBalance::updateOrCreate(
                [
                    'user_id' => $userId,
                    'leave_type_id' => $type->id,
                    'year' => $currentYear,
                ],
                [
                    'total_days' => $initialDays,
                    'used_days' => 0,
                    'remaining_days' => $initialDays,
                ]
            );
        }
    }

    // ==================== Leave Requests ====================
    
    public function index(Request $request)
{
    try {
        $user = Auth::user();
        $query = LeaveRequest::with(['user', 'leaveType', 'parent', 'hr']);
        
        if ($user->hasRole('super_admin')) {
        }
        elseif ($user->hasRole('hr')) {
            $query->whereIn('status', ['pending_hr', 'approved', 'rejected']);
        }
        elseif ($user->hasRole('team_lead') ) {
            $employeeIds = User::where('parent_id', $user->id)
               
                ->pluck('id')
                ->toArray();
            
            $employeeIds[] = $user->id;
            
            $query->whereIn('user_id', $employeeIds);
            
            // $query->where('status', 'pending_parent');
        }
        else {
            $query->where('user_id', $user->id);
        }
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by leave type
        if ($request->has('leave_type_id')) {
            $query->where('leave_type_id', $request->leave_type_id);
        }
        
        // Filter by employee (للـ HR و Super Admin بس)
        if ($request->has('user_id') && ($user->hasRole('super_admin') || $user->hasRole('hr'))) {
            $query->where('user_id', $request->user_id);
        }
        
        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('start_date', [$request->start_date, $request->end_date]);
        }
        
        $leaveRequests = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);
        
        return ApiResponse::success($leaveRequests, 'Leave requests retrieved successfully');
    } catch (\Exception $e) {
        return ApiResponse::error('Failed to retrieve leave requests: ' . $e->getMessage());
    }
}
    
    public function store(Request $request)
    {
        $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string',
            'is_half_day' => 'boolean',
            'half_day_type' => 'required_if:is_half_day,true|in:morning,afternoon',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);
        
        try {
            DB::beginTransaction();
            
            $user =User::find($request->user_id)?? Auth::user();
            $employeeProfile = $user->employeeProfile;

            // Check if employee has completed 6 months
            $joiningDate = $employeeProfile->joining_date ?? null;
            if (!$joiningDate || Carbon::parse($joiningDate)->diffInMonths(now()) < 6) {
                return ApiResponse::error('You are not eligible for leave. You must complete 6 months of service.', 422);
            }

            $leaveType = LeaveType::find($request->leave_type_id);
            $requestStartDate = $request->start_date;
            $requestEndDate = $request->end_date;

            // Compensation Off: single day only, and can't fall on a Saturday or Monday
            if ($leaveType && $leaveType->name === 'Compensation Off') {
                $requestEndDate = $requestStartDate;
                $compOffDate = Carbon::parse($requestStartDate);
                if ($compOffDate->isSaturday() || $compOffDate->isMonday()) {
                    return ApiResponse::error('Compensation Off cannot be requested for a Saturday or a Monday (' . $compOffDate->format('l, d M Y') . ').', 422);
                }
            }

            // Sick Leave - Half Paid: cannot be applied for after 2:00 PM
            if ($leaveType && $leaveType->name === 'Sick Leave - Half Paid' && $request->is_half_day) {
                if (now()->format('H:i') >= '14:00') {
                    return ApiResponse::error('Half-day sick leave cannot be requested after 2:00 PM.', 422);
                }
            }

            // Sick leave tiers must be exhausted in order: Fully Paid -> Half Paid -> Unpaid
            if ($leaveType && in_array($leaveType->name, ['Sick Leave - Half Paid', 'Sick Leave - Unpaid'])) {
                $sickBalances = LeaveBalance::where('user_id', $user->id)
                    ->where('year', date('Y'))
                    ->whereHas('leaveType', function ($q) {
                        $q->whereIn('name', ['Sick Leave - Fully Paid', 'Sick Leave - Half Paid']);
                    })
                    ->with('leaveType')
                    ->get()
                    ->keyBy(fn ($b) => $b->leaveType->name);

                $fullyPaidRemaining = (float) ($sickBalances->get('Sick Leave - Fully Paid')->remaining_days ?? 0);
                if ($fullyPaidRemaining > 0) {
                    return ApiResponse::error('You must use up your Sick Leave - Fully Paid balance before applying for ' . $leaveType->name . '.', 422);
                }

                if ($leaveType->name === 'Sick Leave - Unpaid') {
                    $halfPaidRemaining = (float) ($sickBalances->get('Sick Leave - Half Paid')->remaining_days ?? 0);
                    if ($halfPaidRemaining > 0) {
                        return ApiResponse::error('You must use up your Sick Leave - Half Paid balance before applying for Sick Leave - Unpaid.', 422);
                    }
                }
            }

            // Calculate days
            $startDate = Carbon::parse($requestStartDate);
            $endDate = Carbon::parse($requestEndDate);
            $days = $startDate->diffInDays($endDate) + 1;

            if ($request->is_half_day) {
                $days = 0.5;
            }

            // Check balance
            $balance = LeaveBalance::where('user_id', $user->id)
                ->where('leave_type_id', $request->leave_type_id)
                ->where('year', date('Y'))
                ->first();

            if ($balance && !$balance->hasEnoughBalance($days)) {
                return ApiResponse::error('Insufficient leave balance', 422);
            }

            // Upload attachment if any
            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')->store("leave_attachments/{$user->id}", 'public');
            }
            
            // Get parent (line manager)
            $parentId = $user->parent_id ;
            
            // Create leave request
            $leaveRequest = LeaveRequest::create([
                'user_id' => $user->id,
                'leave_type_id' => $request->leave_type_id,
                'parent_id' => $parentId,
                'start_date' => $requestStartDate,
                'end_date' => $requestEndDate,
                'days' => $days,
                'is_half_day' => $request->is_half_day ?? false,
                'half_day_type' => $request->half_day_type,
                'reason' => $request->reason,
                'attachment' => $attachmentPath,
                'status' => 'pending_parent',
            ]);
            
            // Send notification to parent
            if ($parentId) {
                $parent = User::find($parentId);
                if ($parent) {
                    $parent->notify(new LeaveRequestNotification($leaveRequest, 'parent'));
                }
            }
            
            $hrUsers = User::role(['super_admin'])->get();
            
            foreach ($hrUsers as $hrUser) {
                $hrUser->notify(new LeaveRequestNotification($leaveRequest,'parent'));
            }
            DB::commit();
            
            return ApiResponse::success(
                $leaveRequest->load(['user', 'leaveType', 'parent']),
                'Leave request submitted successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to submit leave request: ' . $e->getMessage());
        }
    }
    
    public function show($id)
    {
        try {
            $user = Auth::user();
            $leaveRequest = LeaveRequest::with(['user', 'leaveType', 'parent', 'hr'])->findOrFail($id);
            
            if (!$user->hasRole('super_admin') && !$user->hasRole('hr') && $leaveRequest->user_id !== $user->id && $leaveRequest->parent_id !== $user->id) {
                return ApiResponse::error('Access denied', 403);
            }
            
            if ($leaveRequest->attachment) {
                $leaveRequest->attachment_url = asset('storage/' . $leaveRequest->attachment);
            }
            
            return ApiResponse::success($leaveRequest, 'Leave request retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Leave request not found', 404);
        }
    }
    
    public function update(Request $request, $id)
    {
        $request->validate([
            'leave_type_id' => 'sometimes|exists:leave_types,id',
            'start_date' => 'sometimes|date|after_or_equal:today',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'reason' => 'nullable|string',
            'is_half_day' => 'boolean',
            'half_day_type' => 'required_if:is_half_day,true|in:morning,afternoon',
        ]);
        
        try {
            DB::beginTransaction();
            
            $leaveRequest = LeaveRequest::findOrFail($id);
            $user = Auth::user();
            
            // Only employee can edit his pending request
            if ($leaveRequest->user_id !== $user->id) {
                return ApiResponse::error('You can only edit your own requests', 403);
            }
            
            if (!$leaveRequest->canEdit()) {
                return ApiResponse::error('You cannot edit this request as it has already been processed', 422);
            }
            
            // Recalculate days if dates changed
            $days = $leaveRequest->days;
            if ($request->has('start_date') || $request->has('end_date')) {
                $startDate = Carbon::parse($request->start_date ?? $leaveRequest->start_date);
                $endDate = Carbon::parse($request->end_date ?? $leaveRequest->end_date);
                $days = $startDate->diffInDays($endDate) + 1;
                
                if ($request->has('is_half_day') && $request->is_half_day) {
                    $days = 0.5;
                } elseif ($leaveRequest->is_half_day && !$request->has('is_half_day')) {
                    $days = $days;
                } elseif ($request->has('is_half_day') && $request->is_half_day) {
                    $days = 0.5;
                }
            }
            
            $leaveRequest->update([
                'leave_type_id' => $request->leave_type_id ?? $leaveRequest->leave_type_id,
                'start_date' => $request->start_date ?? $leaveRequest->start_date,
                'end_date' => $request->end_date ?? $leaveRequest->end_date,
                'days' => $days,
                'is_half_day' => $request->is_half_day ?? $leaveRequest->is_half_day,
                'half_day_type' => $request->half_day_type ?? $leaveRequest->half_day_type,
                'reason' => $request->reason ?? $leaveRequest->reason,
            ]);
            
            DB::commit();
            
            return ApiResponse::success(
                $leaveRequest->load(['user', 'leaveType']),
                'Leave request updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to update leave request: ' . $e->getMessage());
        }
    }
    
    public function approveByParent($id)
    {
        try {
            DB::beginTransaction();
            
            $leaveRequest = LeaveRequest::findOrFail($id);
            $user = Auth::user();
            
            // Check if user is the parent
            if ($leaveRequest->parent_id !== $user->id && !$user->hasRole('super_admin')) {
                return ApiResponse::error('You are not authorized to approve this request', 403);
            }
            
            if (!$leaveRequest->isPendingParent()) {
                return ApiResponse::error('This request cannot be approved at this stage', 422);
            }
            
            $leaveRequest->approveByParent();
            
            // Send notification to HR
            $hrUsers = \App\Models\User::role(['hr','super_admin'])->get();
            foreach ($hrUsers as $hr) {
                $hr->notify(new LeaveRequestNotification($leaveRequest, 'hr'));
            }
            
            DB::commit();
            
            return ApiResponse::success($leaveRequest, 'Leave request approved by parent and sent to HR');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to approve: ' . $e->getMessage());
        }
    }
    
    public function rejectByParent(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'required|string|min:4|max:500',
        ]);
        
        try {
            DB::beginTransaction();
            
            $leaveRequest = LeaveRequest::findOrFail($id);
            $user = Auth::user();
            
            if ($leaveRequest->parent_id !== $user->id && !$user->hasRole('super_admin')) {
                return ApiResponse::error('You are not authorized to reject this request', 403);
            }
            
            if (!$leaveRequest->isPendingParent()) {
                return ApiResponse::error('This request cannot be rejected at this stage', 422);
            }
            
            $leaveRequest->rejectByParent($request->rejection_reason);
                    $leaveRequest->user->notify(new LeaveRequestNotification($leaveRequest, 'employee_rejected'));
                // Send notification to HR
            $hrUsers = \App\Models\User::role(['hr','super_admin'])->get();
            foreach ($hrUsers as $hr) {
                $hr->notify(new LeaveRequestNotification($leaveRequest, 'hr_rejected_by_parent'));
            }
            
            DB::commit();
            
            return ApiResponse::success($leaveRequest, 'Leave request rejected by parent');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to reject: ' . $e->getMessage());
        }
    }
    
    public function approveByHr($id)
    {
        try {
            DB::beginTransaction();
            
            $leaveRequest = LeaveRequest::findOrFail($id);
            $user = Auth::user();
            
            if (!$user->hasRole('super_admin') && !$user->hasRole('hr')) {
                return ApiResponse::error('Only HR can approve this request', 403);
            }
            
            if (!$leaveRequest->isPendingHr()) {
                return ApiResponse::error('This request cannot be approved at this stage', 422);
            }
            
            $leaveRequest->approveByHr();
             $this->updateLeaveBalance($leaveRequest);
             $leaveRequest->user->notify(new LeaveRequestNotification($leaveRequest, 'employee_approved'));
            // NEW — let the parent/manager know HR finalized it
            if ($leaveRequest->parent_id) {
                $parent = User::find($leaveRequest->parent_id);
                if ($parent) {
                    $parent->notify(new LeaveRequestNotification($leaveRequest, 'parent_approved'));
                }
            }
            DB::commit();
            
            return ApiResponse::success($leaveRequest, 'Leave request approved by HR successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to approve: ' . $e->getMessage());
        }
    }
    
    public function rejectByHr(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'required|string|min:4|max:500',
        ]);
        
        try {
            DB::beginTransaction();
            
            $leaveRequest = LeaveRequest::findOrFail($id);
            $user = Auth::user();
            
            if (!$user->hasRole('super_admin') && !$user->hasRole('hr')) {
                return ApiResponse::error('Only HR can reject this request', 403);
            }
            
            if (!$leaveRequest->isPendingHr()) {
                return ApiResponse::error('This request cannot be rejected at this stage', 422);
            }
            
            $leaveRequest->rejectByHr($request->rejection_reason);
            $leaveRequest->user->notify(new LeaveRequestNotification($leaveRequest, 'employee_rejected'));
            // NEW — let the parent/manager know HR finalized it
            if ($leaveRequest->parent_id) {
                $parent = User::find($leaveRequest->parent_id);
                if ($parent) {
                    $parent->notify(new LeaveRequestNotification($leaveRequest, 'parent_rejected'));
                }
            }
            DB::commit();
            
            return ApiResponse::success($leaveRequest, 'Leave request rejected by HR');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to reject: ' . $e->getMessage());
        }
    }
    
    public function cancel($id)
    {
        try {
            $leaveRequest = LeaveRequest::findOrFail($id);
            $user = Auth::user();
            
            if ($leaveRequest->user_id !== $user->id && !$user->hasRole('super_admin')) {
                return ApiResponse::error('You can only cancel your own requests', 403);
            }
            
            if (!$leaveRequest->cancel()) {
                return ApiResponse::error('Cannot cancel this request as it has already been processed', 422);
            }
            
            return ApiResponse::success(null, 'Leave request cancelled successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to cancel: ' . $e->getMessage());
        }
    }
    
    public function statistics()
    {
        try {
            $user = Auth::user();
            
            if ($user->hasRole('super_admin') || $user->hasRole('hr')) {
                $stats = [
                    'total_requests' => LeaveRequest::count(),
                    'pending_parent' => LeaveRequest::where('status', 'pending_parent')->count(),
                    'pending_hr' => LeaveRequest::where('status', 'pending_hr')->count(),
                    'approved' => LeaveRequest::where('status', 'approved')->count(),
                    'rejected' => LeaveRequest::where('status', 'rejected')->count(),
                    'by_type' => LeaveRequest::select('leave_types.name', DB::raw('count(*) as count'))
                        ->join('leave_types', 'leave_requests.leave_type_id', '=', 'leave_types.id')
                        ->groupBy('leave_types.name')
                        ->get(),
                    'this_month' => LeaveRequest::whereMonth('created_at', date('m'))->count(),
                ];
            } else {
                $stats = [
                    'total_requests' => LeaveRequest::where('user_id', $user->id)->count(),
                    'pending' => LeaveRequest::where('user_id', $user->id)->where('status', 'pending_parent')->count(),
                    'approved' => LeaveRequest::where('user_id', $user->id)->where('status', 'approved')->count(),
                    'rejected' => LeaveRequest::where('user_id', $user->id)->where('status', 'rejected')->count(),
                    'total_days_taken' => LeaveRequest::where('user_id', $user->id)->where('status', 'approved')->sum('days'),
                ];
            }
            
            return ApiResponse::success($stats, 'Statistics retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve statistics: ' . $e->getMessage());
        }
    }
  private function updateLeaveBalance($leaveRequest)
{
    try {
        // جلب أو إنشاء البالانس
        $balance = $this->getOrCreateBalance(
            $leaveRequest->user_id, 
            $leaveRequest->leave_type_id
        );
        
        if ($balance) {
            // تحديث الأيام المستخدمة
            $balance->used_days += $leaveRequest->days;
            $balance->remaining_days = $balance->total_days - $balance->used_days;
            $balance->save();
        }
    } catch (\Exception $e) {
        \Log::error('Failed to update leave balance: ' . $e->getMessage());
    }
}
private function getOrCreateBalance($userId, $leaveTypeId)
{
    $currentYear = date('Y');
    
    // حاول جلب البالانس
    $balance = LeaveBalance::where('user_id', $userId)
        ->where('leave_type_id', $leaveTypeId)
        ->where('year', $currentYear)
        ->first();
    
    // لو مش موجود، حاول تعمله
    if (!$balance) {
        // جلب نوع الإجازة
        $leaveType = LeaveType::find($leaveTypeId);
        
        if ($leaveType) {
            // إنشاء بالانس جديد
            $balance = LeaveBalance::create([
                'user_id' => $userId,
                'leave_type_id' => $leaveTypeId,
                'year' => $currentYear,
                'total_days' => $leaveType->default_days ?? 0,
                'used_days' => 0,
                'remaining_days' => $leaveType->default_days ?? 0,
            ]);
        }
    }
    
    return $balance;
}
}