<?php

namespace App\Console\Commands;

use App\Models\DealActivity;
use App\Models\LeadActivity;
use App\Models\User;
use App\Notifications\ActivityReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Sends due reminders for lead and deal activities. Runs every minute (bootstrap/app.php).
 * An activity is due when next_reminder_at <= now; after sending, next_reminder_at
 * moves to the next future offset, or null when none is left.
 */
class SendActivityReminders extends Command
{
    protected $signature = 'activities:send-reminders {--test}';
    protected $description = 'Send lead and deal activity reminders based on reminder times';

    public function handle()
    {
        Log::info('SendActivityReminders: checking due reminders at ' . now());

        $leadActivities = LeadActivity::with(['user', 'lead'])
            ->whereNotNull('next_reminder_at')
            ->where('next_reminder_at', '<=', now())
            ->where('is_completed', false)
            ->get();

        $dealActivities = DealActivity::with(['user', 'deal'])
            ->whereNotNull('next_reminder_at')
            ->where('next_reminder_at', '<=', now())
            ->where('is_completed', false)
            ->get();

        $total = $leadActivities->count() + $dealActivities->count();
        Log::info("SendActivityReminders: found {$leadActivities->count()} lead and {$dealActivities->count()} deal activities due.");

        if ($total === 0) {
            $this->info('No reminders to send.');
            return;
        }

        foreach ($leadActivities as $activity) {
            $this->sendFor($activity, $activity->lead?->responsible_person_id);
        }

        foreach ($dealActivities as $activity) {
            $this->sendFor($activity, $activity->deal?->responsible_person_id);
        }
    }

    private function sendFor(LeadActivity|DealActivity $activity, ?int $responsibleId): void
    {
        $kind = $activity instanceof DealActivity ? 'deal' : 'lead';
        $record = $kind === 'deal' ? $activity->deal : $activity->lead;

        try {
            // Parent record or owner gone: nothing to remind about, stop retrying.
            if (!$record || !$activity->user) {
                $this->advance($activity, "missing {$kind} or user");
                return;
            }

            $timeframe = $this->getTimeframe($activity);
            $activity->user->notify(new ActivityReminderNotification($activity, $timeframe, false));

            if ($responsibleId && $responsibleId != $activity->user_id) {
                User::find($responsibleId)?->notify(new ActivityReminderNotification($activity, $timeframe, true));
            }

            $this->advance($activity);
            $this->info("Reminder sent for {$kind} activity #{$activity->id}");
        } catch (\Throwable $e) {
            // Advance anyway so one bad row does not re-send to the user every minute.
            $this->advance($activity, $e->getMessage());
            $this->error("Failed {$kind} activity #{$activity->id}: {$e->getMessage()}");
            Log::error("SendActivityReminders: failed {$kind} activity #{$activity->id}: {$e->getMessage()}", ['exception' => $e]);
        }
    }

    /** Move this activity to its next reminder (or none). */
    private function advance(LeadActivity|DealActivity $activity, ?string $reason = null): void
    {
        $activity->advanceToNextReminder();
        $activity->saveQuietly();

        Log::info(sprintf(
            'SendActivityReminders: %s #%d next reminder %s%s',
            class_basename($activity),
            $activity->id,
            $activity->next_reminder_at?->toDateTimeString() ?? 'none',
            $reason ? " ({$reason})" : ''
        ));
    }

    private function getTimeframe(LeadActivity|DealActivity $activity): string
    {
        $reminder = $activity->reminder_date;

        if ($reminder->isPast()) {
            return 'overdue';
        }
        if ($reminder->isToday()) {
            return 'today';
        }
        if ($reminder->isTomorrow()) {
            return 'tomorrow';
        }

        return 'upcoming';
    }
}
