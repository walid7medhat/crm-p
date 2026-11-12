# Performance Phase 1 Implementation Notes

**Date:** 2026-11-12  
**Scope:** Immediate performance fixes only (approved Scope B Phase 1)  
**Status:** Complete — awaiting approval before Phase 2 (Lead Search)

## Changes

| Item | Change | Reversible by |
|------|--------|---------------|
| Kanban query log | Off unless `KANBAN_PERF_QUERY_LOG=true` | Re-enable env / revert StageController |
| Duplicate `leads:score` | Removed second scheduler registration | Restore second call in `bootstrap/app.php` |
| Dashboard charts | Period/range charts use `GROUP BY` | Revert DashboardController helpers |
| Property map | Default 500 pins, max 1000 (was 2500/3000) | Revert ListingController + PropertyMap.vue |
| Sales Action Center | Category COUNTs via one `UNION ALL` round-trip (`sac:summary:v2`) | Revert SalesActionCenterService |

## Files changed

- `config/lead_scoring.php`
- `.env.example`
- `app/Http/Controllers/Api/StageController.php`
- `bootstrap/app.php`
- `app/Http/Controllers/Api/DashboardController.php`
- `app/Http/Controllers/Api/Listing/ListingController.php`
- `resources/js/components/alllisting/PropertyMap.vue`
- `app/Services/SalesAction/SalesActionCenterService.php`
- `tests/Unit/PerformancePhase1QuickWinsTest.php` (new)
- `tests/Feature/SalesActionCenterTest.php` (parity test)
- `docs/PERFORMANCE_PHASE1_IMPLEMENTATION.md` (this file)

## Tests / build

- `php artisan test --filter='PerformancePhase1QuickWinsTest|SalesActionCenterTest'` → **16 passed**
- `npm run build` → **success**

## Local measurements (tiny DB; shape evidence)

| Metric | Before (code/audit) | After (local) |
|--------|---------------------|---------------|
| Chart monthly SQL | up to **30** COUNT loops | **1** GROUP BY |
| Chart weekly SQL | **7** COUNT loops | **1–2** (GROUP BY + incidental) |
| Chart yearly SQL | **12** COUNT loops | **1** GROUP BY |
| SAC category summary | **9–10** sequential COUNT | **1** UNION ALL (+ stage lookups) |
| Map default/max pins | 2500 / 3000 | **500 / 1000** |
| Kanban query log | always on | **off** by default |

Production-sized before/after remains for Phase 4 / staging.

## Remaining bottlenecks (next phases)

1. Lead `%LIKE%` contains-search (Phase 2)
2. Lead open fan-out / assignment history / `visibleEngagement` (Phase 3)
3. Studio multi-aggregate, deals list payload, file cache / DB queue (later)
