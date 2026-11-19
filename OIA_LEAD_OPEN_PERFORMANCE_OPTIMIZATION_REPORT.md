# OIA CRM — Lead Open & CRM Speed Optimization Report

**Date:** 2026-11-19  
**Scope:** Incremental, behavior-preserving performance work focused on Lead Details open speed, duplicate requests, and background API overhead.  
**Rule:** No features removed; permissions, visibility, and API contracts preserved.

---

## 1. Root causes of slowness

| # | Root cause | Evidence |
|---|------------|----------|
| 1 | Lead open fan-out races critical path | `history/view` ∥ `GET /leads/{id}` then comments/matching start from seed before show settles (`GeneralTab`, `ViewLeadModal`) |
| 2 | `LeadController::show` still ran assignment + last-activity history lookups per request | `LeadResource::resolveAssignmentHistory` + `ResolvesLeadLastActivity` (JSON `changes->action` scans) |
| 3 | Observers serialized via full `UserResource` on fast show | `LeadObserverResource` → admin_parent/office walks |
| 4 | Matching always ran `/matches` then `/listings/matching` sequentially | `MatchingPropertiesSection.fetchMatches` |
| 5 | Duplicate background GETs | Lead Pool `assignment-status` (Kanban shell + tab); `MobilePushToggle` config × multiple mounts; Live Watch presence every 12s |
| 6 | Chat unread used expensive double `whereHas` | `ChatController::unreadCount` |
| 7 | Presence snapshot reconciled on every poll | `LiveWatchPresenceService::presenceSnapshot` |

**Note:** Local DB (~33 leads) cannot reproduce production multi-second timings. Code-path bottlenecks above are confirmed; production p95 still needs staging measurement.

---

## 2. Exact files modified

### Backend
- `app/Http/Controllers/Api/LeadController.php`
- `app/Http/Resources/Lead/LeadResource.php`
- `app/Http/Resources/Lead/LeadObserverResource.php`
- `app/Services/LeadPoolAssignmentService.php`
- `app/Http/Controllers/Api/LeadPoolSettingsController.php`
- `app/Http/Controllers/Api/ChatController.php`
- `app/Services/LiveWatch/LiveWatchPresenceService.php`

### Frontend
- `resources/js/composables/requestDedupe.js` *(new)*
- `resources/js/composables/leadShowCache.js` *(new)*
- `resources/js/composables/leadPoolAssignmentStatus.js` *(new)*
- `resources/js/composables/pushSubscriptionConfig.js` *(new)*
- `resources/js/composables/useLeadViewModal.js`
- `resources/js/components/kanban/viewLead/ViewLeadModal.vue`
- `resources/js/components/kanban/viewLead/GeneralTab.vue`
- `resources/js/components/kanban/viewLead/MatchingPropertiesSection.vue`
- `resources/js/components/kanban/kanban_deal.vue`
- `resources/js/components/kanban/leadList/LeadPool.vue`
- `resources/js/components/layout/MobilePushToggle.vue`
- `resources/js/components/chat/ChatFloatingButton.vue`
- `resources/js/components/live-watch/LiveWatchOnlineAlert.vue`

### Docs
- `OIA_LEAD_OPEN_PERFORMANCE_OPTIMIZATION_REPORT.md` *(this file)*

---

## 3. Backend optimizations

1. **`LeadResource::primeForFastShow($lead)`** — primes assignment history + optional last-activity history + Bitrix activity user map before resource resolve; skips unused duplicate/blacklist scans on modal open.
2. **Deeper area eager-load** — `area.parent.parent.parent` avoids `Area::title` N+1.
3. **Observers on fast show** — identity-only payload (same shape as `formatLeadUser`), not full `UserResource`.
4. **Lead Pool assignment-status** — 8s per-user `Cache::remember`; invalidated on `createAssignment` and settings `apply()`.
5. **Chat unread** — pivot-first conversation IDs + 15s per-user cache; forgotten on read/send.
6. **Live Watch presence** — 3s shared snapshot cache; forgotten on presence broadcast / monitored-user change.

---

## 4. Frontend optimizations

