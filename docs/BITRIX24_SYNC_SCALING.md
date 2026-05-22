# Bitrix24 lead sync — scaling guide

## Local vs production (queue / Redis)

| Environment | `QUEUE_CONNECTION` | Redis |
|-------------|-------------------|--------|
| **Mac local (no Redis)** | `database` | Optional — app auto-falls back if `redis` is set but unreachable |
| **Production** | `redis` (recommended) | Required for best throughput |

Run health check:

```bash
php artisan bitrix24:doctor
php artisan config:clear && php artisan cache:clear && php artisan optimize:clear
```

Local worker:

```bash
php artisan queue:work database --queue=bitrix24,default --timeout=900 --memory=256 --tries=2
```

If `.env` has `QUEUE_CONNECTION=redis` but Redis is not running, bootstrap resolves to `database` automatically (see `App\Support\QueueConnectionResolver`).

## Architecture (optimized)

| Layer | Behavior |
|-------|----------|
| **API** | `crm.lead.list` — max **50 leads/page** (Bitrix24 hard limit). Each job fetches `BITRIX24_PAGES_PER_JOB` pages (default **20** ≈ **1000 leads/job**). |
| **Sequential mode** | `SyncBitrix24LeadsJob` self-chains with saved `cursor` in `bitrix_sync_states`. |
| **Parallel mode** | `BITRIX24_PARALLEL_SHARDS=20` splits ID space into shards; `SyncBitrix24ShardJob` × N via `Bus::batch()`. |
| **DB** | `importBatch()` + `bulkInsertLeads()` — query builder `insert()`, batched `whereIn` dedup (chunks of 2000). |
| **Fast path** | `skip_existing` + `BITRIX24_FAST_SKIP_EXISTING=true` — bulk insert only, no per-lead timeline API. |

## Environment variables

```env
BITRIX24_SYNC_QUEUE=bitrix24
BITRIX24_PAGES_PER_JOB=20          # 20 × 50 = 1000 leads per job run
BITRIX24_DB_INSERT_CHUNK=500
BITRIX24_PARALLEL_SHARDS=20        # 1 = legacy single worker
BITRIX24_FAST_SKIP_EXISTING=true
BITRIX24_ENRICH_EXISTING=true      # full timeline when not skipping
BITRIX24_HTTP_TIMEOUT=60
```

## Database indexes (migration `2026_05_23_100000_bitrix_sync_performance`)

- `leads.bitrix24_id` — dedup lookups
- `leads.email`, `leads.work_phone`, `leads.created_at` — reporting / matching
- `lead_histories (lead_id, bitrix24_id)` — timeline dedup

## Horizon (`config/horizon.php` snippet)

```php
'environments' => [
    'production' => [
        'bitrix24-supervisor' => [
            'connection'   => 'redis',
            'queue'        => ['bitrix24'],
            'balance'      => 'auto',
            'minProcesses' => 10,
            'maxProcesses' => 30,
            'tries'        => 2,
            'timeout'      => 900,
            'memory'       => 256,
        ],
        // default supervisor for other queues...
    ],
],
```

## Supervisor (no Horizon)

```ini
[program:crm-bitrix24-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work redis --queue=bitrix24 --sleep=1 --timeout=900 --memory=256 --tries=2
numprocs=20
autostart=true
autorestart=true
stopwaitsecs=920
```

## Worker count recommendations

| Dataset | Sequential (`PARALLEL_SHARDS=1`) | Parallel (`PARALLEL_SHARDS=20`) |
|---------|----------------------------------|----------------------------------|
| **160k leads** | 4–8 workers on `bitrix24` queue | **20 workers** (1 per shard) + Redis |
| **1M leads** | 8–12 workers, `PAGES_PER_JOB=30` | **30–50 workers**, `PARALLEL_SHARDS=40` |

With **skip existing** enabled, expect roughly **50–200 leads/sec** (bulk insert, no timeline API) vs ~1–3 leads/sec before optimization.

## Resume

- **Sequential**: `cursor`, counters preserved on `cancelled` / `failed`; start again uses Resume.
- **Parallel**: `bitrix_sync_shards` rows keep per-shard `cursor`; incomplete shards re-dispatched on resume.

## API (unchanged)

- `POST /api/leads/bitrix24/start-queue`
- `GET /api/bitrix24/queue-status` — adds optional `leads_per_sec`, `eta_seconds`, `parallel_shards`, `sync_mode` (Vue ignores extra fields).

## Full enrichment after fast bulk

Use `POST /api/leads/bitrix24/fetch/{bitrixId}` for a single lead’s comments, activities, and timeline.
