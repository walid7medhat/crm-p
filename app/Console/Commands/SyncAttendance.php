<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;

class SyncAttendance extends Command
{
    protected $signature = 'attendance:sync';
    protected $description = 'Sync attendance snapshot from biometric API';

    public function handle()
    {
        $this->info('Start syncing attendance...');

        try {

            $response = Http::withBasicAuth('admin', 'admin1234')
                ->withHeaders([
                    'x-api-key' => 'zkbio_secure_2026',
                    'Accept' => 'application/json',
                ])
                ->withoutVerifying()
                ->timeout(60)
                ->get('https://oiahead.fortidyndns.com/api/attendance/today');

            if (!$response->successful()) {
                $this->error('API Error: ' . $response->status());
                return;
            }

            $data = $response->json() ?? [];

            \Log::info('Attendance API Data', $data);

            // ✔️ users map
            $users = User::whereNotNull('biometric_code')
                ->pluck('id', 'biometric_code');

            // ✔️ today date (Egypt timezone)
            $today = now('Africa/Cairo')->toDateString();

            $count = 0;

            foreach ($data as $item) {

                // ❌ skip wrong date
                if (($item['attendance_date'] ?? null) !== $today) {
                    continue;
                }

                // ❌ skip empty employee
                if (empty($item['emp_code'])) {
                    continue;
                }

                // ✔️ find user
                $userId = $users[$item['emp_code']] ?? null;

                if (!$userId) {
                    $this->warn('User not found: ' . $item['emp_code']);
                    continue;
                }

                // ✔️ parse times
                $checkIn = !empty($item['first_checkin'])
                    ? Carbon::parse($item['first_checkin'])
                    : null;

                $checkOut = !empty($item['last_checkout'])
                    ? Carbon::parse($item['last_checkout'])
                    : null;

                // ✔️ save daily snapshot
                Attendance::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'date' => $today,
                    ],
                    [
                        'check_in' => $checkIn,
                        'check_out' => $checkOut,
                    ]
                );

                $count++;
            }

            $this->info("Done. Synced {$count} records.");

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }
}