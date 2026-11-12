# Luxvant Security Fix — Completion Report

**Date:** 2026-11-12  
**Live Luxvant:** https://luxvant.altcrm.com/  
**Rule:** Luxvant-only security; OIA behavior preserved.

---

## 1. Files changed

| File | Change |
|------|--------|
| `app/Http/Middleware/JwtAuthMiddleware.php` | Rewritten: Tymon Authenticate first (OIA-identical), then `EnsureBrandAccess` |
| `app/Http/Middleware/EnsureBrandAccess.php` | Early exit when `access.mode=open` (OIA); Luxvant allowlist + JWT `brand_key` checks |
| `app/Http/Middleware/BlockRiskyPublicOnLuxvant.php` | **New** — Luxvant-only 403 for selected public risk endpoints |
| `app/Providers/AppServiceProvider.php` | `booted()` re-aliases `jwt.auth` after tymon package overwrite |
| `bootstrap/app.php` | FQCN aliases for `brand.access`, `brand.block_luxvant_public` |
| `app/Support/Brand/BrandResolver.php` | Brand from raw `Host` (not `X-Forwarded-Host`); `BRAND_FORCE` disabled in production |
| `routes/api.php` | Luxvant-only middleware on risky public routes |
| `tests/Feature/Brand/LuxvantBrandGateTest.php` | **New** regression tests |
| `docs/LUXVANT_SECURITY_FIX_COMPLETION_REPORT.md` | This report |

**Not changed:** Areas, Developers, Projects, users, leads, deals, migrations, OIA branding, OIA open access mode.

---

## 2. Middleware / route behavior

### Before (audit)

| Item | Behavior |
|------|----------|
| `jwt.auth` runtime | `Tymon\JWTAuth\Http\Middleware\Authenticate` (package overwrote bootstrap alias) |
| Brand allowlist on APIs | **Dead** (only lived in unused custom middleware) |
| Ordinary OIA JWT → Luxvant API | **Allowed** (auth only) |
| `brand.access` | Broken / unused |
| Public risk endpoints on Luxvant | Same as OIA (open) |

### After (this fix)

| Item | Behavior |
|------|----------|
| `jwt.auth` runtime | `App\Http\Middleware\JwtAuthMiddleware` → Tymon auth → brand gate |
| OIA (`access.mode=open`) | Brand gate **immediate pass** after Tymon — auth path unchanged |
| Luxvant | Allowlist (`super_admin` / env) enforced on every `jwt.auth` route |
| Ordinary OIA JWT → Luxvant API | **HTTP 403** |
| Super Admin → Luxvant API | **Allowed** (same user id) |
| Risky public routes | `brand.block_luxvant_public` → **403 on Luxvant only**; OIA unchanged |

Risky public routes (Luxvant-blocked, OIA preserved):

- `GET /api/test-account-activated-email`
- `GET /api/save-account-activated-email-html`
- `GET /api/get-from-meta-in-day/{id}`
- `GET /api/auth/users/role/{role}`
- `POST /api/altcrm/update-more-info`

---

## 3. Local verification (controlled HTTP kernel) — PASS

| Test | Result | Status |
|------|--------|--------|
| `jwt.auth` resolves to `JwtAuthMiddleware` (not bare Tymon) | Confirmed | **PASS** |
| Ordinary user JWT + `brand_key=oia` → Luxvant `/api/auth/notifications` | **403** tenant mismatch | **PASS** |
| Ordinary user JWT (no `brand_key`) → Luxvant notifications | **403** not authorized | **PASS** |
| Same ordinary JWT → OIA notifications | **200** | **PASS** |
| Super Admin JWT → Luxvant notifications | **200** | **PASS** |
| Luxvant `GET /api/auth/users/role/manager` | **403** workspace block | **PASS** |
| OIA `GET /api/auth/users/role/manager` | **200** | **PASS** |
| Luxvant test-email route | **403** | **PASS** |
| Host=luxvant + `X-Forwarded-Host=oiaproperties…` → brand | `luxvant` | **PASS** |
| Areas/Developers/Projects | No code/data changes | **PASS** |

