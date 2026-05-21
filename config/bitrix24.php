<?php

return [
    'webhook_url' => env('BITRIX24_WEBHOOK_URL'),
    'batch_size'  => (int) env('BITRIX24_SYNC_BATCH_SIZE', 25),
    'http_timeout' => (int) env('BITRIX24_HTTP_TIMEOUT', 30),
];
