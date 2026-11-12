# CRM Full-System Performance Audit Report — Phase 1 (Read-Only)

**Date:** 2026-11-12  
**Scope:** Phase 1 complete performance audit only — **no application code, migrations, config, or production changes**.  
**Stack:** Laravel 11 / PHP 8.4 / Vue 3 SPA / MySQL / Spatie Permission + Activity Log / JWT / Pusher Echo  
**Auditor mode:** Code inspection + schema/migration review + local config verification + reuse of prior Phase 0 baseline measurements

---

## 1. Executive summary

The CRM already has meaningful prior performance work (kanban card resources, phone digits indexing, Smart Search lean mode, Studio dashboard consolidation, listing grid cache, frontend AbortController/debounce on several search paths). Remaining slowness under concurrent usage is driven by a smaller set of **verified** patterns:

1. Leading-wildcard multi-column `%LIKE%` search under role/branch visibility (scales with lead volume).
2. Lead popup request fan-out + correlated comment visibility SQL.
3. Dashboard / Sales Action Center multi-COUNT query shapes.
4. Large deal-list and map payloads.
5. File cache + database queue + dense scheduler under multi-user load.
6. Always-on kanban SQL query logging.

**Critical measurement caveat:** Local DB currently has ~36 leads / 264 users. Warm local API timings are therefore **not representative of production**. Historical local search on a larger effective corpus measured **~3.6–4.0s** for `search=ahmed`. Production timings require a staging clone measurement before claiming targets met.

---

## 2. Audit method and evidence classes

| Evidence class | Meaning |
|----------------|---------|
| **Verified in code** | Current source inspected this pass |
| **Measured local (tiny DB)** | Phase 0 baseline 2026-11-08 + spot-check counts 2026-11-12 |
| **Historical local** | Prior search report with multi-second timings |
| **Inferred / production needed** | Expected at scale; not re-measured on production |

Prior docs cross-checked (claims verified or marked stale):  
`OIA_PHASE_0` … `OIA_PHASE_9`, `LEAD_SEARCH_OPTIMIZATION_REPORT.md`, `OIA_DEAL_KANBAN_QUERY_AUDIT.md`, `docs/performance-baseline-phase0.md`.

### Local environment (this machine)

| Item | Value |
|------|-------|
| Dataset | leads≈36, deals=31, listings=1720, users=264, lead_comments=210, lead_histories=1994 |
| Cache driver | `file` |
| Queue driver | `database` |
| Session driver | `file` |
| `APP_DEBUG` | `true` (Studio cache bypassed) |

---

## 3. Hot-path inventory

### 3.1 Lead search (highest priority)

| Endpoint / component | Current response time | SQL queries | Slowest SQL / pattern | Missing indexes | Root cause | Recommended solution | Risk |
|---|---|---|---|---|---|---|---|
| `GET /api/kanban/stages-with-leads?search=` → `StageController::getStagesWithLeads` + `LeadTextSearch` · FE navbar/`leads.vue` | Local tiny DB ~60–65 ms; **Historical** `ahmed` **3623–3984 ms avg** | Search path ~30 (local); board path **73** | Leading `%term%` OR across `lead_name`, `first_name`, `last_name`, `email`, `work_phone`, `work_phone_2`, `lead_source` (+ optional relations) | BTREE name/email indexes **exist but cannot help leading `%`**. Candidate: **FULLTEXT** on identity fields; prefix strategy | Index-hostile multi-column OR under visibility scope | Prefix/FULLTEXT for identity; tighten phone legacy OR; keep two-phase ID hydrate | **High** (behavior) |
| Phone search via `LeadTextSearch::applyPhone` | Local ~57 ms | — | Indexed `work_phone_digits` seek **plus** legacy `%LIKE%` OR on `work_phone`, `work_phone_2`, `whatsapp_number`, `lead_number`, `source_client_phone` | Digits index present; secondary phone columns still scanned via LIKE | Legacy fallback keeps full-scan path | Prefer digits/prefix; normalize secondary phones | Med |
| `GET /api/leads?search=` Lead Pool | Local ~45 ms; Historical `dubai` ~1.0–1.2s | Paginated card path | Same `LeadTextSearch` lean `%LIKE%` | Same as above | Same text OR under branch pool visibility | Same search strategy as kanban | **High** |
| `GET /api/smart-search` · `SmartSearchService` + `useSmartSearch.js` | Local ~54 ms | Cap 5 + lean | Still lean `%LIKE%` for names; email equality OK; All-tab = 2 sequential requests | Same FULLTEXT opportunity | Generally well-shaped; residual LIKE cost | Keep progressive UX; share text strategy | Low–Med |
| `GET /api/leads/clients` `getClientsList` | Not re-baselined | — | Ad-hoc `%LIKE%` (no `LeadTextSearch`, no digits path) | Same | Divergent unoptimized sibling | Route through `LeadTextSearch` | Med |
| Export `LeadSearchExport` | Admin/export | — | `LOWER(COALESCE(col,'')) LIKE %term%` | Cannot use indexes | Index-hostile export search | Align with shared search / FULLTEXT | Med |

