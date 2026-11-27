<?php

use App\Models\DealActivity;
use App\Models\LeadActivity;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-time data fix for activities saved before HasActivityReminders.
 *
 * The activity forms send reminder_date in UTC ("…T10:00:00Z") and it was stored
 * with those clock digits as if they were app time (Asia/Dubai), i.e. 4 hours early.
 * This moves those rows forward by the app timezone's UTC offset and recomputes
 * next_reminder_at for pending activities that are still ahead.
 *
 * Not shifted: Bitrix-imported lead activities (bitrix24_id set) — the importer
 * already converted those to app time.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->shift(+1);
    }

    public function down(): void
    {
        $this->shift(-1);
    }

    private function shift(int $direction): void
    {
        $offset = Carbon::now(config('app.timezone'))->utcOffset() * $direction; // minutes
        if ($offset === 0) {
            return;
        }

        $cutoff = now()->format('Y-m-d H:i:s');

        foreach ([[LeadActivity::class, 'lead_activities'], [DealActivity::class, 'deal_activities']] as [$model, $table]) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $query = fn () => DB::table($table)
                ->whereNotNull('reminder_date')
                ->where('created_at', '<', $cutoff)
                ->when(Schema::hasColumn($table, 'bitrix24_id'), fn ($q) => $q->whereNull('bitrix24_id'));

            $ids = $query()->pluck('id');

            foreach ($ids->chunk(500) as $chunk) {
                DB::table($table)->whereIn('id', $chunk)->update([
                    'reminder_date' => DB::raw("DATE_ADD(reminder_date, INTERVAL {$offset} MINUTE)"),
                ]);
            }

            // Re-plan reminders for pending activities whose (corrected) time is still ahead.
            $model::query()
                ->whereIn('id', $ids)
                ->where('is_completed', false)
                ->where('reminder_date', '>', now())
                ->whereNotNull('reminders')
                ->chunkById(500, function ($activities) use ($table) {
                    foreach ($activities as $activity) {
                        DB::table($table)->where('id', $activity->id)->update([
                            'next_reminder_at' => $activity->upcomingReminderTimes()->first()?->format('Y-m-d H:i:s'),
                        ]);
                    }
                });
        }
    }
};
