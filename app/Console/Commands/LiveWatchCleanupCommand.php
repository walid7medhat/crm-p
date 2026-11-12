<?php

namespace App\Console\Commands;

use App\Models\LiveWatchEvent;
use App\Models\LiveWatchSession;
use App\Services\LiveWatch\LiveWatchPresenceService;
use Illuminate\Console\Command;

class LiveWatchCleanupCommand extends Command
{
    protected $signature = 'live-watch:cleanup';

    protected $description = 'Expire stale Live Watch presence sessions and prune old timeline events';

    public function handle(LiveWatchPresenceService $presence): int
    {
        $presence->reconcilePresence();

        $timeout = max(30, (int) $presence->settings()->offline_timeout_seconds);
        $deletedSessions = LiveWatchSession::query()
            ->where('last_seen_at', '<', now()->subSeconds($timeout * 2))
            ->delete();

        $days = max(7, (int) config('live_watch.retention_days', 60));
        $deletedEvents = LiveWatchEvent::query()
            ->where('occurred_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Live Watch cleanup: sessions={$deletedSessions}, events={$deletedEvents}, retention_days={$days}");

        return self::SUCCESS;
    }
}
