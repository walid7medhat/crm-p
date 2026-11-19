# OIA CRM — Phase 2 Performance Report

**Date:** 2026-11-19  
**Continues from:** `OIA_LEAD_OPEN_PERFORMANCE_OPTIMIZATION_REPORT.md` (Phase 1 — not reverted)  
**Rule:** No feature removal; visibility/permissions/API contracts preserved.

---

## 1. Remaining bottlenecks (entering Phase 2)

| # | Bottleneck | Why it mattered |
|---|------------|-----------------|
| 1 | `Lead::visibleEngagement` correlated history subquery | Per-comment/activity dependent subquery + per-row `leads` lookup + JSON extract; filesort on histories |
| 2 | Kanban `firstPageLeadIdsByStage` N queries | One limited ID query per visible stage |
| 3 | Kanban FE prefetch discarded | `page:1` failed plain-params check → second full board GET; mismatch also cleared `pending` |
| 4 | Card hierarchy lazy walks | Inactive creator / office parent chains without `User::warmHierarchy` |
| 5 | No production-like volume harness | Phase 1 measured ~33–53 leads only |
| 6 | Phase 1 SWR race | In-flight GET could overwrite a newer local edit |

---

## 2. Root causes

### visibleEngagement
- Owner-at-time uses “first `assigned` history after row” via correlated subquery on `lead_histories`.
- Prior SQL also re-read `leads.responsible_person_id` per row.
- Histories filtered by `lead_id` + `created_at` order lacked a matching composite index (`lead_id, created_at, id`).
- JSON `changes->action` still cannot use a dedicated action index (left for a later verified migration).

### Kanban
- Normal board path intentionally avoided full-table `ROW_NUMBER` (sort risk) but paid N stage round-trips.
- Prefetch was designed to share the first board request; `leads.vue` always sends `page:1`, so the shared promise was dropped and a duplicate HTTP call ran the full ~70+ query path again.

### Phase 1 verification finding
- `setCachedLead` always wrote network payloads with `Date.now()`, so a slower GET could clobber a fresher edit that landed during flight.

---

## 3. Exact files modified

### Backend
- `app/Models/Lead.php` — `visibleEngagement` constant-fold + direct `old_person_id` extract
- `app/Http/Controllers/Api/StageController.php` — UNION ALL first-page IDs; `User::warmHierarchy` before card resolve
- `database/migrations/2026_11_19_120000_add_lead_histories_lead_created_id_index.php` — additive index
- `database/seeders/PerfSyntheticLeadVolumeSeeder.php` — synthetic staging volume (non-PII)
- `tests/Feature/LeadVisibleEngagementParityTest.php` — legacy SQL parity guard
- `scripts/perf/phase2_staging_benchmark.php` — staging HTTP benchmark harness

### Frontend
- `resources/js/composables/leadBoardPrefetch.js` — accept `page:1`; do not clear pending on mismatch
- `resources/js/composables/leadShowCache.js` — refuse older overwrites of newer cache entries

### Docs
- `OIA_PHASE2_PERFORMANCE_REPORT.md` *(this file)*

**Not modified (Phase 1 preserved):** `primeForFastShow`, `leadReady` gating, matching deferral, assignment-status / push-config dedupe, presence 30s poll, chat hidden-tab pause.

---

## 4. SQL optimizations

### visibleEngagement (semantics unchanged)
1. Constant-fold `lead_id` and current `responsible_person_id` (single-lead `hasMany`).
2. Select `json_extract(... old_person_id)` directly instead of selecting full `changes` then extracting in the outer expression.
3. Remove per-row `(select responsible_person_id from leads …)` subquery.
4. Additive index `lead_histories_lead_created_id_idx (lead_id, created_at, id)`.

Admin/super_admin path still `withTrashed()->latest()` (unchanged).

### Kanban first-page IDs
- Replace N limited selects with one `UNION ALL` of parenthesized `(SELECT … ORDER BY … LIMIT)` branches — same per-stage order as `getMoreStageLeads` / prior loop.
- Fallback to the old N-query loop if UNION fails.

---

## 5. Before / after measurements

### Important caveat
Local DB during this pass: **~53 leads**, **~210 comments**, **~2k histories**.  
**Do not treat local timings as production proof.** Staging harness + synthetic seeder are provided for volume runs.

### visibleEngagement (lead 2371, 70 comments, local)

| Variant | Notes | Result |
|---------|-------|--------|
| Legacy correlated SQL (pre-change formula) | EXPLAIN: histories `filesort`, ~53 rows estimate; wall ~71 ms (1 sample) | Baseline formula |
| Optimized + new index | EXPLAIN uses `lead_histories_lead_created_id_idx`; **p50 4.36 ms / p95 7.89 ms** (20 samples, raw SQL) | Faster plan shape |
| Eloquent admin comments | Fast path (no engagement filter) | ~3.2 ms |
| Eloquent sales comments | Optimized filter | ~12.8 ms (0 rows for that owner — visibility intact) |
| Parity test | Sales IDs vs legacy SQL | **PASS** (`LeadVisibleEngagementParityTest`) |

### Kanban `stages-with-leads` (local super_admin, warm)

| Metric | Phase 0 historical | Phase 2 local warm (n=10) |
|--------|--------------------|---------------------------|
| Query count | ~73 (sales, Phase 0) | **p50/p95 = 26** |
| Wall time | ~54–74 ms (tiny DB) | **p50 44.2 ms / p95 50.8 ms** |
| UNION ALL first-page IDs | N/A (N loops) | **Present (1 union query)** |

FE: default board prefetch now matches `per_page=15&page=1` and no longer discards `pending` on filtered fetches — removes an unnecessary **second full board HTTP** on first Kanban paint.

