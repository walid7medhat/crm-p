<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies (Cloudflare)
    |--------------------------------------------------------------------------
    | Only Cloudflare edge CIDRs are trusted for X-Forwarded-* headers.
    | Ranges are loaded from an on-disk snapshot refreshed from Cloudflare's
    | official IP lists — never from trustProxies('*').
    |
    | Official sources:
    |   https://www.cloudflare.com/ips-v4
    |   https://www.cloudflare.com/ips-v6
    */

    'ipv4_url' => 'https://www.cloudflare.com/ips-v4',
    'ipv6_url' => 'https://www.cloudflare.com/ips-v6',

    /** Snapshot written by `php artisan cloudflare:refresh-ips`. */
    'snapshot_path' => storage_path('app/cloudflare/ips.json'),

    /** Bundled fallback shipped with the app (also refreshed by the artisan command). */
    'bundled_snapshot_path' => resource_path('security/cloudflare_ips.json'),

    'cache_key' => 'cloudflare.trusted_proxy_cidrs',
    'cache_ttl_seconds' => 86_400,

    /**
     * Optional comma-separated CIDR override (tests / emergency).
     * Never set to "*" — that value is rejected.
     */
    'env_override' => env('TRUSTED_PROXIES'),

];
