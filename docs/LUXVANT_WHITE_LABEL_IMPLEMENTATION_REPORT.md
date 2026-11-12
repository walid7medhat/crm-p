# Luxvant White-Label Implementation Report

**Date:** 2026-11-12  
**Domain:** `https://luxvant.altcrm.com/`  
**Primary color (official):** Emerald Green `#003c38`  
**Scope:** Domain-specific branding + Super Admin access gate on the existing Laravel 11 + Vue 3 codebase.

---

## Summary

Luxvant branding activates only when the request host resolves to a Luxvant domain. OIA branding and behavior remain the default for `oiaproperties.altcrm.com`, localhost, and other OIA hosts.

There was **no existing SaaS tenancy / BrandContext** in this codebase. A lightweight **host-based brand layer** was added (config + middleware + frontend bootstrap) without duplicating the CRM or changing OIA UI defaults.

---

## Architecture

```
Request host
  → ResolveBrandFromHost (global)
  → BrandContext (singleton)
  → welcome.blade.php injects window.__BRAND__ + CSS variables
  → Vue useBrand() for logos / company name / loaders
  → Auth login + JwtAuthMiddleware enforce Luxvant allowlist
```

### Brand keys

| Key | Hosts (examples) | Access |
|-----|------------------|--------|
| `oia` (default) | `oiaproperties.altcrm.com`, `localhost`, … | Open (all active users) |
| `luxvant` | `luxvant.altcrm.com`, `www.luxvant.altcrm.com` | Allowlist: `super_admin` + optional env IDs/emails |

---

## Files changed / added

### New

- `config/brands.php`
- `app/Support/Brand/BrandContext.php`
- `app/Support/Brand/BrandResolver.php`
- `app/Http/Middleware/ResolveBrandFromHost.php`
- `app/Http/Middleware/EnsureBrandAccess.php` (available; enforcement lives in JWT middleware)
- `app/Http/Controllers/Api/BrandController.php`
- `resources/js/composables/useBrand.js`
- `public/assets/images/brands/luxvant/logo-green.png`
- `public/assets/images/brands/luxvant/logo-white.png`
- `public/assets/images/brands/luxvant/favicon.svg`
- `public/assets/images/brands/luxvant/logo-mark.svg`
- `tests/Unit/Brand/BrandResolverTest.php`
- `docs/LUXVANT_WHITE_LABEL_IMPLEMENTATION_REPORT.md`
- `docs/LUXVANT_BRANDING_SCAN_REPORT.md`
- `docs/LUXVANT_SECURITY_VALIDATION_REPORT.md`

### Modified

- `bootstrap/app.php` — register brand resolve middleware
- `app/Providers/AppServiceProvider.php` — singleton BrandContext / BrandResolver
- `app/Http/Middleware/RequireTrustedReferer.php` — allow `luxvant.altcrm.com`
- `app/Http/Middleware/JwtAuthMiddleware.php` — brand access + JWT brand claim checks
- `app/Http/Controllers/Api/AuthController.php` — login + impersonate brand gate; `brand_key` JWT claim
- `routes/api.php` — `GET /api/brand`
- `resources/views/welcome.blade.php` — title, favicon, splash, CSS vars, `window.__BRAND__`
- Auth UI: `AuthLandingShell.vue`, `SignIn.vue`, `SignUp.vue`, `ForgotPassword.vue`, `ResetPassword.vue`
- Chrome: `header/index.vue`, `navbar/index.vue`, `BrandLoader.vue`, `AppLoader.vue`
- `resources/css/auth-landing.css` — Luxvant logo sizing
- `resources/js/main.js` — document title from brand

---

## Official Luxvant colors

| Token | Value | Status |
|-------|-------|--------|
| Primary | `#003c38` | **Official** (provided) |
| Secondary | `#0a0a0a` | Placeholder — pending official |
| Accent / glow | `#c9a227` | Placeholder — pending official |

Update placeholders in `config/brands.php` → `brands.luxvant.colors` when secondary is confirmed.

---

## Super Admin access

- Same user ID, email, and password hash.
- No duplicated user record.
- Luxvant login allowed when user has Spatie role `super_admin`.
- Optional extras via env:
  - `LUXVANT_ACCESS_USER_IDS=1,2`
  - `LUXVANT_ACCESS_EMAILS=person@example.com`
- Ordinary OIA users receive `403` on Luxvant login / JWT APIs.
- Impersonation on Luxvant blocked for targets not on the Luxvant allowlist.

---

## Data preservation

- **No migrations** executed for this work.
- Areas / Developers / Projects **not** modified, reassigned, or filtered by brand.
- Shared database remains shared (see Security report — data isolation is a separate approval item).

---

## Deployment requirements

1. Deploy code + public Luxvant logo assets.
2. Rebuild frontend assets (`npm run build`) so Vue brand wiring ships.
3. Confirm DNS already points `luxvant.altcrm.com` at this app (do not change DNS from this task).
4. Ensure reverse proxy forwards the correct `Host` header.
5. Optional production harden: `BRAND_REJECT_UNKNOWN_HOSTS=true` after all legitimate hosts are listed in `config/brands.php`.
6. Confirm Super Admin role is `super_admin` on the account used for Luxvant.
7. Smoke-test both domains after deploy.

## Rollback

1. Revert the deployment commit(s) / redeploy previous release.
2. Or temporarily remove `luxvant.altcrm.com` from Luxvant `hosts` and clear config cache — host falls back to OIA brand (not recommended long-term).
3. No DB rollback needed (no schema/data changes).

---

## Remaining issues / follow-ups

1. Official Luxvant **secondary** color still placeholder.
2. Email / PDF / offer templates still contain many hardcoded OIA strings (see branding scan) — request-scoped brand helpers not yet applied to every outbound template.
3. Hardcoded `#0B0736` / `#733E87` still exist in some Vue modules that do not use CSS variables; components that already use `--color-primary` pick up Luxvant automatically.
4. Full **row-level tenant data isolation** is **not** implemented (requires explicit approval — would affect shared Areas/Developers/Projects and every query).
5. PWA splash PNGs remain Alt CRM artwork on Luxvant (favicon/title/theme are Luxvant).