**Frontend search hygiene (verified):**

| Surface | Debounce | Abort | Notes |
|---------|----------|-------|-------|
| Navbar | 120 ms | via board | Fingerprint dedupe; debug logs on keystroke |
| `leads.vue` | 300 ms | AbortController | Stacked with navbar debounce |
| Lead Pool | relies on navbar | AbortController | Double loading event on first load |
| Smart Search | 160 ms | AbortController + seq | All tab: leads then deals/listings/places |

**Evidence:** `app/Services/LeadTextSearch.php:120–129`, `app/Http/Controllers/Api/StageController.php:258`, `416–455`, `832–838`, `app/Http/Controllers/Api/LeadController.php:291–298`, `2768–2775`.

---

### 3.2 Lead listing / Kanban

| Endpoint / component | Current response time | SQL queries | Slowest SQL / pattern | Missing indexes | Root cause | Recommended solution | Risk |
|---|---|---|---|---|---|---|---|
| `GET /api/kanban/stages-with-leads` (no search) | Local ~54–74 ms | **73** (sales local) | Per-stage first-page ID queries + counts + heat + **always-on query log** | Kanban composites largely present (`2026_10_06_*`); optional `(responsible_person_id, stage_id, deleted_at, bitrix24_last_activity_at, id)` for scoped boards | N× stage ID fetches + logging overhead | Gate/sample query log; consolidate first-page IDs; soft totals on load-more | Low–Med |
| `GET …/stage/{stage}/more-leads` | — | Includes `paginate()` COUNT on non-search | Exact count per column scroll | Existing stage indexes | Soft totals not used on load-more | Prefer `limit+1` / `simplePaginate` | Low |
| `GET /api/leads` pool | Local fast | Light card + `simplePaginate` | Branch visibility OR | Optional composite `(lead_branch_source, responsible_person_id, stage_id, deleted_at)` | Mostly remediated | Keep card resource; avoid `grouped=1` | Low |
| Drag stage change `LeadController::changeStage` | Write path | Large method | Heavy post-update work | — | Business-critical path still heavy | Profile; move non-critical side effects to jobs only if UX allows | Med |

**Evidence:** `StageController.php:249–258`, `638–657`, `1591–1609`; `KanbanLeadCardResource`; Phase 0 query count **73**.

---

### 3.3 Individual lead details

