# CRM Performance Optimization Plan

**Status:** Draft for approval after Phase 1 audit  
**Source:** `docs/PERFORMANCE_AUDIT_REPORT.md`  
**Rule:** No production deploy, migration, or infra change without explicit approval. Preserve all business logic, permissions, branch/tenant isolation, stages, integrations, and audit trails.

---

## 1. Principles

1. Small, reviewable phases — one concern per PR where practical.
2. Measure on staging clone before/after (never aggressive prod load tests).
3. Prefer query-shape and payload fixes over broad refactors.
4. Do not add BTREE indexes hoping to fix leading `%LIKE%`.
5. Never share user/branch-scoped caches across unauthorized users.
6. Do not move user-critical writes to queues if the UI needs immediate consistency.

---

## 2. Highest impact / lowest risk first

| Priority | Item | Impact | Risk | Phase |
|----------|------|--------|------|-------|
| P0 | Gate/sample kanban `DB::enableQueryLog()` | Med | **Very low** | 4 |
| P0 | Remove duplicate `leads:score` schedule entry | Low–Med | **Very low** | 6 |
| P0 | Fix chart N+1 (`getChartDataByPeriod` → GROUP BY) | Med | Low | 5 |
| P0 | Map FE `per_page` 3000 → lower + bbox-first | High | Low–Med | 8 |
| P1 | Lead search strategy (prefix/FULLTEXT + phone fallback tighten) | **Critical** | Med | 3 |
| P1 | Slim lead fast-show / gate `resolveAssignmentHistory` / meta | High | Low | 4 |
| P1 | Matching: first gallery image + async refresh | Med–High | Low | 4/6 |
| P1 | SAC single-pass category counts / lazy lists | High | Med | 5 |
| P2 | Studio KPI consolidation + confirm prod cache | High | Med | 5 |
| P2 | Deals list area-tree slim | High | Med | 4 |
| P2 | `visibleEngagement` rewrite without rule change | High | **High** | 4 |
| P2 | Index parity (notifications, deal comments/activities) | Med | Low | 2 |
| P3 | Redis cache/queue evaluation (only if hosting supports) | High | Med–High | 7/9 |
| P3 | Frontend shell weight / virtualization where proven | Med | Med | 8 |
| P3 | PHP-FPM / MySQL / OPcache tuning from real metrics | High | Med | 9 |

---

## 3. Phase plan (aligned to mission)

### Phase 2 — Database optimization (after approval)

**Deliverable:** `docs/DATABASE_INDEX_RECOMMENDATIONS.md` + safe migrations for approved indexes only.

Proposed first index batch (low risk, evidence-backed):

1. `notifications` composite for notifiable + `created_at` (and unread helper if needed).
2. `deal_comments (deal_id, created_at)`.
3. `deal_activities` follow-up composite parity with leads.
4. Activity reminder scan composite `(is_completed, next_reminder_at)` if EXPLAIN supports it.

Search FULLTEXT:

- Design only after EXPLAIN on staging clone with production-like data.
- Preserve visibility/permissions in application layer (FULLTEXT does not replace scopes).
- Online DDL plan for large `leads` table; test on clone; no production drop of existing indexes.

### Phase 3 — Lead search (highest priority)

1. Keep FE debounce/abort (already good); remove debug keystroke noise; avoid double-fetch races.
2. Tighten `LeadTextSearch` phone path: prefer `work_phone_digits`; reduce legacy leading `%` OR.
3. Evaluate prefix match (`term%`) vs contains for name fields; FULLTEXT where product requires contains.
4. Route `getClientsList` / export through shared search helper.
5. Preserve roles, branch, stages, filters.

**Success criteria (staging):** search suggestions p95 &lt; 300 ms on realistic data for common name/phone queries.

### Phase 4 — Lead loading / Kanban

1. Gate kanban query log behind env/sample/slow-only.
2. Consolidate non-search first-page stage IDs where sort semantics allow.
3. Soft totals on load-more.
4. Lead show: gate assignment history under fast-show; omit unused meta by default.
5. Comments: only rewrite `visibleEngagement` with explicit rule-preservation tests.
6. Matches: cached response first; queue refresh job on miss.
7. Keep stage IDs and assignment logic unchanged.

