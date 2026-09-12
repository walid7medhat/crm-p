<?php

/**
 * AI Lead Intelligence — Phase 3 (deterministic aggregation).
 * Phase 4 LLM settings intentionally omitted.
 */
return [

    'enabled' => (bool) env('AI_LEAD_INTELLIGENCE_ENABLED', true),

    'batch_size' => (int) env('AI_LEAD_INTELLIGENCE_BATCH_SIZE', 40),

    /*
    |--------------------------------------------------------------------------
    | Comment / activity / history windows (Phase 4 context prep)
    |--------------------------------------------------------------------------
    */
    'comments' => [
        'recent_limit' => 10,
        'max_chars_per_comment' => 500,
    ],

    'activities' => [
        'recent_limit' => 10,
    ],

    'histories' => [
        'recent_limit' => 15,
    ],

    'property_matches' => [
        'limit' => 5,
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard list caps (compact payload)
    |--------------------------------------------------------------------------
    */
    'dashboard' => [
        'priority_limit' => 25,
        'at_risk_limit' => 25,
        'property_opportunities_limit' => 25,
        'neglect_group_limit' => 15,
        'today_actions_limit' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Inactivity / neglect urgency (days since last meaningful activity)
    | Aligns with AiSI default neglect_inactive_days = 7 for "needs_attention".
    |--------------------------------------------------------------------------
    */
    'inactivity' => [
        'monitor_days' => (int) env('ALI_INACTIVITY_MONITOR_DAYS', 3),
        'needs_attention_days' => (int) env('ALI_INACTIVITY_NEEDS_ATTENTION_DAYS', 7),
        'critical_days' => (int) env('ALI_INACTIVITY_CRITICAL_DAYS', 14),
    ],

    /*
    |--------------------------------------------------------------------------
    | Priority / intent thresholds (read existing LeadIntelligence fields)
    |--------------------------------------------------------------------------
    */
    'priority_score_min' => 80,
    'high_intent_values' => ['high', 'strong_buying_intent', 'strong_renting_intent'],

    'queue' => env('AI_LEAD_INTELLIGENCE_QUEUE', 'default'),
];
