# Lead Search Optimization Report

## Scope Executed

This pass focused on high-safety performance fixes that do not alter lead-search business logic or filter behavior:

- Frontend request de-duplication and race-condition prevention.
- Frontend debounce tuning for smoother typing.
- Database indexing for common lead and history filters.
- Runtime benchmarks for key lead-search endpoints.

## Files Changed

- `resources/js/components/layout/navbar/index.vue`
- `resources/js/components/kanban/kanban_deal.vue`
- `resources/js/components/kanban/leadList/LeadPool.vue`
- `resources/js/components/kanban/leadList/leads.vue`
- `resources/js/components/kanban/leadList/LeadSearchModal.vue`
- `database/migrations/2026_07_28_130000_add_search_indexes_for_leads.php`

## Frontend Optimizations

### 1) Search debounce and duplicate trigger prevention
- Reduced navbar debounce to `300ms`.
- Added silent search setter to prevent programmatic updates from retriggering the search watcher.
- Removed direct `fetchLeads()` calls from navbar lead-search handler; events now drive updates through the board container (single source of truth).

### 2) Event listener duplication cleanup
- Consolidated Kanban search listeners into stable named handlers.
- Removed duplicate listener registration in `onMounted`.
- Fixed `removeEventListener` cleanup by using the same function references.

### 3) Lead Pool request cancellation and race handling
- Added `AbortController` cancellation to `/leads` requests in `LeadPool.vue`.
- Aborted previous in-flight request before issuing the next one.
- Ignored cancelled-request errors cleanly.
- Added unmount cleanup for pending request abort.

### 4) Kanban lead fetch latest-wins behavior
- Updated `leads.vue` fetch flow to avoid dropping newer user intents while a request is active.
- Existing abort flow now always allows newest query to execute.

### 5) Lead Search Modal watcher dedupe
- Removed duplicate `watch(() => props.modelValue, ...)` blocks to avoid redundant sync work.

## SQL / Index Optimizations

Migration added targeted indexes:

### `leads`
- `leads_stage_updated_idx (stage_id, updated_at)`
- `leads_stage_created_idx (stage_id, created_at)`
- `leads_resp_stage_updated_idx (responsible_person_id, stage_id, updated_at)`
- `leads_source_idx (lead_source)`
- `leads_status_lead_idx (status_lead)`
- `leads_interaction_result_idx (interaction_result)`
- `leads_branch_source_idx (lead_branch_source)`
- `leads_type_property_status_idx (lead_type, property_status)`
- `leads_property_area_idx (property_type_id, area_id)`
- `leads_budget_range_idx (budget_from, budget_to)`

### `lead_histories`
- `lead_histories_lead_user_created_idx (lead_id, user_id, created_at)`
- `lead_histories_user_created_idx (user_id, created_at)`

### `lead_comments`
- `lead_comments_lead_created_idx (lead_id, created_at)`

## Benchmark Snapshot (Local)

Test method: authenticated `curl` calls, 5 runs each, measured wall time.

### Before frontend/backend code changes in this pass
- `GET /api/stages/kanban/stages-with-leads?per_page=20&search=ahmed`
  - avg: **3623.8ms**, p95: **3820.5ms**, payload: **672.5KB**
- `GET /api/stages/kanban/stages-with-leads?per_page=20&stage_id=1&responsible_person_id=30&created_from=2026-01-01&created_to=2026-07-28`
  - avg: **20.3ms**, p95: **22.7ms**, payload: **0.2KB**
- `GET /api/leads?stage_id=10&paginate=1&page=1&per_page=24&search=dubai`
  - avg: **1042.2ms**, p95: **1221.8ms**, payload: **301.7KB**

### After final applied changes
- `GET /api/stages/kanban/stages-with-leads?per_page=20&search=ahmed`
  - avg: **3984.1ms**, p95: **4778.4ms**, payload: **672.5KB**
- `GET /api/stages/kanban/stages-with-leads?per_page=20&stage_id=1&responsible_person_id=30&created_from=2026-01-01&created_to=2026-07-28`
  - avg: **21.6ms**, p95: **26.3ms**, payload: **0.2KB**
- `GET /api/leads?stage_id=10&paginate=1&page=1&per_page=24&search=dubai`
  - avg: **1018.2ms**, p95: **1213.1ms**, payload: **301.7KB**

## Interpretation

- Indexed/targeted filters are already fast (near the target class of performance).
- Broad free-text search remains the major bottleneck and is dominated by:
  - multi-column `%LIKE%` scanning,
  - relation `whereHas` OR chains,
  - large payload/resource serialization.
- Frontend changes reduce duplicate calls and stale-result races, improving perceived responsiveness under typing/filter changes even when backend is still heavy.

## Remaining Bottlenecks

1. `LIKE "%term%"` OR across many lead columns.
2. Search OR clauses across related models (`comments`, `responsiblePerson`, `stage`, `propertyType`, `integration`).
3. Large response payload size on kanban/global search.
4. Duplicate filter logic across `LeadController` and `StageController` (harder to optimize consistently).
5. Per-lead expensive fields in `LeadResource` on lead-pool path.

## Next Optimization Phase (Recommended)

1. Extract shared lead-search query builder (single source for all filters and text search).
2. Introduce two-phase search:
   - phase A: resolve matching lead IDs (minimal columns),
   - phase B: fetch display payload for only those IDs.
3. Replace expensive relation `whereHas` OR blocks with targeted joins/subqueries where safe.
4. Add optional lightweight card resource for list endpoints (`/leads` paginate and kanban list) while preserving response compatibility.
5. Introduce search profile guardrails:
   - cap broad-term result windows,
   - log slow queries (`DB::listen` / telescope / query log sampling),
   - track payload size and SQL count per endpoint.

## Scale Recommendations

### 100k+ leads
- Keep current indexes + shared search builder refactor.
- Add endpoint-level query budget monitoring and slow-query alerts.

### 500k+ leads
- Move free-text to dedicated full-text strategy (`FULLTEXT`/external search service), keep exact filters in MySQL.
- Add read replicas for search-heavy traffic.

### 1M+ leads
- Introduce search service layer (OpenSearch/Elasticsearch/Meilisearch) with sync pipeline.
- Keep Laravel/MySQL for transactional consistency and exact relational filters.
- Use incremental denormalized search documents for multi-field + multi-relation text search.

