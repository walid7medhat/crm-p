<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Kanban board aggregates (stage counts, heat chip totals) over many leads.
 *
 * leads_board_scope_idx / leads_board_owner_idx hold every column those queries
 * read, so MySQL answers them from the index without opening the wide lead rows.
 * Left alone, the planner prefers narrower indexes (heat cover / merged / brand_key)
 * that still need a row lookup per lead — about 30 s instead of 0.2 s on 180k leads.
 * Ignoring those three lets it choose between the two covering indexes.
 *
 * Only applied on MySQL/MariaDB and only when every named index exists
 * (a hint naming a missing index is an SQL error).
 */
class LeadBoardIndexHint
{
    private const REQUIRED = ['leads_board_scope_idx', 'leads_board_owner_idx'];

    private const IGNORED = [
        'leads_kanban_heat_cover_idx',
        'leads_merged_into_lead_id_index',
        'leads_brand_key_index',
    ];

    private static ?bool $ready = null;

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query  a query on the leads table
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function apply($query)
    {
        $base = $query->getQuery();
        if ($base->from !== 'leads' || ! self::ready()) {
            return $query;
        }

        $base->from(DB::raw('`leads` IGNORE INDEX ('.implode(', ', self::IGNORED).')'));

        return $query;
    }

    public static function ready(): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return self::$ready = false;
        }

        try {
            $names = Cache::remember('leads_board_index_hint_v1', 3600, function () {
                return collect(DB::select('SHOW INDEX FROM `leads`'))
                    ->pluck('Key_name')->unique()->values()->all();
            });
        } catch (\Throwable) {
            return self::$ready = false;
        }

        return self::$ready = array_diff([...self::REQUIRED, ...self::IGNORED], $names) === [];
    }

    public static function forget(): void
    {
        self::$ready = null;
        Cache::forget('leads_board_index_hint_v1');
    }
}
