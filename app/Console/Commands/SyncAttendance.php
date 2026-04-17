<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Models\Attendance;

class SyncAttendance extends Command
{
    protected $signature = 'attendance:sync';
    protected $description = 'Sync today attendance from biometric API';

    public function handle()
    {
        $this->info('Start syncing attendance...');

        try {

            $response = Http::withBasicAuth('admin', 'admin1234')
                ->withHeaders([
                    'x-api-key' => 'zkbio_secure_2026',
                    'Accept' => 'application/json',
                ])
                ->timeout(60)
                ->get('http://oiahead.fortidyndns.com:8083/api/attendance/today');

            if (!$response->successful()) {
                $this->error('API Error');
                return;
            }

            $data = $response->json()['data'] ?? [];

            $count = 0;

            foreach ($data as $item) {

                if (empty($item['emp_code'])) continue;

                // 🔥 ربط باليوزر
                $user = User::where('biometric_code', $item['emp_code'])->first();

                if (!$user) continue;

                Attendance::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'check_time' => $item['punch_time'],
                    ],
                    [
                        'type' => $item['punch_state'] == "0" ? 'in' : 'out',
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