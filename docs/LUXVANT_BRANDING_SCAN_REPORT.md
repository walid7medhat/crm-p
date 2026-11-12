# Luxvant Branding Scan Report

**Date:** 2026-11-12  
**Goal:** Inventory OIA brand touchpoints and mark what was made tenant-aware vs deferred.

---

## Made tenant-aware (host-driven)

| Area | Mechanism |
|------|-----------|
| Document title / theme-color / favicon | `welcome.blade.php` + brand logos |
| Boot splash mark / name / colors | `welcome.blade.php` |
| Global CSS brand tokens | `:root` override via `BrandContext::cssVariableMap()` after Vite |
| Login / signup / forgot / reset company title | `useBrand().companyNameDisplay` |
| Auth marketing logos + headlines | `AuthLandingShell.vue` |
| Sidebar logo + collapsed mark | `header/index.vue` |
| Mobile drawer short name | `header/index.vue` |
| App / brand loaders | `AppLoader.vue`, `BrandLoader.vue` |
| Birthday navbar subline | `navbar/index.vue` |
| Login API brand payload | `AuthController@login` → `brand` |
| Public brand JSON | `GET /api/brand` |

Components already consuming `--color-primary` / `--gradient-primary` inherit Luxvant emerald automatically on the Luxvant host.

---

## Official assets

| Asset | Path |
|-------|------|
| Green logo (light UI) | `/assets/images/brands/luxvant/logo-green.png` |
| White logo (dark / auth) | `/assets/images/brands/luxvant/logo-white.png` |
| Favicon / mark | `/assets/images/brands/luxvant/favicon.svg`, `logo-mark.svg` |

Source originals preserved under Cursor project assets; production copies under `public/assets/images/brands/luxvant/`.

---

## Remaining OIA references (intentional or deferred)

### Intentional (do not blind-replace)

| Reference | Reason |
|-----------|--------|
| Historical DB content, lead sources, audit logs | Business data — not branding chrome |
| `@oiaproperties.com` email validation / hygiene tools | Domain policy for OIA staff accounts |
| Lead source badge “OIA” for oiaproperties.com sources | Source taxonomy, not shell branding |
| Watermark file names containing “Oia” in storage | Existing uploaded/setting assets |
| ROI / commission copy “OIA retained” | Business wording; optional later rename to “Company retained” |
| `DEFAULT_COMPANIES = ['OIA PROPERTIES L.L.C']` | Legal entity default for asset requests |

### Deferred outbound branding (still OIA-hardcoded)

Email blades under `resources/views/emails/*`, PDF blades under `resources/views/pdf/*`, `calculatorPdfTheme.js`, BlogOne offer PDF stamps, privacy-policy blade, PWA splash PNGs, `manifest.webmanifest` name.

These should use `BrandContext` (or queued job brand payload) in a follow-up without changing historical stored PDFs.

### Deferred hardcoded color literals

Many Vue SCSS files still hardcode `#0B0736` / navy for text. Prefer migrating to CSS variables over time. Shell chrome and primary actions that use theme tokens already switch on Luxvant.

---

## Color SSOT

| Brand | Primary | Config |
|-------|---------|--------|
| OIA | `#733E87` | `config/brands.php` + `resources/css/crm-theme.css` (unchanged defaults) |
| Luxvant | `#003c38` | `config/brands.php` only (CSS overridden at runtime) |

Do **not** change `crm-theme.css` defaults to Luxvant — that would redesign OIA.
