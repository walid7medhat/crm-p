<?php

return [
    /** Property Finder Enterprise API gateway. */
    'base_url' => rtrim(env('PF_BASE_URL', 'https://atlas.propertyfinder.com'), '/'),

    /** From the PF Enterprise dashboard (key = 40 chars, secret = 32 chars). Scopes: leads:read, webhooks:full_access. */
    'api_key' => env('PF_API_KEY'),
    'api_secret' => env('PF_API_SECRET'),

    /**
     * HMAC secret sent to PF when subscribing (max 32 chars). PF signs each delivery with it
     * in the X-Signature header; deliveries with a wrong signature are rejected.
     */
    'webhook_secret' => env('PF_WEBHOOK_SECRET'),

    /** Public HTTPS URL PF posts to. Defaults to APP_URL/api/propertyfinder/webhook. */
    'webhook_url' => env('PF_WEBHOOK_URL'),

    /** Events subscribed by `php artisan propertyfinder:webhooks subscribe`. */
    'events' => ['lead.created', 'lead.updated', 'lead.assigned'],

    /**
     * Only leads created in PF at/after this moment are imported — older leads are never
     * pulled in (not by the sync, not by an update/assign webhook on an old lead).
     * Empty → set automatically the first time the integration runs (see
     * PropertyFinderLeadImporter::importFrom()). Always UAE time, e.g. 2026-10-05 09:00:00.
     */
    'import_from' => env('PF_IMPORT_FROM'),

    /** added_by, and responsible_person_id when the emirate is not Dubai / Abu Dhabi. */
    'system_user_id' => (int) env('PF_SYSTEM_USER_ID', 1),

    /** responsible_person_id per branch (from the enquired property's emirate). */
    'branch_users' => [
        'Dubai' => (int) env('PF_DUBAI_USER_ID', 59),
        'Abu Dhabi' => (int) env('PF_ABU_DHABI_USER_ID', 25),
    ],

    /** Queue for webhook processing jobs. */
    'queue' => env('PF_QUEUE', 'default'),

    'http_timeout' => (int) env('PF_HTTP_TIMEOUT', 20),
];
