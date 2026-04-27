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
            LeaveBalance::updateOrCreate(
                [
                    'user_id' => $event->user->id,
                    'leave_type_id' => $type->id,
                    'year' => $currentYear,
                ],
                [
                    'total_days' => $type->default_days,
                    'used_days' => 0,
                    'remaining_days' => $type->default_days,
                ]
            );
        }
    }
}