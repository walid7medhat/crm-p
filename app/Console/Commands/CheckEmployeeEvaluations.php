<?php

namespace App\Console\Commands;

use App\Mail\EvaluationRequestMail;
use App\Models\Evaluation;
use App\Models\EvaluationSetting;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckEmployeeEvaluations extends Command
{
    protected $signature = 'evaluations:check-due';
    protected $description = "Create evaluation cycles for active employees who've hit their tenure milestone(s)";

    /**
     * Sales gets two early check-ins (1 month, 2 months); everyone else gets
     * a single check-in at 6 months. Each entry's index (1-based) is that
     * evaluation's period_number.
     */
    private function milestoneSchedule(bool $isSales): array
    {
        return $isSales ? [1, 2] : [6];
    }

    public function handle()
    {
        $recurring = EvaluationSetting::current()->isRecurring();

        $users = User::where('status', 'active')
            ->whereHas('employeeProfile', function ($q) {
                $q->whereNotNull('joining_date');
            })
            ->with('employeeProfile.designation')
            ->get();

        $created = 0;

        foreach ($users as $user) {
            try {
                $profile = $user->employeeProfile;
                $months = $profile->joining_date->diffInMonths(now());
                $isSales = stripos($profile->designation?->name ?? '', 'sales') !== false;
                $schedule = $this->milestoneSchedule($isSales);

                // period_number => milestone_months for every checkpoint tenure has reached.
                $dueMilestones = [];
                foreach ($schedule as $index => $milestoneMonths) {
                    if ($months >= $milestoneMonths) {
                        $dueMilestones[$index + 1] = $milestoneMonths;
                    }
                }

                // Recurring mode: once the fixed schedule is exhausted, keep firing
                // using the gap between its last two checkpoints (or the schedule's
                // only checkpoint, if it has just one).
                if ($recurring && $months > end($schedule)) {
                    $lastMilestone = end($schedule);
                    $gap = count($schedule) > 1
                        ? ($schedule[count($schedule) - 1] - $schedule[count($schedule) - 2])
                        : $lastMilestone;
                    $extraCycles = intdiv($months - $lastMilestone, $gap);
                    for ($i = 1; $i <= $extraCycles; $i++) {
                        $dueMilestones[count($schedule) + $i] = $lastMilestone + $i * $gap;
                    }
                }

                foreach ($dueMilestones as $period => $milestoneMonths) {
                    $exists = Evaluation::where('user_id', $user->id)
                        ->where('period_number', $period)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $evaluation = Evaluation::create([
                        'user_id' => $user->id,
                        'evaluator_id' => $user->parent_id,
                        'milestone_months' => $milestoneMonths,
                        'period_number' => $period,
                        'designation_name_snapshot' => $profile->designation?->name,
                        'status' => 'pending',
                    ]);

                    $created++;

                    if ($user->parent_id) {
                        $manager = $user->parent;
                        if ($manager && $manager->email) {
                            Mail::to($manager->email)->send(new EvaluationRequestMail($evaluation, $user, $manager));
                        }
                    } else {
                        Log::warning("Evaluation created for user {$user->id} but they have no manager (parent_id) to notify.");
                    }
                }
            } catch (\Exception $e) {
                Log::error("Failed to process evaluation for user {$user->id}: {$e->getMessage()}");
            }
        }

        $this->info("Evaluation check complete. {$created} evaluation(s) created.");

        return 0;
    }
}