| Endpoint / component | Current response time | SQL queries | Slowest SQL / pattern | Missing indexes | Root cause | Recommended solution | Risk |
|---|---|---|---|---|---|---|---|
| Open lead fan-out: `history/view` + `show` + portal-links + comments (+ deferred merged/history/matches) | Local sum of medians ~87 ms API; **prod unknown**; `history/view` p95 **215 ms** | show ~18 queries local | Multiple RTTs; full show payload includes `raw_meta_data` / Facebook Q&A | — | Progressive UX still stacks requests | Slim fast-show; gate meta; client dedupe | Low–Med |
| `LeadResource` always calls `resolveAssignmentHistory()` | Included in show | Extra `lead_histories` query | Assignment history lookup even when fast-show skips other work | `(lead_id, created_at)` + action filtering helpers | Ungated history resolve | Gate behind `!$fastShow` or seed from card | Low |
| `GET /leads/{id}/comments` via `Lead::visibleEngagement` | Local tiny ~20 ms | COUNT under visibility predicate | **Correlated** `lead_histories` JSON extract for owner-at-comment-time | Expression/generated column or materialized owner timeline | Correct visibility rule, expensive SQL | Precompute owner timeline / batch assignment intervals **without changing rules** | **Med** (correctness) |
| `GET /leads/{id}/matches` cold | Fast when cached | — | Sync `refreshLead` on cache miss (matcher scores inline) | Match tables exist | Blocking refresh in HTTP | Return stale/cached first; queue refresh job | Low–Med |
| Portal links LIKE scan | Separate call | LIMIT 50 LIKE | Leading-wildcard comment URL search | Extracted links table / FULLTEXT comments | `%url%` scan | Store extracted portal URLs | Low |

**Evidence:** `Lead.php:344–407`, `LeadResource.php:205`, `654–665`, `useLeadViewModal.js`, `ViewLeadModal.vue`, `LeadMatchController` sync refresh, `App.vue` eager modal.

---

### 3.4 Dashboard

| Endpoint / component | Current response time | SQL queries | Slowest SQL / pattern | Missing indexes | Root cause | Recommended solution | Risk |
|---|---|---|---|---|---|---|---|
| `GET /dashboard/studio` (home) | Local sales ~53 ms; admin cold **320 ms** / p95 **172 ms** | **52** queries | Dozens of separate COUNT/SUM in `buildStudioPayload` | `created_at` / kanban heat indexes present | Sequential micro-aggregates; cache **skipped when `app.debug`** | Consolidate KPI `selectRaw`; ensure prod `APP_DEBUG=false`; short TTL cache | Med |
| Legacy `getChartDataByPeriod` monthly | Used by listings-statistics path | **Up to 30 COUNTs** | Per-day `whereDate()->count()` loop | Index on `listings.created_at` composites help but N queries remain | Pre-GROUP-BY helper never migrated | Reuse `buildDailyTrend` GROUP BY | **Low** |
| `GET /dashboard/top-agent` | — | Full `User::pluck('id')` | All-users hierarchy then withCount | — | Role scoping commented out | Restore scope; LIMIT in SQL | Med |
| Analytics bands CRM+Deals+Listing+HR | 4 parallel FE calls | Deals/listing multi-COUNT; CRM already consolidated | Duplicate `computeConvertedSalesMetrics` across CRM+Deals bands | — | Shared metrics not cached across split endpoints | Shared sales-metrics cache key | Low–Med |
| `GET /sidebar/counts` | Local ~15 ms | Multi COUNT | Hierarchy listing/access counts | — | Already 45s cache + 60s FE poll | Optional CASE consolidation | Low |
| Activity log search in Dashboard | — | — | `description LIKE` + `CAST(properties AS CHAR) LIKE` | No FULLTEXT on activity_log | Unindexed text/JSON search | Restrict search fields / FULLTEXT description only | Med |

**Evidence:** `DashboardController.php:447–497`, `860`, `2111–2120`, `2137–2365`; `useDashboardHome.js`; `useAnalyticsDashboard.js`.

---

### 3.5 Sales Action Center

