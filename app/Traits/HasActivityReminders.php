<?php

namespace App\Traits;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Collection;

/**
 * Shared reminder logic for LeadActivity and DealActivity.
 *
 * The frontend sends reminder_date as a UTC ISO string ("…T10:00:00.000Z").
 * Laravel's datetime cast stores the clock digits of that value without
 * converting it, so a 2:00 PM Dubai pick was stored as 10:00 and every reminder
 * fired 4 hours early. The mutator below converts any incoming value (string
 * with offset, Carbon in another zone, naive string) to the app timezone first.
 */
trait HasActivityReminders
{
    public function setReminderDateAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['reminder_date'] = null;
            return;
        }

        $date = $value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse($value);

        $this->attributes['reminder_date'] = $date
            ->setTimezone(config('app.timezone'))
            ->format($this->getDateFormat());
    }

    /**
     * Reminder times (reminder_date minus each offset) that are still ahead, earliest first.
     */
    public function upcomingReminderTimes(): Collection
    {
        if (empty($this->reminders) || !$this->reminder_date) {
            return collect();
        }

        return collect($this->reminders)
            ->map(fn ($minutes) => $this->reminder_date->copy()->subMinutes((int) $minutes))
            ->filter(fn (Carbon $time) => $time->isFuture())
            ->sort()
            ->values();
    }

    /**
     * Set next_reminder_at when an activity is created or edited. If every offset
     * is already past, remind once at the activity time itself.
     */
    public function calculateNextReminder(): void
    {
        if (empty($this->reminders) || !$this->reminder_date) {
            $this->next_reminder_at = null;
            return;
        }

        $this->next_reminder_at = $this->upcomingReminderTimes()->first() ?? $this->reminder_date;
    }

    /**
     * After a reminder is sent: move to the next future offset, or stop.
     */
    public function advanceToNextReminder(): void
    {
        $this->last_reminded_at = now();
        $this->next_reminder_at = $this->upcomingReminderTimes()->first();
    }
}
