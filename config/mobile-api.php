<?php

return [
    'version' => 'v1',
    /** Seconds — short TTL; kanban payload is user-specific */
    'kanban_cache_ttl' => (int) env('MOBILE_KANBAN_CACHE_TTL', 60),
    /** Idempotent move responses (seconds) */
    'move_idempotency_ttl' => (int) env('MOBILE_MOVE_IDEMPOTENCY_TTL', 86400),
];
