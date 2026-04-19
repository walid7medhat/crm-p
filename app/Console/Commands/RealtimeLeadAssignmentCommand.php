<?php

namespace App\Console\Commands;

use App\Models\LeadAssignmentSetting;
use App\Services\LeadAssignmentService;
use Illuminate\Console\Command;

class RealtimeLeadAssignmentCommand extends Command
{
    protected $signature = 'leads:realtime-assign
                            {--once : Process a single batch then exit}
                            {--daemon : Run continuously until stopped (use Supervisor)}';

    protected $description = 'Realtime lead assignment: simple attendance mode or full AI batch';

    protected function applyAdaptiveSleep(
        LeadAssignmentService $service,
        LeadAssignmentSetting $settings
    ): void {
        if ($settings->system_disabled || !$settings->auto_assign) {
            return;
        }

        if (!$settings->simple_mode_enabled && !$settings->realtime_assignment_enabled) {
            return;
        }

        if ($settings->simple_mode_enabled) {
            $sleep = max(2, min(300, (int) ($settings->simple_mode_auto_interval_seconds ?? 10)));
        } else {
            $depth = $service->countPendingNewLeads();
            $sleep = LeadAssignmentService::adaptiveRealtimeSleepSeconds(
                $depth,
                (int) ($settings->realtime_interval_seconds ?? 3)
            );
            $sleep = max(1, min(30, $sleep));
        }

        LeadAssignmentSetting::query()->whereKey($settings->id)->update([
            'realtime_last_interval_applied' => $sleep,
        ]);

        sleep($sleep);
    }

    protected function runTick(LeadAssignmentService $service): array
    {
        $settings = LeadAssignmentSetting::current();
        if ($settings->system_disabled || !$settings->auto_assign) {
            return [];
        }

        if ($settings->simple_mode_enabled) {
            return $service->assignLeadsByAttendanceSimple();
        }

        return $service->runRealtimeAssignmentBatch();
    }

    public function handle(LeadAssignmentService $service): int
    {
        if ($this->option('daemon')) {
            $this->warn('Realtime daemon running — stop via Supervisor/SIGTERM.');
            while (true) {
                $settings = LeadAssignmentSetting::current();
                $simpleModeGate = $settings->simple_mode_enabled && !$settings->system_disabled && $settings->auto_assign;
                $realtimeGate = $settings->realtime_assignment_enabled && !$settings->system_disabled && $settings->auto_assign;
                if (!$simpleModeGate && !$realtimeGate) {
                    LeadAssignmentSetting::query()->whereKey($settings->id)->update([
                        'realtime_status' => 'stopped',
                        'realtime_last_run_at' => now(),
                    ]);
                    sleep(5);
                    continue;
                }

                $stats = $this->runTick($service);
                if (($stats['assigned'] ?? 0) > 0 || ($stats['errors'] ?? 0) > 0 || (($stats['high_load'] ?? false) === true)) {
                    $this->line(json_encode($stats));
                }

                $fresh = LeadAssignmentSetting::current();
                $this->applyAdaptiveSleep($service, $fresh);
            }
        }

        if ($this->option('once')) {
            $stats = $this->runTick($service);
            $this->info(json_encode($stats));
            $settings = LeadAssignmentSetting::current();
            if ((($settings->realtime_assignment_enabled || $settings->simple_mode_enabled) && !$settings->system_disabled && $settings->auto_assign)) {
                if ($settings->simple_mode_enabled) {
                    $sleep = max(2, min(300, (int) ($settings->simple_mode_auto_interval_seconds ?? 10)));
                } else {
                    $depth = $service->countPendingNewLeads();
                    $sleep = LeadAssignmentService::adaptiveRealtimeSleepSeconds(
                        $depth,
                        (int) ($settings->realtime_interval_seconds ?? 3)
                    );
                    $sleep = max(1, min(30, $sleep));
                }
                LeadAssignmentSetting::query()->whereKey($settings->id)->update([
                    'realtime_last_interval_applied' => $sleep,
                ]);
            }

            return self::SUCCESS;
        }

        $settings = LeadAssignmentSetting::current();
        if ((!$settings->realtime_assignment_enabled && !$settings->simple_mode_enabled) || $settings->system_disabled || !$settings->auto_assign) {
            return self::SUCCESS;
        }

        $deadline = time() + 57;

        while (time() < $deadline) {
            $fresh = LeadAssignmentSetting::current();
            if ((!$fresh->realtime_assignment_enabled && !$fresh->simple_mode_enabled) || $fresh->system_disabled || !$fresh->auto_assign) {
                LeadAssignmentSetting::query()->whereKey($fresh->id)->update([
                    'realtime_status' => 'stopped',
                ]);
                break;
            }

            $stats = $this->runTick($service);
            if (($stats['assigned'] ?? 0) > 0 || ($stats['errors'] ?? 0) > 0 || (($stats['high_load'] ?? false) === true)) {
                $this->line(json_encode($stats));
            }

            $after = LeadAssignmentSetting::current();
            if ((!$after->realtime_assignment_enabled && !$after->simple_mode_enabled) || $after->system_disabled || !$after->auto_assign) {
                break;
            }

            $this->applyAdaptiveSleep($service, $after);

            if (time() >= $deadline) {
                break;
            }
        }

        return self::SUCCESS;
    }
}
