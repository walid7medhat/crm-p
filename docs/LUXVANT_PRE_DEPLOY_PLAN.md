# Luxvant Pre-Deploy Plan — Security Fixes (+ Brand Runtime)

**Status:** READY FOR APPROVAL — **DO NOT DEPLOY** until explicit approval.  
**Live:** https://luxvant.altcrm.com/  
**Prepared:** 2026-11-12  
**Migrations:** none  
**DB changes:** none  
**Env changes:** none required (do **not** set `BRAND_FORCE` or `SESSION_DOMAIN=.altcrm.com`)

---

## A. Exact deployment file list

### Package S — Security-critical (must deploy for C1)

| # | Path | Type | Shared with OIA? |
|---|------|------|------------------|
| 1 | `app/Http/Middleware/JwtAuthMiddleware.php` | modified | **Yes** — see impact |
| 2 | `app/Http/Middleware/EnsureBrandAccess.php` | new | Yes (no-op on OIA) |
| 3 | `app/Http/Middleware/BlockRiskyPublicOnLuxvant.php` | new | Yes (no-op on OIA) |
| 4 | `app/Http/Middleware/ResolveBrandFromHost.php` | new | Yes (defaults OIA) |
| 5 | `app/Support/Brand/BrandContext.php` | new | Yes |
| 6 | `app/Support/Brand/BrandResolver.php` | new | Yes |
| 7 | `app/Http/Controllers/Api/BrandController.php` | new | Yes (read-only brand JSON) |
| 8 | `app/Http/Controllers/Api/AuthController.php` | modified | **Yes** — Luxvant login/impersonate gates only |
| 9 | `app/Providers/AppServiceProvider.php` | modified | **Yes** — alias rebind after boot |
| 10 | `bootstrap/app.php` | modified | **Yes** — brand middleware aliases + ResolveBrandFromHost append |
| 11 | `app/Http/Middleware/RequireTrustedReferer.php` | modified | Adds `luxvant.altcrm.com` allowlist entry |
| 12 | `routes/api.php` | modified | `GET /api/brand` + Luxvant-only middleware on risk routes |
| 13 | `config/brands.php` | new | Host/brand config (OIA defaults mirror current theme) |

### Package B — Brand runtime UI (needed for Luxvant-branded CRM; optional if security-only release)

| # | Path | Type |
|---|------|------|
| 14 | `resources/views/welcome.blade.php` | modified — inject `__BRAND__` + CSS vars |
| 15 | `resources/js/composables/useBrand.js` | new |
| 16 | `resources/js/main.js` | modified — document title from brand |
| 17–21 | Auth Vue: `AuthLandingShell`, `SignIn`, `SignUp`, `ForgotPassword`, `ResetPassword` | modified |
| 22 | `resources/css/auth-landing.css` | modified — Luxvant logo sizing |
| 23–26 | `AppLoader.vue`, `BrandLoader.vue`, `header/index.vue`, `navbar/index.vue` | modified |
| 27–30 | `public/assets/images/brands/luxvant/*` | new assets |
| 31 | `public/build/**` | **must rebuild** if Package B is deployed (`npm run build`) |

### Explicitly EXCLUDED (do not deploy)

| Path | Reason |
|------|--------|
| `resources/js/components/ConfirmationModal.vue` | Unrelated whitespace-only local change |
| `.DS_Store`, `.phpunit.result.cache` | Noise |
| `OIA_*_AUDIT.md`, `ALT_CRM_*`, `executive-reports/` | Unrelated docs |
| Local `.env` (`BRAND_FORCE=luxvant`) | **Never deploy** — production ignores it when `APP_ENV=production`, but must stay absent |
| `tests/**` | Optional; safe but not required for runtime |

### Recommended release

**Deploy Package S + Package B together** so `/api/brand` works and Luxvant UI is branded.  
**Minimum viable security:** Package S only (APIs gated; UI may still look partially OIA until B).

---

## B. Local vs production differences (shared files)

### Live production today (observed)

| Check | Result |
|-------|--------|
| `GET https://luxvant.altcrm.com/api/brand` | **404** (BrandController not live) |
| `GET https://luxvant.altcrm.com/sign-in` | **200** |
| `GET https://oiaproperties.altcrm.com/sign-in` | **200** |
| GitHub `main` `JwtAuthMiddleware` | Custom inactive/presence implementation |
| Live `jwt.auth` effective middleware | **Bare Tymon Authenticate** (package overwrites alias) |

### Shared-file OIA impact analysis

| File | OIA impact | Safe? |
|------|------------|-------|
| `JwtAuthMiddleware.php` | Becomes Tymon → then brand gate. Live already uses Tymon for auth. OIA brand gate exits immediately (`mode=open`). Does **not** re-enable inactive/presence logic that exists in git but was not live. | **Yes** — matches live auth |
| `AppServiceProvider.php` | Re-binds `jwt.auth` after boot | **Yes** — required for C1 |
| `bootstrap/app.php` | Appends `ResolveBrandFromHost`; OIA hosts resolve to OIA brand | **Yes** |
| `AuthController.php` | Extra allowlist only when Luxvant brand; OIA login path unchanged after credentials | **Yes** |
| `RequireTrustedReferer.php` | Adds Luxvant host to allowlist; OIA hosts unchanged | **Yes** |
| `routes/api.php` | Risk-route middleware is Luxvant-only no-op on OIA | **Yes** |
| `config/brands.php` + Brand\* | New; OIA is default open brand | **Yes** |

