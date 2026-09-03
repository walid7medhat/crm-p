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
    protected $description = "Create evaluation cycles for active employees who've hit their 3/6-month tenure milestone";

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
                $milestone = $isSales ? 3 : 6;
                $currentPeriod = intdiv($months, $milestone);

                if ($currentPeriod < 1) {
                    continue;
                }

                $periodsToCreate = $recurring ? range(1, $currentPeriod) : [1];

                foreach ($periodsToCreate as $period) {
                    $exists = Evaluation::where('user_id', $user->id)
                        ->where('period_number', $period)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    if (! $recurring && $period > 1) {
                        continue;
                    }

                    $evaluation = Evaluation::create([
                        'user_id' => $user->id,
                        'evaluator_id' => $user->parent_id,
                        'milestone_months' => $milestone,
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
