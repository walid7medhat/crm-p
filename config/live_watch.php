<?php

/**
 * Super Admin Live Watch — presence + activity timeline.
 *
 * Behaviour (documented for operators):
 * - Exactly one CRM user may be monitored at a time (live_watch_settings).
 * - Presence uses authenticated heartbeats (default 30s) and a DB session table
 *   (Redis was not operational; do not switch without confirming Redis).
 * - A user is online while ≥1 non-expired tab/device session exists.
 * - Offline timeout defaults to 90s after the last heartbeat for every session.
 * - Timeline stores navigation (page/modal) and business actions separately.
 * - Never stores passwords, tokens, full query strings, form bodies, or DMs.
 * - Retention: prune events older than retention_days (default 60).
 * - Super Admin only: APIs, /tracking UI, and private-live-watch channel.
 */

return [
    'heartbeat_interval_seconds' => (int) env('LIVE_WATCH_HEARTBEAT_SECONDS', 30),
    'offline_timeout_seconds' => (int) env('LIVE_WATCH_OFFLINE_TIMEOUT_SECONDS', 90),
    'retention_days' => (int) env('LIVE_WATCH_RETENTION_DAYS', 60),
    'navigation_dedupe_seconds' => 8,
    'max_metadata_bytes' => 2048,
];