1. **Per-user in-memory SWR lead cache** (`leadShowCache.js`) — instant paint on revisit; background refresh; invalidated/updated on edits; keyed by `userId:leadId`.
2. **Request dedupe helper** — shared in-flight promises for identical keys.
3. **`leadReady` gate** — comments/merged/timeline wait until `GET /leads/{id}` (or cache) hydrates so engagement SQL does not race show.
4. **Matching** — if ≥6 scored rows, defer `/listings/matching` to idle.
5. **Assignment-status + push config** — module-level dedupe/TTL shared across mounts.
6. **Chat poll** — pauses while tab hidden; resumes + refresh on visible.
7. **Live Watch poll** — 12s → 30s (Echo remains primary).

---

## 5. Database optimizations and migrations

- **No migrations added** in this pass.
- Existing `lead_histories` indexes (`lead_id`, `lead_user_created_idx`) already support primed lookups.
- A generated/virtual column + index on `changes->action` remains a candidate for a later verified migration (JSON extract still appears in `createdHistory` / assignment primes under load).

---

## 6. Duplicate requests eliminated

| Request | Fix |
|---------|-----|
| `GET /lead-pool/assignment-status` (Kanban + Lead Pool) | Shared FE dedupe/TTL + BE 8s user cache |
| `GET /push-subscriptions/config` (multi mount) | Shared FE TTL cache |
| `GET /leads/{id}` (re-open / concurrent) | In-flight dedupe + SWR cache |
| Live Watch presence chatter | Poll 30s + 3s snapshot cache |
| Chat unread while tab hidden | Poll paused |

Kanban `stages-with-leads` already had blade/JS prefetch + in-flight fingerprint; left intact (Echo-fallback 15s poll only when Echo fails).

---

## 7. Before / after measurements (LOCAL)

Environment: `APP_ENV=local`, ~33 leads, PHP 8.4, file cache. **Not production.**

| Metric | Before | After | Notes |
|--------|--------|-------|-------|
| `GET /leads/{id}` (super_admin, warm) | ~30.7 ms, 11 queries, 4.7 KB | ~26.7 ms, 11 queries, 4.7 KB | Tiny dataset; query *shape* improved (primed history) |
| Lead revisit (FE) | Always network | Instant cache paint + optional refresh | Perceived open |
| Comments start | On seed (~idle 500ms) | After lead hydrated | Protects show path |
| Live Watch poll | 12s | 30s | Echo still live |
| Assignment-status double GET | 2 on Lead Pool tab | 1 shared | |

**Production targets still pending staging timings** for show p95, kanban, presence, notifications.

---

## 8. Tests executed and results

| Suite | Result |
|-------|--------|
| `tests/Feature/LiveWatch/LiveWatchFeatureTest.php` | **11 passed** |
| Broader LeadPool / LeadMerge / DealDetailPerformance / KanbanIndex filters | Failures observed appear **pre-existing** (stage PK collisions, deal query budget, index name mismatch, pool permission fixtures) — not introduced by this diff |
| Frontend production build | Not run in this pass (no `npm run build` requested); Vue changes are additive composables |

**Honest gap:** No new automated Feature test specifically asserting `primeForFastShow` query count. Recommend adding one on staging CI with RefreshDatabase.

---

## 9. Remaining bottlenecks

1. **`visibleEngagement` correlated history SQL** on comments/activities — still the heaviest post-open cost for non-admins on large leads.
2. **`canViewLead` / subordinate BFS** for sales/managers on large trees.
3. **Kanban `stages-with-leads`** query volume (~73 queries historically) + Echo-fallback full-board poll.
4. **JSON `changes->action` filters** on `lead_histories` under production volume.
5. **App shell JS/CSS weight** (Phase 4/9) — unrelated to lead API but affects first interaction.
6. **Production measurement** of lead-open click-to-useful still required.

---

## 10. Deployment instructions

1. Deploy PHP + built frontend assets together (new JS composables).
2. **No migrations** required.
3. Clear application cache if desired: `php artisan cache:clear` (assignment-status / chat / presence keys are short-TTL).
4. Smoke checklist:
   - Open lead from Kanban (seed → full hydrate → comments appear)
   - Re-open same lead (should feel instant)
   - Lead Pool tab: single assignment-status network call
   - Super Admin Live Watch alert still updates via Echo
   - Chat unread badge updates after read / new message
   - Role matrix: sales / branch_admin / admin / super_admin can still open allowed leads; 403 still closes modal

---

## Top 5 bottlenecks addressed this pass

1. Lead show history/activity priming + slim observers  
2. Comments deferred until lead hydrated  
3. Lead SWR cache + request dedupe  
4. Assignment-status / push-config / presence / chat background overhead  
5. Matching search deferred when scored rows already fill first paint  
