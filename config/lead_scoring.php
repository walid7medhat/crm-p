<?php

return [
    'weights' => [
        'budget' => 30,
        'whatsapp' => 15,
        'email' => 10,
        'source' => 10,
        'recency' => 20,
        'stage' => 5,
    ],
    'thresholds' => [
        'hot' => 80,
        'warm' => 50,
    ],
    'rules' => [
        'budget_high_value' => 1000000,
        'budget_mid_value' => 300000,
        'recency_hours' => 48,
    ],
    'automation' => [
        'on_create' => true,
        'on_update' => true,
        'scheduled_enabled' => true,
    ],
    'ai_mode' => 'fallback',

    /*
    |--------------------------------------------------------------------------
    | Kanban free-text search (temporary benchmark switch)
    |--------------------------------------------------------------------------
    |
    | When true, Kanban search skips ROW_NUMBER / activity ranking, analytics,
    | and duplicate/activity post-processing. Filtering stays the same; results
    | use a simple id order. Set KANBAN_SEARCH_SKIP_RANKING=false to restore.
    |
    */
    'kanban_search' => [
        'skip_ranking' => (bool) env('KANBAN_SEARCH_SKIP_RANKING', true),
    ],
];
