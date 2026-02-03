<?php

namespace App\Console\Commands;

use App\Models\LeadActivity;
use App\Models\User;
use App\Notifications\ActivityReminderNotification;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendActivityReminders extends Command
{
    protected $signature = 'activities:send-reminders {--test}';
    protected $description = 'Send activity reminders based on reminder times';

    public function handle()
    {
        $this->info('Checking due activity reminders...');
        Log::info('SendActivityReminders: Checking due activity reminders.');
        Log::info('SendActivityReminders: '.Carbon::now());

        $activities = $this->getDueReminders();

        if ($activities->isEmpty()) {
            $this->info('No reminders to send.');
            Log::info('SendActivityReminders: No due reminders found.');
            return;
        }

        foreach ($activities as $activity) {
            try {
                $this->info("Sending reminder for activity #{$activity->id} to user #{$activity->user_id}");
                Log::info("SendActivityReminders: Sending reminder for activity #{$activity->id} to user #{$activity->user_id}");

                $timeframe = $this->getTimeframe($activity);
                $activity->user->notify(new ActivityReminderNotification($activity, $timeframe, false));

                if ($activity->lead->responsible_person_id && $activity->lead->responsible_person_id != $activity->user_id) {
                        $responsibleUser = User::find($activity->lead->responsible_person_id);
                        if ($responsibleUser) {
                            $responsibleUser->notify(new ActivityReminderNotification($activity, $timeframe, true));
                        }
                    }

                $this->markReminderSent($activity);
                $this->info("✅ Reminder sent for activity #{$activity->id}");
                Log::info("SendActivityReminders: Reminder marked sent for activity #{$activity->id}");

            } catch (\Exception $e) {
                $this->error("❌ Failed activity #{$activity->id}: {$e->getMessage()}");
                Log::error("SendActivityReminders: Failed to send reminder for activity #{$activity->id}. Error: {$e->getMessage()}", [
                    'exception' => $e
                ]);
            }
        }
    }

    private function getDueReminders()
    {
        $activities = LeadActivity::with(['user', 'lead'])
            ->where('next_reminder_at', '<=', now())
            ->where('is_completed', false)
            ->get();

        Log::info("SendActivityReminders: Found {$activities->count()} due activities.");
        return $activities;
    }

    private function markReminderSent(LeadActivity $activity)
    {
        $activity->last_reminded_at = now();

        $next = collect($activity->reminders)
            ->map(fn ($m) => Carbon::parse($activity->reminder_date)->subMinutes($m))
            ->filter(fn ($t) => $t->isFuture())
            ->sort()
            ->first();

        $activity->next_reminder_at = $next;

        $activity->save();

        Log::info("SendActivityReminders: Activity #{$activity->id} next reminder set to " . ($next?->toDateTimeString() ?? 'none'));
    }
    private function getTimeframe($activity)
{
    $now = now();
    $reminder = $activity->reminder_date;

    if ($reminder->isPast()) {
        return 'overdue';
    } elseif ($reminder->isToday()) {
        return 'today';
    } elseif ($reminder->isTomorrow()) {
        return 'tomorrow';
    } else {
        return 'upcoming';
    }
}

}