### Auth path confirmation (required)

```
OIA request:
  ResolveBrandFromHost → brand=oia (open)
  jwt.auth → Tymon Authenticate → EnsureBrandAccess (immediate pass) → controller

Luxvant request:
  ResolveBrandFromHost → brand=luxvant (allowlist)
  jwt.auth → Tymon Authenticate → EnsureBrandAccess (super_admin / allowlist) → controller
```

---

## C. Backup and rollback

Run **on the production server** (paths may be `/var/www/...` — adjust `APP_ROOT`).

### Backup (before any copy)

```bash
APP_ROOT=/path/to/crm-p   # SET THIS
STAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="$APP_ROOT/storage/deploy-backups/luxvant_security_$STAMP"
mkdir -p "$BACKUP_DIR"

# Backup only files that exist and will be replaced
for f in \
  app/Http/Middleware/JwtAuthMiddleware.php \
  app/Http/Controllers/Api/AuthController.php \
  app/Providers/AppServiceProvider.php \
  bootstrap/app.php \
  app/Http/Middleware/RequireTrustedReferer.php \
  routes/api.php \
  resources/views/welcome.blade.php \
  resources/js/main.js \
  resources/css/auth-landing.css \
  resources/js/components/Authentication/AuthLandingShell.vue \
  resources/js/components/Authentication/SignIn.vue \
  resources/js/components/Authentication/SignUp.vue \
  resources/js/components/Authentication/ForgotPassword.vue \
  resources/js/components/Authentication/ResetPassword.vue \
  resources/js/components/layout/AppLoader.vue \
  resources/js/components/layout/BrandLoader.vue \
  resources/js/components/layout/header/index.vue \
  resources/js/components/layout/navbar/index.vue
 do
  if [ -f "$APP_ROOT/$f" ]; then
    mkdir -p "$BACKUP_DIR/$(dirname "$f")"
    cp -a "$APP_ROOT/$f" "$BACKUP_DIR/$f"
  fi
done

# Backup current built assets if Package B includes public/build
if [ -d "$APP_ROOT/public/build" ]; then
  cp -a "$APP_ROOT/public/build" "$BACKUP_DIR/public_build"
fi

echo "Backup at: $BACKUP_DIR"
ls -la "$BACKUP_DIR"
```

### Rollback

```bash
APP_ROOT=/path/to/crm-p
BACKUP_DIR=/path/to/backup   # the stamp dir created above

# Restore replaced files
rsync -a "$BACKUP_DIR/" "$APP_ROOT/"

# Remove new files that did not exist pre-deploy
rm -f \
  "$APP_ROOT/app/Http/Middleware/EnsureBrandAccess.php" \
  "$APP_ROOT/app/Http/Middleware/BlockRiskyPublicOnLuxvant.php" \
  "$APP_ROOT/app/Http/Middleware/ResolveBrandFromHost.php" \
  "$APP_ROOT/app/Http/Controllers/Api/BrandController.php" \
  "$APP_ROOT/config/brands.php" \
  "$APP_ROOT/resources/js/composables/useBrand.js"
rm -rf \
  "$APP_ROOT/app/Support/Brand" \
  "$APP_ROOT/public/assets/images/brands/luxvant"

# If public/build was backed up:
# rm -rf "$APP_ROOT/public/build" && cp -a "$BACKUP_DIR/public_build" "$APP_ROOT/public/build"

php "$APP_ROOT/artisan" optimize:clear
# reload php-fpm / octane if applicable
```

Rollback is **file-only** — no DB reverse needed.

---

## D. Exact deployment commands (DO NOT RUN until approved)

Assumes release artifacts prepared on CI or a clean copy of Package S+B (not the whole dirty working tree).

```bash
APP_ROOT=/path/to/crm-p
RELEASE=/path/to/luxvant_security_release   # curated file tree mirroring package paths

# 1) Backup (section C)
# 2) Copy release files only
rsync -av --relative \
  "$RELEASE/./app/Http/Middleware/JwtAuthMiddleware.php" \
  "$RELEASE/./app/Http/Middleware/EnsureBrandAccess.php" \
  "$RELEASE/./app/Http/Middleware/BlockRiskyPublicOnLuxvant.php" \
  "$RELEASE/./app/Http/Middleware/ResolveBrandFromHost.php" \
  "$RELEASE/./app/Support/Brand/" \
  "$RELEASE/./app/Http/Controllers/Api/BrandController.php" \
  "$RELEASE/./app/Http/Controllers/Api/AuthController.php" \
  "$RELEASE/./app/Providers/AppServiceProvider.php" \
  "$RELEASE/./bootstrap/app.php" \
  "$RELEASE/./app/Http/Middleware/RequireTrustedReferer.php" \
  "$RELEASE/./routes/api.php" \
  "$RELEASE/./config/brands.php" \
  "$APP_ROOT/"

# Package B (if approved):
rsync -av --relative \
  "$RELEASE/./resources/views/welcome.blade.php" \
  "$RELEASE/./resources/js/" \
  "$RELEASE/./resources/css/auth-landing.css" \
  "$RELEASE/./public/assets/images/brands/" \
  "$RELEASE/./public/build/" \
  "$APP_ROOT/"

# 3) Clear caches — no migrate
cd "$APP_ROOT"
php artisan optimize:clear
# php artisan config:cache   # optional; ensure .env has NO BRAND_FORCE

# 4) Reload PHP
# sudo systemctl reload php8.3-fpm
```

