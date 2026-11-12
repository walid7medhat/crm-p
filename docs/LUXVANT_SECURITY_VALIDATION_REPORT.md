# Luxvant Security Validation Report

**Date:** 2026-11-12  
**Focus:** Host brand resolution, Super Admin access, tenant claim checks, OIA non-regression, data safety.

---

## What was validated (automated / local)

| Check | Result |
|-------|--------|
| `luxvant.altcrm.com` → brand `luxvant`, primary `#003c38` | Pass |
| `oiaproperties.altcrm.com` → brand `oia`, primary `#733E87` | Pass |
| `localhost` → brand `oia` | Pass |
| Unknown host → default OIA when `BRAND_REJECT_UNKNOWN_HOSTS=false` | Pass |
| Super Admin role may access Luxvant | Pass |
| Non–super_admin agent denied Luxvant | Pass |
| Non–super_admin agent allowed OIA | Pass |
| Luxvant logo assets present on disk | Pass |
| Unit test `BrandResolverTest` | Pass (2 tests) |
| No migrations / no Areas-Developers-Projects writes | Pass (by design) |

---

## Authentication & authorization model

1. **Credentials unchanged** — login still verifies email + password hash on the existing `users` row.
2. **Brand gate after credential success** — Luxvant requires `super_admin` (or env allowlist).
3. **JWT** — new logins stamp `brand_key`. Mismatch vs host brand is rejected unless the user is authorized for the host brand (Super Admin may cross with audit log).
4. **JWT APIs** — `JwtAuthMiddleware` re-checks brand access on every protected call.
5. **Impersonation** — blocked on Luxvant when the target user is not Luxvant-authorized.
6. **Trusted referer** — `luxvant.altcrm.com` added so API referer guard does not block Luxvant SPA calls in enforce mode.

Ordinary OIA users **cannot** obtain a Luxvant session without explicit allowlisting.

---

## Critical limitation — shared data plane

This CRM historically has **one database and no `tenant_id` scoping**.

| Isolation layer | Status |
|-----------------|--------|
| Branding (logo/colors/name) | Implemented |
| Login / API access gate for Luxvant | Implemented |
| Row-level data isolation between OIA and Luxvant | **Not implemented** |

Implication: a Luxvant-authorized Super Admin sees the **same** CRM records as on OIA (including Areas, Developers, Projects). That matches the requirement to **preserve** those records and avoid destructive reassignment, but it is **not** “separate Luxvant private data.”

Building full multi-tenant data isolation would touch nearly every query and could hide or reassign protected inventory. **Approval is required before any such work.**

---

## Unknown hosts

- Default: unknown hosts resolve to OIA (safe for MAMP / local aliases).
- Production option: set `BRAND_REJECT_UNKNOWN_HOSTS=true` to `abort(404)` for unmapped hosts after listing all legitimate hosts in `config/brands.php`.

---

## OIA regression expectations

| Surface | Expected |
|---------|----------|
| OIA login design / purple theme | Unchanged (default brand tokens match `crm-theme.css`) |
| OIA logos / “OIA PROPERTIES” titles | Unchanged on OIA hosts |
| OIA permissions / workflows | Untouched |
| OIA user login | Untouched (`access.mode = open`) |

Manual production smoke on `oiaproperties.altcrm.com` is still required after deploy.

---

## Manual test checklist (production / staging)

### Luxvant (`luxvant.altcrm.com`)

- [ ] URL stays on `luxvant.altcrm.com` (no redirect to OIA)
- [ ] Login page structure matches OIA layout; logo/colors/name are Luxvant
- [ ] Favicon / title show Luxvant
- [ ] Super Admin logs in with existing credentials; user id unchanged
- [ ] Ordinary OIA agent login returns 403
- [ ] Sidebar / loaders / primary buttons use emerald `#003c38`
- [ ] Hard refresh keeps Luxvant branding
- [ ] `GET /api/brand` returns `key: luxvant`

### OIA

- [ ] Login unchanged
- [ ] Purple primary `#733E87` unchanged
- [ ] OIA logos unchanged
- [ ] Existing users and permissions work

### Security

- [ ] Luxvant agent token cannot be used if somehow obtained without allowlist
- [ ] Impersonate into non-allowlisted user fails on Luxvant
- [ ] Referer enforce mode still allows Luxvant origin

---

## Audit logging

Denied Luxvant logins / API access and JWT brand mismatches are logged via Laravel `Log::warning` / `Log::info` with `user_id`, host, path, and brand keys.
