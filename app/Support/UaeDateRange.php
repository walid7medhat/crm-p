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
     * leads.updated_at isn't used: it also moves on system writes (sync, auto-assign…),
     * while the history only holds real changes.
     *
     * $userIds ("Modified By") limits it to changes made by those users. With both
     * dates and users, the same history entry must match both (changed by X on day D).
     *
     * Written as `leads.id IN (select lead_id from lead_histories where …)` rather than
     * a per-lead EXISTS: MySQL first picks the matching history rows through the
     * (created_at, lead_id) / (user_id, created_at) indexes, instead of walking every
     * lead's whole history on each kanban query.
     *
     * $query must be a Lead query on the `leads` table.
     */
    public static function applyModified($query, $from = null, $to = null, $exact = null, array $userIds = [])
    {
        if (! filled($from) && ! filled($to) && ! filled($exact) && empty($userIds)) {
            return $query;
        }

        // Same visibility as Lead::histories(): admins also see soft-deleted entries.
        $history = \App\Models\LeadHistory::query();
        if (auth()->check() && auth()->user()->hasAnyRole(['admin', 'super_admin', 'branch_admin'])) {
            $history->withTrashed();
        }

        self::apply($history, 'lead_histories.created_at', $from, $to, $exact);
        if (! empty($userIds)) {
            $history->whereIn('lead_histories.user_id', $userIds);
        }
        $history->whereNotNull('lead_histories.lead_id')
            ->where(function ($q) {
                $q->whereNull('lead_histories.changes->action')
                    ->orWhereNotIn('lead_histories.changes->action', self::NON_MODIFYING_HISTORY_ACTIONS);
            });

        return $query->whereIn('leads.id', $history->select('lead_histories.lead_id'));
    }
}