**Alternative (git):** commit **only** Package S(+B) on a release branch, deploy that commit — do **not** push unrelated audits / ConfirmationModal.

---

## E. Post-deployment verification commands

Use admin-supplied credentials in your shell only. **Never echo tokens.**

```bash
LUX=https://luxvant.altcrm.com
OIA=https://oiaproperties.altcrm.com

# --- A. Luxvant ---
curl -s -o /dev/null -w "lux_signin=%{http_code}\n" "$LUX/sign-in"
curl -s "$LUX/api/brand" | jq '{key:.data.key, primary:.data.colors.primary}'
# expect key=luxvant, primary=#003c38

# Super Admin login (password from admin; do not log it)
# curl -s -X POST "$LUX/api/auth/login" -H 'Content-Type: application/json' \
#   -H "Origin: $LUX" \
#   -d '{"email":"…","password":"…"}' > /tmp/lux_sa.json
# SA_TOKEN=$(jq -r '.data.token' /tmp/lux_sa.json)
# curl -s -o /dev/null -w "lux_sa_api=%{http_code}\n" \
#   -H "Authorization: Bearer $SA_TOKEN" -H "Origin: $LUX" \
#   "$LUX/api/auth/notifications"
# expect 200
# shred -u /tmp/lux_sa.json

# Ordinary OIA JWT (login on OIA, then call Luxvant)
# curl -s -X POST "$OIA/api/auth/login" ... > /tmp/oia_user.json
# OIA_TOKEN=$(jq -r '.data.token' /tmp/oia_user.json)
# curl -s -o /dev/null -w "lux_deny=%{http_code}\n" \
#   -H "Authorization: Bearer $OIA_TOKEN" -H "Origin: $LUX" \
#   "$LUX/api/auth/notifications"
# expect 403

curl -s -o /dev/null -w "lux_role=%{http_code}\n" "$LUX/api/auth/users/role/manager"
curl -s -o /dev/null -w "lux_meta=%{http_code}\n" "$LUX/api/get-from-meta-in-day/1"
curl -s -o /dev/null -w "lux_testmail=%{http_code}\n" "$LUX/api/test-account-activated-email"
# expect 403 for Luxvant public risk blocks

# --- B. OIA ---
curl -s -o /dev/null -w "oia_signin=%{http_code}\n" "$OIA/sign-in"
# Existing OIA JWT:
# curl -s -o /dev/null -w "oia_api=%{http_code}\n" \
#   -H "Authorization: Bearer $OIA_TOKEN" -H "Origin: $OIA" \
#   "$OIA/api/auth/notifications"
# expect 200

# Visual: OIA login still purple / OIA PROPERTIES; Luxvant emerald / LUXVANT
# Confirm Areas/Developers/Projects untouched (read-only UI spot-check; no DB writes)
```

---

## F. Remaining risks

| Risk | Severity | Notes |
|------|----------|-------|
| Shared DB — Luxvant SA sees OIA data | High (accepted) | Not fixed in this release; not SaaS tenancy |
| Deploying dirty working tree | High | Must curate Package S/B only |
| Forgetting `npm run build` for Package B | Medium | Stale JS without `useBrand` |
| `BRAND_FORCE` accidentally in prod `.env` | Medium | Ignored in production env check; still remove |
| Referer guard `enforce` without Origin | Low | May 403 some curl tests; use `-H Origin:` |
| `trustProxies(*)` still global | Low/Med | Brand uses raw Host; general proxy trust unchanged |
| Live C1 not verified until deploy | — | Local PASS only so far |

---

## G. Stop-condition check (pre-approval)

| Condition | Result |
|-----------|--------|
| Unrelated OIA modifications in package? | **No** if ConfirmationModal / audits excluded |
| Migration required? | **No** |
| Could affect OIA authentication? | **Analyzed** — Tymon path preserved; brand gate no-op on OIA |
| Prod test needs CRM record writes? | **No** for listed curls |
| Rollback unclear? | **No** — file restore + delete new files |

---

## Approval gate

**I have not deployed anything.**

Reply with one of:

1. `APPROVE Package S only` — security APIs  
2. `APPROVE Package S + B` — security + Luxvant branding UI (recommended)  
3. `CHANGES NEEDED` — with instructions  

After approval, deployment can proceed with the backup → rsync → `optimize:clear` → verification sequence above.