| Endpoint / component | Current response time | SQL queries | Slowest SQL / pattern | Missing indexes | Root cause | Recommended solution | Risk |
|---|---|---|---|---|---|---|---|
| `GET /sales-action-center` summary | Cached 45s/user | **9–10** independent `categoryQuery()->count()` | Visibility + `whereNotExists` on activities + COALESCE date predicates × categories | Follow-up index on lead_activities exists; may still be EXISTS-heavy | Rule engine as N COUNT queries | Single-pass CASE classification; keep short TTL | Med |
| `with_lists=1` on open | Extra HTTP after summary | Page query × non-zero categories | List + activities + notes per category | — | Prefetch all columns on open | Lazy per-category (hover already exists) | Low–Med |

**Evidence:** `app/Services/SalesAction/SalesActionCenterService.php:82–98`; `SalesActionCenter.vue` prefetch/open/`with_lists`.

---

### 3.6 Deals & listings

| Endpoint / component | Current response time | SQL queries | Slowest SQL / pattern | Missing indexes | Root cause | Recommended solution | Risk |
|---|---|---|---|---|---|---|---|
| `GET /deals` index | Local **~99–109 ms**, **418–514 KB** | **60** | Deep `properties.area.parent×5`, parties, thumbs; `DealProperty::$with` | Deal composites present | List still over-fetches area tree after removing full detail graph | Flatten area label; trim parties; denormalize display fields | Med |
| Deals kanban grouped | Local ~40 ms / ~14.5 KB | ~34 | Stages double-load; all parties; unused lead cols | Present | Card hydrate not fully lean | Stage map; primary buyer only; trim columns | Low |
| Listings grid | Local ~15 ms / 1 query on cache hit | Cached 1800s | — | Present | Healthy — preserve | Keep | — |
| Matching listings | Local ~59 ms / ~33 KB | EXISTS + relaxation loops | Full `galleryImages` eager | — | Detail-ish match payload | First image only; single ranked query | Low–Med |
| Property map | Local map@500 ~70 ms / **280 KB**; FE requests **`per_page: 3000`** | Heavy joins + coordinate resolve | Default backend 2500 / max 3000 | lat/lng index exists | Full inventory pins without bbox-first | Lower default; require bbox; slim DTO | Med |
| Listing detail `BlogOne.vue` | Large FE chunk **255 KB**; SFC ~14.5k LOC | Full gallery | — | — | Mega component + full gallery | Progressive gallery; tab split (no redesign of UX) | Med |

**Evidence:** `DealController::index`, `PropertyMap.vue:633`, listing matching controller path, Phase 0 payload table.

---

### 3.7 Frontend architecture

| Finding | Evidence | Impact | Risk |
|---------|----------|--------|------|
| Shell CSS **2.84 MB** + main JS **1.07 MB** | Phase 0 build assets | Slow first paint for all users | Med (bundle work) |
| ApexCharts chunk 568 KB | Build | Heavy when analytics imported | Low–Med |
| Eager `ViewLeadModal` in `App.vue` | Code comment ~5s historical async feel | Trades bundle for open speed | Keep for now |
| Residual raw `import axios from 'axios'` in some widgets | Multiple Vue files | Auth/baseURL consistency risk more than latency | Low |
| Activity/comment duplicate watchers | **Fixed** in current lists | — | — |
| Legacy dashboard / ListingsDashboard multi-widget fan-out | `useDashboardHome`, Ai widgets | Extra parallel APIs if those routes used | Low if Studio is primary home |

---

### 3.8 Infrastructure / jobs / cache

