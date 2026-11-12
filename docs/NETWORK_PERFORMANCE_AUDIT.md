# Network Performance Audit — Phase 2.5 (Read-Only Findings)

**Date:** 2026-11-12  
**Source:** Production Chrome DevTools Network observations + codebase verification  
**Scope:** Leads/Kanban shell — repeated APIs, HTTP 429, Iconify, kanban timing

---

## 1. POST `/api/auth/location` → HTTP 429

| Field | Finding |
|-------|---------|
| **Exact sources** | (1) `resources/js/composables/useLocationTracking.js` started from `App.vue` `watch(showLayout)`; (2) `SignIn.vue` one-shot `fetch(.../auth/location)` on login with `keepalive` |
| **Triggers** | Layout visible → immediate `captureAndSend`; `setInterval` (default **180s**); `visibilitychange` when tab visible again; every `startLocationTracking()` re-entry also fired an immediate capture |
| **Retries** | No axios retry. Soft-fail catch swallowed 429, so the interval kept firing with no backoff |
| **Multi-tab** | Each open CRM tab runs its own tracker → N× posts |
| **Rate limit** | `throttle:30,1` on the route (inside outer `throttle:300,1` group). Plain `throttle:30,1` shares the numeric limiter signature with any other `30,1` routes historically |
| **Why 429** | Bursts from login + layout start + visibility + multi-tab + no client cool-down / no 429 backoff. Server already has 30s write cooldown, but **rate limit counts every POST**, including no-op cool-down responses |

**Evidence files:** `useLocationTracking.js`, `App.vue`, `SignIn.vue`, `routes/api.php` (`auth/location`), `LocationTrackingService.php` (`write_cooldown_seconds`).

---

## 2. Background polling matrix

| Endpoint | Source | Lifecycle | Interval (before) | Simultaneous callers | Continues when hidden? |
|----------|--------|-----------|-------------------|----------------------|------------------------|
| `/api/auth/birthdays/today` | `BirthdayCelebrationLayer.vue` | `onMounted` + `setInterval` | **5 min** | Yes — also navbar | **Yes** (interval kept running) |
| `/api/auth/birthdays/today` | `navbar/index.vue` `loadBirthdayPopup` | idle start + **60s** interval | **60s** | Duplicate of layer | **Yes** |
| `/api/auth/notifications?type=BirthdaySelf…` | navbar `loadBirthdayPopup` | with birthday poll | 60s | Single (navbar) | Yes |
| `/api/announcements/unread` | navbar `loadPendingAnnouncementPopups` | idle + 60s | **60s** | Single | Yes |
| `/api/system-campaigns/due` | `SystemCampaignPopup.vue` | `onMounted` + `visibilitychange` | No interval (on show) | Could double on rapid visibility | Skipped when popup open; still ran when hidden→visible |
| `/api/auth/location` | `useLocationTracking` | see §1 | 180s + events | Multi-tab | Paused when hidden (good) |
| Push `/push-subscriptions` | `MobilePushToggle.vue` (`auto-only` in App) | mount / user gesture | On demand | Single | N/A |

**Root cause of birthday spam:** two independent pollers for the same `/auth/birthdays/today` (layer 5m + navbar 60s).

---

## 3. Duplicate reference data

| Endpoint | Callers (examples) | Cache before |
|----------|--------------------|--------------|
| `/api/listings/areas?has_listings=true` | `StageChangeReasonModal.vue`, listing SearchBar, etc. | Partial via `useCache` **without** `has_listings` key |
| `/api/listings/property-types` | StageChangeReasonModal, LeadInfoView, CreateLeadModal, Deal modals, `useCache` | Module cache in `useCache` only if that composable is used; many modals call `api.get` directly |

**Root cause:** no shared in-flight dedupe across modals; `useCache.fetchAreas()` ignored `has_listings=true`, so StageChangeReasonModal always hit the network even when areas were warm elsewhere.

---

## 4. Iconify → `api.iconify.design/lucide.json` (+ heroicons)

| Field | Finding |
|-------|---------|
| **Mechanism** | Global `<iconify-icon>` wrapper around `@iconify/vue` `Icon` in `main.js` |
| **Why network** | Icons referenced as `lucide:…` / `heroicons:…` strings; `@iconify/vue` fetches missing collections from Iconify API |
| **Scale** | Header/navbar/kanban alone reference dozens of lucide icons → collection JSON fetch(es) on session start |

---

## 5. Kanban `GET /api/stages/kanban/stages-with-leads?per_page=15` (~2.96s prod)

| Layer | Finding |
|-------|---------|
| **Duplicate board fetch** | `welcome.blade.php` early prefetch + `leadBoardPrefetch.js` + `leads.vue` — **designed to share** via `takeLeadBoardPrefetch`; not a double-hit when wired correctly |
| **Backend** | Still heavy: many SQL queries per board (Phase 0 local **73** queries); Phase 1 gated query log |
| **Frontend wait** | `axios` `waitForLeadBoardPriority()` holds most other APIs until board settles — amplifies perceived slowness of the whole page, not only kanban TTFB |
| **Payload** | Card resource is lean vs old LeadResource; still non-trivial with many stages |

**Bottleneck conclusion:** primarily **server query volume + payload** under production data, not a missing frontend abort. Early prefetch helps TTFB but cannot shrink SQL work.

---

## 6. Instrumentation gap (before)

No first-party duplicate-request console diagnostics. Chrome Network was the only view.
