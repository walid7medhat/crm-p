<?php

namespace App\Console\Commands;

use App\Mail\AbsenceNoticeMail;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotifyAbsentEmployees extends Command
{
    protected $signature = 'attendance:notify-absent {--force : Run even before 11:00 UAE time}';
    protected $description = 'Email employees with no check-in today and on the previous working day';

    public function handle()
    {
        $now = now('Asia/Dubai');

        if (!$this->option('force') && $now->hour < 11) {
            $this->warn('Before 11:00 Dubai time, skipping. Use --force to override.');
            return;
        }

        if ($now->isSunday()) {
            $this->info('Sunday (weekend), nothing to do.');
            return;
        }

        // Previous working day (skip Sunday)
        $prev = $now->copy()->subDay();
        if ($prev->isSunday()) {
            $prev->subDay();
        }

        $dates = [$now->toDateString(), $prev->toDateString()];

        // Anyone with a check-in on either day is excluded
        $presentIds = Attendance::whereIn('date', $dates)
           ->where('receive_absent_email', true) 
            ->whereNotNull('check_in')
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->unique()
            ->all();

        $absentUsers = User::where('status', 'active')
            ->whereNotNull('biometric_code')
            ->whereNotNull('email')
            ->whereNotIn('id', $presentIds)
            ->get();

        $sent = 0;

        foreach ($absentUsers as $user) {
            // One email per user per day, even if the command runs twice
            $key = "absence-notice:{$user->id}:" . $now->toDateString();
            if (!Cache::add($key, true, now()->addDays(2))) {
                continue;
            }

            try {
                Mail::to($user->email)->send(
                    new AbsenceNoticeMail(User::resolveDisplayName($user))
                );
                $sent++;
            } catch (\Throwable $e) {
                Cache::forget($key); // allow retry
                Log::warning('Absence notice failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Absence notices sent: {$sent}");
    }
}