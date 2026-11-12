# Phase 2.5-B / 2.5-B.1 — Lead Kanban Search & Filter Request Optimization

**Date:** 2026-11-12  
**Status:** 2.5-B.1 remediation applied locally — **awaiting review** (no production deploy / no prod DB migrations)

---

## 2.5-B.1 remediation (verification blockers)

### BLOCKER 1 — Stacked debounce (fixed)

| Path | Debounce owner | Board fetch |
|------|----------------|-------------|
| Modal text (`lead_name` / `first_name` / `work_phone` / `email`) | `LeadSearchModal` **350ms** | `fetchLeads(true, …)` immediate |
| Navbar free-text | `navbar` **350ms** | `fetchLeads(true, …)` via `kanban-lead-search` |
| Dates / dropdowns / Search click | None (immediate UX) | `fetchLeads(true, …)` |
| Legacy `immediate=false` callers | `leads.vue` 350ms only | Unchanged fallback |

**Before:** modal/navbar 350ms → `fetchLeads(false)` → board +350ms ≈ **700ms**  
**After:** one **~350ms** text debounce, then immediate board request.

### BLOCKER 2 — Fingerprint (fixed)

Moved to `leads.vue` `buildBoardFetchFingerprint(params)` over the **effective API params**:

- All `buildLeadSearchApiParams` keys (text, assigned dates, agent/branch, stage, heat, toggles, …)
- `page` (board fetch uses `1`)
- `per_page`

Behavior:

- Skip only when **same fingerprint is already in flight** (duplicate watcher emits).
- After completion, identical filters may be **re-applied**.
- Explicit Search emits `force: true` → always refetch (aborts prior).
- Newer different search always wins (`fetchGeneration` + AbortController).

### Agent dashboard chart audit

| Path | Status |
|------|--------|
| Listing charts (`getChartDataByPeriod` / date range) | Already Phase 1 `fillListingChartFromDailyCounts` GROUP BY |
| Studio `leadsByArea` | Already single `GROUP BY area_id` |
| `getActiveAgents` weekly series | **Was** 7× `whereDate()->count()`; **now** 1× `GROUP BY DATE` + zero-fill (hierarchy preserved) |

No production index added. No staging timing claims.

---

## Request flow (after 2.5-B.1)

```text
type in modal text
  → wait 350ms (LeadSearchModal)
  → emit search (keepOpen)
  → navbar onLeadSearch → kanban-lead-search-update
  → kanban_deal onLeadSearch → fetchLeads(true, query)
  → executeFetchLeads (in-flight fp dedupe) → GET stages-with-leads

type in navbar box
  → wait 350ms (SEARCH_DEBOUNCE_MS)
  → kanban-lead-search → fetchLeads(true, query)

date / dropdown / Search button
  → immediate emit (force on Search)
  → fetchLeads(true, query, { force })
```

---

## Files changed (2.5-B + 2.5-B.1)

- `resources/js/components/layout/navbar/index.vue`
- `resources/js/components/kanban/leadList/LeadSearchModal.vue`
- `resources/js/components/kanban/leadList/leads.vue`
- `resources/js/components/kanban/kanban_deal.vue`
- `app/Http/Controllers/Api/StageController.php`
- `app/Http/Controllers/Api/DashboardController.php` (`getActiveAgents` weekly GROUP BY)
- `tests/Unit/KanbanFilterRequestGuardsTest.php`
- This doc

---

## Tests / deploy

```text
php artisan test --filter=KanbanFilterRequestGuardsTest
npm run build
```

Deploy only after approval. No DB migrations. Rollback = revert listed files / prior assets.

## STOP

Awaiting review before further phases or production deploy.
