<?php

namespace App\Console\Commands;

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AccrueAnnualLeave extends Command
{
    protected $signature = 'leave:accrue-annual';

    protected $description = 'Accrue Annual Leave balances: 14 days after 6 months of service, then +2.5 days per month after that';

    public function handle()
    {
        $annualLeaveType = LeaveType::where('name', 'Annual Leave - Paid Leave')->first();

        if (!$annualLeaveType) {
            $this->error('Annual Leave - Paid Leave type not found.');
            return 1;
        }

        $currentYear = date('Y');
        $today = Carbon::today();

        $users = User::whereHas('employeeProfile', function ($query) {
            $query->whereNotNull('joining_date');
        })->with('employeeProfile')->get();

        $updated = 0;

        foreach ($users as $user) {
            $joiningDate = Carbon::parse($user->employeeProfile->joining_date);
            $months = $joiningDate->diffInMonths($today);

            $expected = $months < 6 ? 0 : 14 + max(0, $months - 6) * 2.5;

            $balance = LeaveBalance::firstOrNew([
                'user_id' => $user->id,
                'leave_type_id' => $annualLeaveType->id,
                'year' => $currentYear,
            ]);

            $currentTotal = (float) ($balance->total_days ?? 0);
            $newTotal = max($currentTotal, $expected);

            if ($newTotal == $currentTotal && $balance->exists) {
                continue;
            }

            $balance->total_days = $newTotal;
            $balance->used_days = $balance->used_days ?? 0;
            $balance->remaining_days = $newTotal - (float) $balance->used_days;
            $balance->save();

            $updated++;
        }

        $this->info("Annual leave accrual complete. Balances updated: {$updated}");
        Log::info('Annual leave accrual completed', ['balances_updated' => $updated]);

        return 0;
    }
}
