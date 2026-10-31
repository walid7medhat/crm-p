<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Foreground session poll interval (seconds)
    |--------------------------------------------------------------------------
    | How often the CRM SPA asks the browser for a fresh device fix while the
    | tab/PWA is visible. Background/suspended browsers do not keep polling.
    */
    'interval_seconds' => (int) env('LOCATION_TRACK_INTERVAL_SECONDS', 180),

    /*
    |--------------------------------------------------------------------------
    | Minimum movement before a new history row (metres)
    |--------------------------------------------------------------------------
    */
    'min_move_meters' => (float) env('LOCATION_TRACK_MIN_METERS', 75),

    /*
    |--------------------------------------------------------------------------
    | Freshness window (minutes)
    |--------------------------------------------------------------------------
    | A device fix is treated as "live" only when captured_at is within this
    | window. Older points are "last known", even if the user is still online.
    */
    'fresh_minutes' => (int) env('LOCATION_FRESH_MINUTES', 10),

    /*
    |--------------------------------------------------------------------------
    | Online presence window (minutes)
    |--------------------------------------------------------------------------
    | Must match the CRM online definition (JwtAuthMiddleware presence touch +
    | last_login_at within this window). A location write after the previous
    | history point fell outside this window starts a new online session and
    | always appends a history row (even without 75 m of movement).
    */
    'online_window_minutes' => (int) env('LOCATION_ONLINE_WINDOW_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Server write throttle (seconds)
    |--------------------------------------------------------------------------
    | Ignore rapid duplicate POSTs from reconnects / double submits. Latest
    | coords are still allowed to refresh after this cool-down.
    */
    'write_cooldown_seconds' => (int) env('LOCATION_WRITE_COOLDOWN_SECONDS', 30),

];
