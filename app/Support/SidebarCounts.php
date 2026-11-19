<?php

namespace App\Support;

use App\Events\SidebarCountsChanged;
use App\Models\HotDealRequest;
use App\Models\Listing;
use App\Models\ListingAccessRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Push-based freshness for sidebar badges.
 *
 * Any change that can move a badge bumps a global version (part of the per-user cache key,
 * so the next fetch is fresh) and broadcasts SidebarCountsChanged once per burst. Clients
 * then refetch instead of every tab polling /sidebar/counts every minute.
 */
class SidebarCounts
{
    private const VERSION_KEY = 'sidebar_counts:version';

    private const BROADCAST_LOCK = 'sidebar_counts:broadcast-lock';

    /** One broadcast per burst (bulk approvals, imports) — the rest ride on the same refetch. */
    private const BROADCAST_EVERY_SECONDS = 3;

    /** Listing columns that feed a badge (all / my / archive / need-approve). */
    private const LISTING_FIELDS = ['approved', 'status', 'is_archived', 'is_active', 'agent_id'];

    private static bool $scheduled = false;

    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 0);
    }

    public static function registerModelHooks(): void
    {
        $bump = static fn () => self::changed();

        Listing::created($bump);
        Listing::deleted($bump);
        Listing::updated(static function (Model $listing) {
            if ($listing->wasChanged(self::LISTING_FIELDS)) {
                self::changed();
            }
        });

        foreach ([ListingAccessRequest::class, HotDealRequest::class] as $model) {
            $model::created($bump);
            $model::updated($bump);
            $model::deleted($bump);
        }
    }

    /**
     * Mark counts stale. Broadcasting is deferred until after the response is sent, so a
     * save never waits on Pusher, and collapsed to one event per request.
     */
    public static function changed(): void
    {
        try {
            if (! Cache::add(self::VERSION_KEY, 1)) {
                Cache::increment(self::VERSION_KEY);
            }
        } catch (\Throwable $e) {
            Log::debug('sidebar counts version bump failed', ['error' => $e->getMessage()]);
        }

        // Queue workers / artisan never "terminate" between jobs — send right away there.
        if (app()->runningInConsole()) {
            self::broadcast();

            return;
        }

        if (self::$scheduled) {
            return;
        }
        self::$scheduled = true;

        app()->terminating(static function () {
            self::$scheduled = false;
            self::broadcast();
        });
    }

    private static function broadcast(): void
    {
        try {
            if (config('broadcasting.default') === 'null' || ! Cache::add(self::BROADCAST_LOCK, 1, self::BROADCAST_EVERY_SECONDS)) {
                return;
            }
            broadcast(new SidebarCountsChanged(self::version()));
        } catch (\Throwable $e) {
            // Clients still have the slow safety poll — never fail the request over this.
            Log::debug('sidebar counts broadcast failed', ['error' => $e->getMessage()]);
        }
    }
}
