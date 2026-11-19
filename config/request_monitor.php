<?php

return [
    /*
    | Full path to the web server access log for this domain. Leave empty to
    | auto-detect cPanel's ~/access-logs/<domain>-ssl_log.
    */
    'access_log' => env('REQUEST_MONITOR_ACCESS_LOG'),

    // Seconds a snapshot is reused so several open monitor tabs don't re-read the logs.
    'cache_seconds' => (int) env('REQUEST_MONITOR_CACHE_SECONDS', 15),
];
