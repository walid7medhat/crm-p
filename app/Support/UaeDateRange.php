<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Date filters ("Today", "Yesterday", custom range) are picked as UAE calendar days.
 *
 * `whereDate(created_at, ...)` compares the date in the app/DB timezone, so when the
 * server runs on UTC a "day" was cut at 04:00 UAE: leads created 00:00–04:00 UAE fell
 * into Yesterday and Today missed them. These helpers turn a UAE day into the exact
 * [start, end] datetimes in the app timezone, so the boundary is UAE midnight whatever
 * APP_TIMEZONE is (identity when it is already Asia/Dubai).
 */
class UaeDateRange
{
    public const TIMEZONE = 'Asia/Dubai';

    /** 'Y-m-d' (UAE day) → start of that day as an app-timezone datetime string. */
    public static function start(string $date): string
    {
        return Carbon::parse($date, self::TIMEZONE)->startOfDay()
            ->setTimezone(config('app.timezone'))->toDateTimeString();
    }

    /** 'Y-m-d' (UAE day) → end of that day as an app-timezone datetime string. */
    public static function end(string $date): string
    {
        return Carbon::parse($date, self::TIMEZONE)->endOfDay()
            ->setTimezone(config('app.timezone'))->toDateTimeString();
    }

    /**
     * Constrain $column to UAE days. $exact (a single day) wins over $from/$to;
     * either bound may be empty.
     */
    public static function apply($query, string $column, $from = null, $to = null, $exact = null)
    {
        if (filled($exact)) {
            $from = $to = $exact;
        }
        if (filled($from)) {
            $query->where($column, '>=', self::start((string) $from));
        }
        if (filled($to)) {
            $query->where($column, '<=', self::end((string) $to));
        }

        return $query;
    }

    /** History actions that are not a modification of the lead. */
    public const NON_MODIFYING_HISTORY_ACTIONS = ['view', 'created'];

    /**
     * Lead search "Modified On": the lead has a real change in its history
     * (lead_histories) on those UAE days — an update, stage change, assignment,
     * comment, activity… — not just a view or its creation entry.
     *
     * leads.updated_at can't be used: leads:score re-saves every lead every 10
     * minutes (last_scored_at), so updated_at is always "now".
     *
     * $query must be an Eloquent Lead query (uses the `histories` relation).
     */
    public static function applyModified($query, $from = null, $to = null, $exact = null)
    {
        if (! filled($from) && ! filled($to) && ! filled($exact)) {
            return $query;
        }

        return $query->whereHas('histories', function ($history) use ($from, $to, $exact) {
            self::apply($history, 'lead_histories.created_at', $from, $to, $exact);
            $history->where(function ($q) {
                $q->whereNull('changes->action')
                    ->orWhereNotIn('changes->action', self::NON_MODIFYING_HISTORY_ACTIONS);
            });
        });
    }
}