| Finding | Evidence | Impact | Risk |
|---------|----------|--------|------|
| Cache=`file`, Queue=`database`, Session=`file` | Runtime config this pass | File cache + DB queue contend under concurrency; not Redis | **High** under 100–200 users |
| Redis configured but not selected; queue falls back if Redis missing | `QueueConnectionResolver`, `InfrastructureBootstrap` | Safe fallback, but no Redis performance win today | — |
| `leads:score` scheduled **twice** every 10 minutes | `bootstrap/app.php:106–117` | Duplicate scoring work | Low fix |
| `leads:recover-stuck --sync` every 5 min | `bootstrap/app.php:138–140` | Scheduler process does inline DB work | Med |
| Dense every-minute assign/reminder jobs | Same schedule block | Constant background DB load | Med |
| Kanban `DB::enableQueryLog()` always on | `StageController:258` | Memory + log I/O on every board/search | Med |
| Notifications ordered by `created_at` without dedicated index | Morph index only | Slower notification inbox as table grows | Low–Med |
| OPcache | Enabled in CLI PHP build; **no in-repo production php-fpm/OPcache docs** | Production worker/OPcache settings unverified | Needs server inspection |

---

## 4. Database index status (summary)

### Strong (already present on leads)

- Kanban covering / sort indexes (`2026_10_06_*`, heat, counts)
- Phone digits + work_phone indexes
- Name BTREE indexes (`lead_name`, `first_name`, `last_name`) — useful for prefix/equality, **not** leading `%LIKE%`
- Email index, branch/source/filter composites, `created_at`

### Gaps / opportunities (do not create blindly)

| Candidate | Why | Notes |
|-----------|-----|-------|
| FULLTEXT on lead identity fields | Leading `%LIKE%` cannot use BTREE | Highest search impact; test relevance vs current behavior |
| Notifications `(notifiable_type, notifiable_id, created_at)` / unread helpers | Inbox `orderBy created_at` | Low risk |
| `deal_comments (deal_id, created_at)` | Parity with lead_comments | Low risk |
| `deal_activities (deal_id, is_completed, reminder_date)` | Parity with lead follow-up index | Low risk |
| `(is_completed, next_reminder_at)` on activities | Reminder cron scan | Low risk |
| activity_log FULLTEXT on `description` only | Avoid CAST JSON LIKE | Med — admin logs |
| Generated/materialized assignment timeline for comments | Speeds `visibleEngagement` | **Higher design risk** — protect business rules |

**Do not** add more BTREE indexes expecting to fix leading-wildcard search — they will not.

---

## 5. Prioritized bottleneck list (Top 10)

| # | Bottleneck | Exact files | Slow SQL / pattern | Est. impact |
|---|------------|-------------|--------------------|-------------|
| 1 | Leading `%LIKE%` multi-column lead text search | `app/Services/LeadTextSearch.php`, `StageController.php`, `LeadController.php` | `WHERE lead_name LIKE '%x%' OR first_name LIKE …` | **Critical** at prod scale |
| 2 | Lead open multi-request fan-out + heavy show meta | `useLeadViewModal.js`, `ViewLeadModal.vue`, `Lead/LeadResource.php`, `LeadController.php` | Multiple endpoints; assignment history; raw meta | High perceived latency |
| 3 | `visibleEngagement` correlated history JSON SQL | `app/Models/Lead.php:344–407`, `LeadActivityController.php` | Correlated `json_extract` on `lead_histories` | High as comments grow |
| 4 | Kanban always-on query log + N stage ID queries | `StageController.php:258`, `638–657`, `1591–1609` | 73 queries local board | Med–High under concurrency |
| 5 | Sales Action Center N× COUNT EXISTS | `SalesAction/SalesActionCenterService.php:82–98` | 9–10 category counts | High for SAC users |
| 6 | Studio / dashboard multi-aggregate + chart N+1 | `DashboardController.php` (`buildStudioPayload`, `getChartDataByPeriod`) | 30× daily COUNT; 52 studio queries | High on dashboard |
| 7 | Deals list over-fetch (~400–500 KB) | `DealController.php`, `DealProperty.php`, `DealResource` | Deep area parents + parties | High bandwidth/CPU |
| 8 | Map `per_page: 3000` + matching full galleries | `PropertyMap.vue:633`, listing match controller | Large pin/gallery payloads | High on listings |
| 9 | File cache + DB queue + dense/duplicate schedule | `config/*`, `bootstrap/app.php`, runtime drivers | Background + lock contention | High at 100–200 users |
| 10 | Sync lead match refresh on cold open | `LeadMatchController`, matcher service | Inline scoring in HTTP | Med |

