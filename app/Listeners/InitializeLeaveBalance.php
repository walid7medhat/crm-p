<?php

namespace App\Listeners;

use App\Models\LeaveBalance;
use App\Models\LeaveType;

class InitializeLeaveBalance
{
    public function handle($event)
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
                    'user_id' => $event->user->id,
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
}