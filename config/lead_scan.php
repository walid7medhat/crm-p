<?php

return [

    /*
    | Search provider. "brave" uses the Brave Search API. Any other value, or a
    | missing key, leaves scans unconfigured. The app never invents results.
    |
    | Sending a lead's name, and sometimes an email or phone, to this provider
    | is an external transfer. Enable it only after the provider terms and the
    | company's UAE personal-data review allow that transfer.
    */
    'provider' => env('LEAD_SCAN_PROVIDER', ''),

    'api_key' => env('LEAD_SCAN_API_KEY'),

    'endpoint' => env('LEAD_SCAN_ENDPOINT', 'https://api.search.brave.com/res/v1/web/search'),

    'timeout' => (int) env('LEAD_SCAN_TIMEOUT', 12),

    'results_per_query' => 8,

    'max_queries' => 3,

    /*
    | A completed scan is reused until this many hours have passed, so a second
    | click does not call the paid API again. Pass force=1 to scan again.
    */
    'fresh_hours' => (int) env('LEAD_SCAN_FRESH_HOURS', 24),

    'retain_days' => (int) env('LEAD_SCAN_RETAIN_DAYS', 90),

];
