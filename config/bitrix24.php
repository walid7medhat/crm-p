<?php

$webhook = env('BITRIX24_WEBHOOK_URL');
if (is_string($webhook) && $webhook !== '') {
    $webhook = rtrim($webhook, '/') . '/';
}

return [
    'webhook_url' => $webhook ?: null,
    'http_timeout' => (int) env('BITRIX24_HTTP_TIMEOUT', 60),
    'api_max_retries' => max(1, min(10, (int) env('BITRIX24_API_MAX_RETRIES', 8))),
    'api_retry_base_ms' => max(200, (int) env('BITRIX24_API_RETRY_BASE_MS', 500)),
    'api_retry_max_ms' => max(1000, (int) env('BITRIX24_API_RETRY_MAX_MS', 180000)),
    'max_pages_per_request' => max(1, min(50, (int) env('BITRIX24_MAX_PAGES_PER_REQUEST', 50))),

    /** Dedicated queue for sync jobs (run Horizon/supervisor workers on this queue). */
    'queue' => env('BITRIX24_SYNC_QUEUE', 'bitrix24'),

    /**
     * Bitrix24 returns max 50 leads per crm.lead.list page (API limit).
     * Each job run fetches this many consecutive pages before chaining.
     * 20 pages ≈ 1000 leads per job. Tune 10–40 via BITRIX24_PAGES_PER_JOB.
     */
    'pages_per_job' => max(1, min(50, (int) env('BITRIX24_PAGES_PER_JOB', 20))),

    /** Max pages per job when importing with full enrichment (skip_existing=false). */
    'pages_per_job_enrich_max' => max(1, min(10, (int) env('BITRIX24_PAGES_PER_JOB_ENRICH_MAX', 2))),

    /** Flush sync progress to DB every N leads (live counter on monitor UI). */
    'progress_report_every' => max(1, min(50, (int) env('BITRIX24_PROGRESS_REPORT_EVERY', 5))),

    /** Rows inserted per DB::table()->insert() chunk. */
    'db_insert_chunk' => max(50, min(2000, (int) env('BITRIX24_DB_INSERT_CHUNK', 500))),

    /**
     * Parallel shard workers (ID-range split). Set BITRIX24_PARALLEL_SHARDS=20
     * for large imports. Use 1 for legacy single-worker sequential mode.
     */
    'parallel_shards' => max(1, min(100, (int) env('BITRIX24_PARALLEL_SHARDS', 1))),

    /**
     * When true (default for skip_existing), new leads are bulk-inserted without
     * per-lead comment/activity/timeline API calls. Re-run fetchOne or a future
     * enrichment pass for full timeline on specific leads.
     */
    'fast_skip_existing' => (bool) env('BITRIX24_FAST_SKIP_EXISTING', true),

    /** Enrich existing leads (stage sync + timeline) when not skipping. */
    'enrich_existing' => (bool) env('BITRIX24_ENRICH_EXISTING', true),

    /** Legacy alias — maps to pages_per_job if set. */
    'batch_size' => max(1, min(50, (int) env('BITRIX24_SYNC_BATCH_SIZE', 20))),
];