---

## 6. Production-like benchmark methodology

### Principles
- Prefer **staging** with **synthetic** or **approved-anonymized** data.
- Never copy raw production PII without an approved anonymization process.
- Refuse non-local hosts in the harness unless `ALLOW_REMOTE=1`.

### Volume seed (staging / local non-prod only)
```bash
TARGET_LEADS=10000 COMMENTS_PER_LEAD=2 \
  php artisan db:seed --class=PerfSyntheticLeadVolumeSeeder

# Optional larger targets
TARGET_LEADS=30000 php artisan db:seed --class=PerfSyntheticLeadVolumeSeeder
TARGET_LEADS=80000 php artisan db:seed --class=PerfSyntheticLeadVolumeSeeder
```
Seeder refuses `production` environment. Phones/emails are synthetic (`@example.test`).

### Suggested role matrix
| Role | What to measure |
|------|-----------------|
| Sales | Lead show, comments (engagement SQL), kanban own-scope |
| Branch admin | Branch-scoped board + engagement |
| Admin / Super Admin | Full board, show, comments (`withTrashed`) |

### HTTP harness
```bash
BASE_URL=https://staging.example.com \
JWT=... \
LEAD_ID=... \
SAMPLES=15 \
ALLOW_REMOTE=1 \
ENDPOINTS=lead_show,comments,kanban,history,matches \
php scripts/perf/phase2_staging_benchmark.php
```
Reports p50 / p95 / p99 / avg ms and avg bytes to stdout + `/tmp/oia_phase2_benchmark_*.json`.

### Browser metrics (manual staging)
- Lead open: time to essential fields visible; time to interactive; revisit (SWR).
- Kanban: first contentful board; confirm **one** `stages-with-leads` for default load (Network).
- Background: presence ≤1/30s when visible; chat unread paused when hidden.

---

## 7. p50 / p95 / p99 results

### Available now (LOCAL only)
| Endpoint / SQL | n | p50 | p95 | p99 | Notes |
|----------------|--:|----:|----:|----:|-------|
| Optimized engagement raw SQL | 20 | 4.36 ms | 7.89 ms | ≈ last sample | Tiny DB |
| Kanban board (in-process) | 10 | 44.2 ms | 50.8 ms | — | Tiny DB; query count 26 |

### Not available yet (must run on staging volume)
| Metric | Status |
|--------|--------|
| Lead Details API @ 10k / 30k / 80k leads | **Pending staging run** |
| Comments engagement @ realistic history depth | **Pending** |
| Kanban @ multi-branch production-like cardinality | **Pending** |
| Click-to-useful / TTI in Chrome | **Pending authenticated browser session** |

---

## 8. Regression test results

| Test | Result |
|------|--------|
| `tests/Feature/LeadVisibleEngagementParityTest` | **PASS** (legacy SQL ID parity + visibility expectations) |
| `tests/Feature/LiveWatch/LiveWatchFeatureTest` (presence filter) | **PASS** |
| Broader LeadPool / LeadMerge suites | Known pre-existing fixture issues (not introduced here); not re-claimed as green |

---

## 9. Remaining risks

1. **JSON `action` filter** still not indexable — very large `lead_histories` per lead can still hurt engagement; consider a stored generated `action` column in a later approved migration.
2. **UNION ALL** first-page IDs: fallback exists, but extreme query-builder edge cases should be watched in staging logs (`kanban firstPageLeadIdsByStage union failed`).
3. **Heat / exact COUNT** scans remain on every normal board load — dominant at 80k+ leads even after ID consolidation.
4. **`visibleEngagement` still correlated** — constant-fold + index help; not a full denormalized owner-at-time column.
5. **Synthetic seeder** inserts minimal columns — staging schemas with extra NOT NULL columns may need seeder extension before 80k runs.
6. Local timings **must not** be marketed as production gains.

---

## 10. Deployment instructions

1. Deploy backend + frontend assets together.
2. Run migration (additive index only):
   ```bash
   php artisan migrate --path=database/migrations/2026_11_19_120000_add_lead_histories_lead_created_id_index.php
   ```
3. On large production tables, schedule the index build in a low-traffic window (`ALGORITHM=INPLACE` is MySQL-default for secondary indexes in modern versions; still monitor lock time).
4. Smoke:
   - Sales: open lead → comments match prior visibility (own + owned-at-time).
   - Kanban first load: single `stages-with-leads` for default params; stage order/counts unchanged; load-more still works.
   - Edit lead while open → network refresh must not wipe the edit (SWR newer-wins).
5. Staging: seed synthetic volume → run `phase2_staging_benchmark.php` → attach JSON to this report’s follow-up.

---

## Phase 1 verification checklist

| Item | Status |
|------|--------|
| `primeForFastShow` | Present in `LeadResource` / used by `LeadController::show` |
| Per-user SWR cache | Present; **fixed** older-overwrite race |
| Seed paint | Unchanged (`initialLead` / seed merge) |
| `leadReady` | Still gates `GeneralTab` secondary rails |
| Matching deferral | Still defers search when ≥6 scored rows |
| Assignment-status dedupe | Shared composable + BE cache intact |
| Push config dedupe | Shared composable intact |
| Presence polling | 30s + snapshot cache intact |
| Hidden-tab chat polling | Pause/resume intact |
| Server-side permissions | `canViewLead` / engagement rules unchanged; cache is per `userId` key only |

---

## Ultimate objective status

Essential Lead Details under **1s in production** remains the goal. Phase 2 removes major engagement/kanban multipliers and closes a cache race, but **staging volume benchmarks are required** before claiming production p95.
