<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Foreign Access Monitor
    |--------------------------------------------------------------------------
    | Lightweight geo-from-IP monitoring for access outside the UAE.
    | Detection only — never blocks login or invalidates sessions.
    */

    'enabled' => (bool) env('FOREIGN_ACCESS_MONITOR_ENABLED', true),

    /** Queue name for EvaluateForeignAccessJob (async; requires queue:work). */
    'queue' => env('FOREIGN_ACCESS_QUEUE', 'default'),

    /** ISO 3166-1 alpha-2 codes treated as home (no foreign alert). */
    'home_country_codes' => ['AE'],

    /** Deduplicate identical foreign alerts for the same user + IP + session. */
    'dedupe_ttl_seconds' => (int) env('FOREIGN_ACCESS_DEDUPE_TTL', 21600), // 6 hours

    /**
     * Mid-session IP probe cooldown. Cache-only gate before any GeoIP / DB work.
     * Prevents expensive lookups on every authenticated request.
     */
    'probe_cooldown_seconds' => (int) env('FOREIGN_ACCESS_PROBE_COOLDOWN', 120),

];