---

## 6. What is already healthy (preserve)

- Smart Search shape (cap 5, lean, abort, progressive modules).
- Kanban card N+1 largely fixed (`KanbanLeadCardResource` batch priming).
- Lead pool `simplePaginate` + card resource path.
- Listing grid response cache (1800s) — local cache hit = 1 query.
- Studio as single home endpoint (vs legacy multi-widget) when cache enabled.
- Phone digits indexed path (primary phone strategy).
- Frontend AbortController on kanban/pool/smart search.
- Activity/comment duplicate watcher fix already landed.

---

## 7. Changes implemented in this phase

**None.** Phase 1 is read-only.

---

## 8. Changes not implemented (and why)

All optimization work deferred until findings are reviewed and a scoped Phase 2+ plan is approved. Especially deferred:

- FULLTEXT / search strategy changes (behavior risk)
- `visibleEngagement` rewrite (permission/visibility risk)
- Redis cutover (infra/ops risk; Spatie permission cache coherence)
- Production php-fpm / MySQL buffer changes (needs live server metrics)
- Aggressive load tests against production (**forbidden**)

---

## 9. Before / after measurements

| Metric | Before (this audit) | After |
|--------|---------------------|-------|
| Code changes | Baseline documented | N/A — no changes |
| Local Phase 0 timings | See §10 / `OIA_PHASE_0_PERFORMANCE_BASELINE.md` | Pending Phase 10 |
| Production p50/p95 | **Not measured** | Required on staging clone |

---

## 10. Reference local baseline (sales warm, tiny DB)

Source: `OIA_PHASE_0_PERFORMANCE_BASELINE.md` (2026-11-08).

| Endpoint | avg ms | p95 | queries | bytes |
|----------|-------:|----:|--------:|------:|
| kanban_board | 53.7 | 67.4 | **73** | 17 KB |
| kanban_search_ahmed | 64.3 | 72.0 | 30 | 17 KB |
| smart_search | 54.2 | 65.9 | — | 1.7 KB |
| lead_show | 26.5 | 33.8 | 18 | 4.4 KB |
| lead_history_view | 62.2 | **215** | — | 78 B |
| dashboard_studio | 52.9 | 55.8 | 52 | 3.3 KB |
| deals_index | **99.3** | 104 | **60** | **418 KB** |
| listings_map_500 | 70.3 | 72 | — | **280 KB** |

Historical search (larger corpus): kanban `ahmed` **~3.6–4.0 s**.

---

## 11. Remaining infrastructure limitations

- Local/staging dataset ≪ production lead volume → latency targets cannot be certified from this machine alone.
- Cache/queue not Redis; file/DB drivers will limit concurrent responsiveness.
- Production PHP-FPM worker math, MySQL buffer pool, slow query log, and OPcache settings are **not verified** from hosting (requires approved server access).
- ~50 newly added users increase concurrent session/permission/cache pressure even before lead-volume growth.

---

## 12. Safe next steps (approval required)

1. Review this report and approve Phase 2 scope (indexes + search strategy first).
2. Measure critical endpoints on a **staging DB clone** with realistic lead volume (EXPLAIN ANALYZE where safe).
3. Implement only approved, small, reviewable changes.
4. Re-baseline; then proceed Dashboard/SAC/Frontend/Infra phases.
5. Never run aggressive load tests or migrations on production without explicit approval.

---

## 13. Rollback instructions

N/A for Phase 1 (no changes). Future phases must ship with migration down methods, feature flags where useful, and documented revert steps in `docs/PERFORMANCE_DEPLOYMENT_GUIDE.md`.