**Success criteria:** lead open primary API p95 &lt; 500 ms; fewer than ~4 blocking requests for first useful paint.

### Phase 5 — Dashboard / SAC

1. Replace chart day loops with GROUP BY.
2. Collapse Studio KPI aggregates; ensure cache active when `APP_DEBUG=false`.
3. Shared cache for `computeConvertedSalesMetrics`.
4. Scope `getTopAgentPerformance` (remove full `User::pluck`).
5. SAC: single-pass category classification; lazy lists instead of bulk `with_lists`.

**Success criteria:** common dashboard APIs p95 &lt; 500 ms on staging; Studio useful on first paint.

### Phase 6 — Background jobs / queues

1. Deduplicate `leads:score` schedule.
2. Move recover-stuck off `--sync` only if safe/idempotent queued path exists.
3. Queue cold match refresh and non-critical analytics.
4. Do **not** queue assignment, stage change, or permission-critical writes users wait on.
5. Inspect actual hosting before recommending Redis workers.

### Phase 7 — Caching strategy

1. Keep short TTLs for sidebar/SAC/studio with user/branch/role in keys.
2. Evaluate Redis only after confirming process availability and Spatie permission cache cutover plan.
3. Never cache unauthorized lead result sets across users.

### Phase 8 — Frontend

1. Lower map pin default; bbox after first paint.
2. Matching gallery slim; catalog composable for areas/types/developers.
3. Prefer Studio over legacy multi-widget home where product allows.
4. Virtualize only proven large lists.
5. No UI redesign; no feature removal.

### Phase 9 — Production server

Requires approved access to hosting metrics:

- PHP-FPM workers from RAM/CPU formula (not blind increase)
- OPcache validate
- MySQL buffer pool / connections / slow query log
- Queue worker count vs memory
- Cron overlap audit

### Phase 10 — Performance testing

Deliverable: `docs/PERFORMANCE_BENCHMARKS.md`

- Staging clone only
- Concurrency: 1 / 10 / 25 / 50 / (100 if safe)
- p50/p95/p99, errors, query counts, DB CPU, PHP saturation, queue backlog
- Suites: search, list, kanban, lead open, dashboard, SAC, common actions

---

## 4. Proposed implementation order (first sprint after approval)

1. **Quick wins PR:** kanban query-log gate, duplicate schedule fix, chart GROUP BY, map `per_page` reduction.  
2. **Search PR:** phone fallback tighten + shared search path + staging EXPLAIN + optional FULLTEXT migration (clone-tested).  
3. **Lead open PR:** fast-show slim + async matches.  
4. **SAC/Studio PR:** aggregate consolidation.  
5. **Deals list PR:** payload slim.  
6. **Infra review:** Redis/FPM/MySQL only with ops approval.

---

## 5. Explicit non-goals

- No lead stage ID / assignment / merge / Bitrix / Meta / notification rule changes.
- No broad Vue rewrites (`leads.vue`, `BlogOne.vue` mega splits only if narrowly justified later).
- No production `.env` edits without approval.
- No dropping indexes without explicit approval.
- No sacrificing correctness for benchmarks.

---

## 6. Deliverables tracker

| Document | Status |
|----------|--------|
| `docs/PERFORMANCE_AUDIT_REPORT.md` | **Done (Phase 1)** |
| `docs/PERFORMANCE_OPTIMIZATION_PLAN.md` | **Done (this file)** |
| `docs/DATABASE_INDEX_RECOMMENDATIONS.md` | Pending Phase 2 (EXPLAIN + approval) |
| `docs/PERFORMANCE_BENCHMARKS.md` | Pending Phase 10 (staging measurements) |
| `docs/PERFORMANCE_DEPLOYMENT_GUIDE.md` | Pending first implementation PR |

---

## 7. Approval gate

**Stop here until the Phase 1 findings are reviewed.**

Please confirm which tranche to implement first:

- **A)** Quick wins only (query log, schedule, chart, map)  
- **B)** A + Lead search strategy (recommended)  
- **C)** Full Phase 2–5 scope as sequenced above  

No code beyond documentation will be changed until you approve a scope.
