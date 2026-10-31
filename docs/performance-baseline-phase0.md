# Phase 0 Performance Baseline (2026-10-31)

Method: static request-path counting from source (pre-change). Browser Network / Telescope timings were not captured against a logged-in session in this step; code-path counts are the reproducible baseline for Opt 1–2.

## Existing test results (pre-change)

| Suite | Result |
|-------|--------|
| `tests/Feature/Deal/DealDetailPerformanceTest.php` | 2 passed |
| `tests/Feature/Deal/DealKanbanOptimizationTest.php` | 6 passed, **3 failed** (pre-existing; assignment parent / single-user query assertions) |

Failures are unrelated to CompleteStageFieldsModal / Activity-Comment work and are recorded as baseline debt.

## Code-path API request counts (before)

### Deals Kanban board load (`Deals.vue` mounts `CompleteStageFieldsModal` always)

While modal **closed**, `CompleteStageFieldsModal.onMounted` fires:

1. `GET /listings/areas`
2. `GET /available-responsible-persons?limit=30`
3. `GET /sources`
4. `GET /listings/property-types`
5. `GET /listings/developers`

**Baseline closed-modal reference requests: 5** (plus board’s own `GET /deals/grouped-by-stage`).

### View Deal — Activity tab open

`DealActivityList`:

- `watch(keyDelete, { immediate: true })` → `GET /deals/{id}/activities`
- `onMounted` → `GET /deals/{id}/activities` again

**Baseline: 2 identical activity requests per Activity tab mount.**

### View Deal — Comments tab open

`DealCommentList`:

- `watch(key, { immediate: true })` → fetch
- `watch(dealId, { immediate: true })` → fetch
- `onMounted` → fetch

**Baseline: 3 identical comment requests per Comments tab mount.**

### View Lead — Activity tab

`ActivityList` + `:key-delete="activityListKey"`:

- `watch(keyDelete, { immediate: true })` → fetch
- `onMounted` → fetch
- `watch(leadId)` (non-immediate) — no extra on first mount unless id changes

**Baseline: 2 identical activity requests per Activity tab mount.**

### View Lead — Comments tab

`CommentList` uses `watch(leadId, { immediate: true })` only (no duplicate onMounted).

**Baseline: 1 comment request** (already OK). Refresh after create increments Vue `:key` / remount — preserved.

## Target after Opt 1–2

| Interaction | Before | After |
|-------------|--------|-------|
| Deals board load, CompleteStageFields closed | +5 ref APIs | **+0** |
| CompleteStageFields first open | +0 extra (already warm) / or partial | **+5 once** (areas, users, sources, types, developers) then cached |
| Deal Activity tab open | 2 | **1** |
| Deal Comments tab open | 3 | **1** |
| Lead Activity tab open | 2 | **1** |
| Lead Comments tab open | 1 | **1** (unchanged) |

## After Opt 1–2 (verified 2026-10-31)

Static source verification:

- `CompleteStageFieldsModal.onMounted`: **0** reference `fetch*` calls (style injection only).
- `ensureReferenceDataLoaded()` runs only when `props.show === true`; loads areas + users + sources + property-types + developers once, with in-flight dedupe.
- Activity/Comment lists: single `*Id` watch with `{ immediate: true }`; `key`/`keyDelete` refresh without `immediate`; no `onMounted` fetch.

| Interaction | Before (code-path) | After (code-path) | SQL / TTFB |
|-------------|--------------------|-------------------|------------|
| Deals board, modal closed | +5 APIs | **+0** | Not measured in browser (auth session); request elimination is deterministic from source |
| CompleteStageFields first open | cached from board mount | **+5 once**, then cached while component stays mounted | Same endpoints as before, deferred until open |
| Deal Activity tab | 2× activities | **1×** | −1 identical GET (same SQL once instead of twice) |
| Deal Comments tab | 3× comments | **1×** | −2 identical GETs |
| Lead Activity tab | 2× activities | **1×** | −1 identical GET |
| Lead Comments tab | 1× | **1×** | unchanged |

Tests after change:

- `DealDetailPerformanceTest`: **PASS** (2)
- `npm run build`: **PASS** (22.82s)

**Stopped here** — Deals list N+1, Leads unbounded queries, and Notifications not started (awaiting approval of this report).
