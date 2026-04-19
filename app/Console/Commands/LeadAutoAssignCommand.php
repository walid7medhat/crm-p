<?php

namespace App\Console\Commands;

use App\Models\LeadAssignmentSetting;
use App\Services\LeadAssignmentService;
use Illuminate\Console\Command;

class LeadAutoAssignCommand extends Command
{
    protected $signature = 'leads:auto-assign {--scheduled-tick : Run only when schedule_times matches current minute}';

    protected $description = 'Process lead auto-assignment queue (scheduled engine tick or full run)';

    public function handle(LeadAssignmentService $service): int
    {
        $settings = LeadAssignmentSetting::current();

        if ($this->option('scheduled-tick')) {
            if ($settings->system_disabled || !$settings->auto_assign || $settings->mode !== 'scheduled') {
                return self::SUCCESS;
            }

            if (!$this->matchesSchedule($settings)) {
                return self::SUCCESS;
            }

            $stats = $service->assignQueuedLeads(true);
            $this->info(json_encode($stats));

            return self::SUCCESS;
        }

        $stats = $service->assignQueuedLeads(false);
        $this->info(json_encode($stats));

        return self::SUCCESS;
    }

    protected function matchesSchedule(LeadAssignmentSetting $settings): bool
    {
        $tz = (string) ($settings->working_hours['timezone'] ?? 'Asia/Dubai');
        $now = now($tz);
        $current = $now->format('H:i');
        $times = collect($settings->schedule_times ?? [])
            ->map(fn ($t) => substr((string) $t, 0, 5))
            ->all();

        return in_array($current, $times, true);
    }
}
