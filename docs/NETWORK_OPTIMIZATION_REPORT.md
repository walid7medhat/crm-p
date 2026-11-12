# Network Optimization Report — Phase 2.5

**Date:** 2026-11-12  
**Status:** Implemented locally — **awaiting approval** (no production deploy)  
**Companion audit:** [NETWORK_PERFORMANCE_AUDIT.md](./NETWORK_PERFORMANCE_AUDIT.md)

---

## Summary

Phase 2.5 fixed verified frontend request orchestration issues that caused HTTP 429 on `/api/auth/location`, duplicate birthday/announcement polls, repeated reference-data fetches, and external Iconify CDN hits. Kanban board SQL work was measured but not aggressively rewritten (bottleneck remains server-side under production data; early prefetch already exists).

---

## 1. Root causes → fixes

| Issue | Root cause | Fix | Files |
|-------|------------|-----|-------|
| **429 `/api/auth/location`** | Immediate capture on every `startLocationTracking()` (layout watcher), 180s interval, visibility resume, multi-tab; soft-fail with **no 429 backoff**; shared `throttle:30,1` counted every POST | Single-flight (`inFlight`); **45s** client min gap; skip &lt;50m moves; exponential 429 backoff + `Retry-After`; skip re-start spam; dedicated `throttle:location-tracking` (**20/min**, keyed by user) | `useLocationTracking.js`, `AppServiceProvider.php`, `routes/api.php` |
| **Duplicate birthdays** | `BirthdayCelebrationLayer` (5m) **and** navbar (60s) both hit `/auth/birthdays/today` | Shared `useShellPolling` (5m birthdays, 90s announcements); pause when tab hidden; in-flight dedupe | `useShellPolling.js`, `BirthdayCelebrationLayer.vue`, `navbar/index.vue` |
| **Announcements over-poll** | Navbar 60s interval always-on | Coordinated 90s + visibility pause | same |
| **Campaigns due** | Possible overlapping visibility fetches | `dueInflight` single-flight; cleanup on unmount | `SystemCampaignPopup.vue` |
| **Areas / property-types** | Modals called API directly; `useCache` ignored `has_listings` | `referenceDataCache` TTL + in-flight dedupe; StageChangeReasonModal + `useCache` wired | `referenceDataCache.js`, `useCache.js`, `StageChangeReasonModal.vue` |
| **Iconify CDN** | `@iconify/vue` fetched lucide/heroicons JSON from `api.iconify.design` | Idle dynamic `import('@iconify-json/…')` + `addCollection` (async chunks, not sync-bloated main) | `main.js`, `package.json` |
| **Dev diagnostics** | None | Opt-in axios interceptor (`localStorage crm_net_diag=1`) | `requestDiagnostics.js`, `axios.js` |
| **Kanban ~2.96s** | Heavy SQL + payload (Phase 0: ~73 queries); not duplicate board GET when prefetch shared | No blind query rewrite this phase; preserve pagination/permissions; measure-first noted | Prefetch already in blade/`leadBoardPrefetch.js` |

---

## 2. Before / after request rates (steady shell, one tab, visible)

Estimates for an authenticated CRM session with layout mounted (Leads or Dashboard). Multi-tab multiplies location posts.

| Endpoint | Before (approx / min) | After (approx / min) | Notes |
|----------|----------------------:|---------------------:|-------|
| `POST /api/auth/location` | Burst: login + layout + visibility → **several in &lt;1 min**; then ~0.33/min + visibility spikes → easy **&gt;30/min** across tabs | **≤ ~1.3/min** client gap; server 20/min hard cap; skips unchanged; 429 backoff | 429 eliminated under normal use |
| `GET /api/auth/birthdays/today` | Navbar **1/min** + layer **0.2/min** ≈ **1.2** | **0.2** (5 min shared) | Both UIs subscribe |
| `GET /api/announcements/unread` | **1/min** | **0.67** (90s) | Hidden = 0 |
| `GET /api/system-campaigns/due` | Mount + each show | Same triggers, **deduped** | No interval |
| `GET …/areas?has_listings=true` | Per modal open | **1 / 10 min TTL** + inflight | Per query key |
| `GET …/property-types` | Per modal open | **1 / 10 min TTL** + inflight | |
| `api.iconify.design/*` | **1–2+** collection fetches / session | **0** after idle pack load | Local chunks `icons-*.js` |