---

## 4. Live verification — https://luxvant.altcrm.com/

**These fixes are not deployed to live yet.** Live probes reflect **current production**, not this branch.

| Test | Live result | Status |
|------|-------------|--------|
| `GET /api/brand` | **404** (endpoint absent on live) | **FAIL** (pre-deploy) |
| `GET /sign-in` | **200** | **PASS** (page loads) |
| `GET /api/auth/users/role/manager` | **403** Forbidden (referer/guard — not this fix) | **NOT TESTED** (post-deploy) |
| Ordinary OIA JWT → Luxvant API | Not run (no production credentials used) | **NOT TESTED** |
| Super Admin Luxvant login | Not run (no production credentials used) | **NOT TESTED** |
| OIA login / branding regression | `oiaproperties.altcrm.com/sign-in` → **200** | **PASS** (smoke only) |
| Host spoof against live edge | Not run (unsafe / inconclusive without edge access) | **NOT TESTED** |

### Post-deploy checklist (required for live PASS)

```bash
# Brand endpoint
curl -s https://luxvant.altcrm.com/api/brand | jq .data.key
# expect: luxvant

# Ordinary OIA JWT (obtain from OIA login in a private test only)
curl -s -o /dev/null -w "%{http_code}\n" \
  -H "Authorization: Bearer $OIA_USER_JWT" \
  https://luxvant.altcrm.com/api/auth/notifications
# expect: 403

# Super Admin JWT from Luxvant login
curl -s -o /dev/null -w "%{http_code}\n" \
  -H "Authorization: Bearer $SA_LUXVANT_JWT" \
  https://luxvant.altcrm.com/api/auth/notifications
# expect: 200

# Public risk block
curl -s -o /dev/null -w "%{http_code}\n" https://luxvant.altcrm.com/api/auth/users/role/manager
# expect: 403

# OIA unchanged
curl -s -o /dev/null -w "%{http_code}\n" https://oiaproperties.altcrm.com/api/auth/users/role/manager
# expect: 200 (with normal browser Origin/Referer if referer guard enforce)
```

**Do not commit `BRAND_FORCE=luxvant` to production `.env`.** It is ignored when `APP_ENV=production`, but should still be absent.

**Do not set `SESSION_DOMAIN=.altcrm.com`.**

---

## 5. Unresolved findings (honest)

| Finding | Status |
|---------|--------|
| Shared DB / no row-level tenant isolation | **Unresolved** — out of scope; Luxvant SA still sees OIA data |
| `trustProxies(at: '*')` still global | **Mitigated** for brand via raw `Host`; general proxy trust unchanged (OIA-safe) |
| Public OIA webhooks still unauthenticated by design | **Unresolved** on OIA (intentionally preserved) |
| Email/PDF/queue brand leakage | **Unresolved** — not part of this fix |
| Cache key brand namespacing | **Unresolved** — deferred (would risk OIA cache behavior) |
| Live post-deploy auth matrix | **NOT TESTED** until deploy |

---

## 6. Readiness verdicts

| Question | Verdict |
|----------|---------|
| Safe for **restricted production** Luxvant (Super Admin only) after deploy + post-deploy checklist PASS? | **CONDITIONAL YES** — branding + login/API allowlist; shared data remains |
| Ready for **independent SaaS tenancy**? | **NO** — no tenant data isolation |

---

## 7. OIA impact statement

| Area | Impact |
|------|--------|
| JWT authentication | Still Tymon Authenticate first — same as live today |
| Brand gate on OIA | No-op (`mode=open`) |
| Public role/meta/whatsapp/test routes | Unchanged on OIA hosts |
| Branding / UI | Unchanged |
| Database | No migrations / no record changes |
