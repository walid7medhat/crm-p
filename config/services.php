<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'nominatim' => [
        'base_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org/search'),
        'user_agent' => env('NOMINATIM_USER_AGENT', 'crm-property-map/1.0 (admin@example.com)'),
        'default_city' => env('NOMINATIM_DEFAULT_CITY', 'Dubai'),
        'default_country' => env('NOMINATIM_DEFAULT_COUNTRY', 'UAE'),
        'retries' => env('NOMINATIM_RETRIES', 3),
        /** Comma-separated ISO 3166-1 alpha-2 (e.g. ae). Empty = no filter. */
        'countrycodes' => env('NOMINATIM_COUNTRYCODES', 'ae'),
        /** Cap geocode fallback attempts per map request (cache hits + optional sync). */
        'map_max_geocode_fallback_per_request' => (int) env('MAP_MAX_GEOCODE_FALLBACK', 40),
        /**
         * When false (default), map API only uses geocoding_caches — never calls Nominatim during the request.
         * Set true only for local debugging (slow, rate-limited).
         */
        'map_allow_sync_geocode' => filter_var(env('MAP_ALLOW_SYNC_GEOCODE', false), FILTER_VALIDATE_BOOLEAN),
    ],

];