Hidden tab: location / shell polls **paused**; resume refreshes only if stale.

---

## 3. API timings (kanban)

| Metric | Result |
|--------|--------|
| Production Network (pre-fix) | ~**2.96s** `GET /api/stages/kanban…` |
| Primary bottleneck | Backend query count + serialization (not frontend duplicate board request when prefetch works) |
| Frontend change this phase | Orchestration elsewhere; kanban SQL rewrite deferred (risk / Scope B Phase 1 already gated query log) |
| Expected wall-clock change | **No material TTFB cut** from Phase 2.5 alone; page feels cleaner from fewer competing polls |

---

## 4. HTTP 429 resolution evidence

1. **Client:** `applyBackoffFromError` reads `Retry-After` or doubles 15s→120s; `captureAndSend` / `postFix` respect `backoffUntil` and `MIN_POST_GAP_MS` (45s); `inFlight` prevents parallel posts; re-`startLocationTracking` skips if recently posted.
2. **Server:** `RateLimiter::for('location-tracking')` → 20/min by `location-tracking:{userId}`; route middleware `throttle:location-tracking` (isolated from generic `30,1`).
3. **Guard test:** `Tests\Unit\NetworkPhase25GuardsTest` — route + limiter registration asserted (**PASS**).

Rate limit was **not** raised globally; location limiter is **lower** (20 vs previous 30) but isolated and matched to client cool-down.

---

## 5. Iconify reduction

| Before | After |
|--------|-------|
| Runtime fetch `api.iconify.design/lucide.json` (+ heroicons) | Bundled `@iconify-json/lucide` + `@iconify-json/heroicons` |
| Sync `addCollection` bloated **main ~2.3MB** (rejected) | Idle dynamic import → separate chunks (~**588KB** + ~**601KB**), **main ~1.11MB** |
| UI | Same `<iconify-icon>` / lucide: & heroicons: names |

---

## 6. Performance instrumentation

```js
localStorage.setItem('crm_net_diag', '1') // or 'dev' + Vite DEV
```

Logs: method, URL, status, durationMs, duplicateCount. **No** tokens, bodies, or PII. Off by default in production.

`window.__crmNetDiag` / `getRequestDiagnosticsSnapshot()` for console inspection.

---

## 7. Tests & build

| Check | Result |
|-------|--------|
| `php artisan test --filter=NetworkPhase25GuardsTest` | **3 passed** (10 assertions) |
| `npm run build` | **✓** ~20s; main 1,105 KB; icons async chunks present |
| Manual scenarios (dev) | Load → Leads ↔ Dashboard → lead modal open/close → tab hide/show → verify single birthday/announcement pollers; location not flooding Network |

Note: Existing `LocationTrackingHistoryTest` / login location suites still show brand/auth **403** noise unrelated to this throttle change (not introduced as 429 regressions).

---

## 8. Deployment & rollback

### Deploy (after approval)

1. Deploy app code (PHP + built assets). No production DB migrations for this phase.
2. Ensure `npm ci && npm run build` (or CI artifact) includes `@iconify-json/*` and new JS modules.
3. Smoke: login → Kanban Network tab: no Iconify CDN; location posts spaced; birthdays ≤1/5min; announcements ≤1/90s.
4. Optional: staging `crm_net_diag=1` for 5 minutes, then clear.

### Rollback

1. Revert the Phase 2.5 commit(s) / redeploy previous `public/build`.
2. Or surgically: restore `throttle:30,1` on `auth/location` and previous `useLocationTracking` / navbar poll loops if only location/shell must roll back.
3. No DB rollback required.

### Risk

Low: client orchestration + isolated limiter + offline icon packs. Business logic, permissions, multi-brand isolation unchanged.

---

## 9. Out of scope / deferred

- Deep kanban SQL / N+1 reduction (next performance phase if approved).
- Migrating all modals to `referenceDataCache` (StageChangeReasonModal + `useCache` done; others can adopt incrementally).
- Cross-tab location BroadcastChannel lock (optional; multi-tab still N trackers but each is cool-downed).
- Raising or disabling Laravel global API throttle.

---

## STOP

Phase 2.5 complete for review. **Awaiting approval** before Phase 3 (Lead Opening) or production deploy.
