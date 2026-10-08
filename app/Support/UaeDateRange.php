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
}
