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

    /**
     * Lead search "Modified On": updated_at within the UAE days, and only leads that
     * were actually edited after creation. A new lead's updated_at equals its
     * created_at (or lands a moment later — store() saves it again right after
     * create), so anything updated within a minute of creation is not "modified".
     */
    public static function applyModified($query, $from = null, $to = null, $exact = null)
    {
        if (! filled($from) && ! filled($to) && ! filled($exact)) {
            return $query;
        }

        self::apply($query, 'updated_at', $from, $to, $exact);

        return $query->whereRaw('updated_at > DATE_ADD(created_at, INTERVAL 60 SECOND)');
    }
}
