#!/usr/bin/env bash
# Maintenance-safe Package S / Package B deploy helpers.
# DO NOT RUN until explicit approval.
#
# Usage examples:
#   REPO=/path/to/local STAGE=/tmp/lux_stage ./docs/LUXVANT_DEPLOY_SEQUENCE.sh stage
#   APP_ROOT=/var/www/crm-p STAGE=/tmp/lux_stage ./docs/LUXVANT_DEPLOY_SEQUENCE.sh deploy-s
#   APP_ROOT=/var/www/crm-p STAGE=/tmp/lux_stage ./docs/LUXVANT_DEPLOY_SEQUENCE.sh deploy-b-atomic

set -euo pipefail

REPO="${REPO:-}"
STAGE="${STAGE:-}"
APP_ROOT="${APP_ROOT:-}"
ACTION="${1:-}"

PACKAGE_S=(
  app/Http/Middleware/JwtAuthMiddleware.php
  app/Http/Middleware/EnsureBrandAccess.php
  app/Http/Middleware/BlockRiskyPublicOnLuxvant.php
  app/Http/Middleware/ResolveBrandFromHost.php
  app/Support/Brand/BrandContext.php
  app/Support/Brand/BrandResolver.php
  app/Http/Controllers/Api/BrandController.php
  app/Http/Controllers/Api/AuthController.php
  app/Providers/AppServiceProvider.php
  bootstrap/app.php
  app/Http/Middleware/RequireTrustedReferer.php
  routes/api.php
  config/brands.php
)

PACKAGE_B_SOURCE=(
  resources/views/welcome.blade.php
  resources/js/composables/useBrand.js
  resources/js/main.js
  resources/js/components/Authentication/AuthLandingShell.vue
  resources/js/components/Authentication/SignIn.vue
  resources/js/components/Authentication/SignUp.vue
  resources/js/components/Authentication/ForgotPassword.vue
  resources/js/components/Authentication/ResetPassword.vue
  resources/css/auth-landing.css
  resources/js/components/layout/AppLoader.vue
  resources/js/components/layout/BrandLoader.vue
  resources/js/components/layout/header/index.vue
  resources/js/components/layout/navbar/index.vue
  public/assets/images/brands/luxvant/logo-green.png
  public/assets/images/brands/luxvant/logo-white.png
  public/assets/images/brands/luxvant/favicon.svg
  public/assets/images/brands/luxvant/logo-mark.svg
)

copy_list() {
  local src_root="$1"
  shift
  for f in "$@"; do
    mkdir -p "$STAGE/$(dirname "$f")"
    cp -a "$src_root/$f" "$STAGE/$f"
  done
}

stage() {
  [[ -n "$REPO" && -d "$REPO" ]] || { echo "Set REPO"; exit 1; }
  [[ -n "$STAGE" ]] || STAGE="/tmp/luxvant_sb_stage_$(date +%Y%m%d_%H%M%S)"
  rm -rf "$STAGE"
  mkdir -p "$STAGE"
  copy_list "$REPO" "${PACKAGE_S[@]}"
  copy_list "$REPO" "${PACKAGE_B_SOURCE[@]}"
  mkdir -p "$STAGE/public"
  # Copy build tree only — never copy public/hot (Vite HMR sentinel).
  cp -a "$REPO/public/build" "$STAGE/public/build"
  rm -f "$STAGE/public/hot"

  # Refuse dirty exclusions
  for bad in \
    resources/js/components/ConfirmationModal.vue \
    .env \
    public/hot \
    OIA_CRM_FULL_PERFORMANCE_AUDIT.md
  do
    if [[ -e "$STAGE/$bad" ]]; then
      echo "FATAL: excluded path present in stage: $bad" >&2
      exit 1
    fi
  done

  echo "STAGE=$STAGE"
  find "$STAGE" -type f | wc -l
}

require_app_stage() {
  [[ -n "$APP_ROOT" && -d "$APP_ROOT" ]] || { echo "Set APP_ROOT"; exit 1; }
  [[ -n "$STAGE" && -d "$STAGE" ]] || { echo "Set STAGE to staged release dir"; exit 1; }
}

# Remove Vite HMR sentinel on the production tree only (never touches local REPO).
ensure_no_public_hot() {
  local root="${1:-}"
  [[ -n "$root" && -d "$root" ]] || { echo "ensure_no_public_hot: root required" >&2; exit 1; }
  if [[ -e "$root/public/hot" ]]; then
    rm -f "$root/public/hot"
    echo "removed stray public/hot under $root"
  fi
  if [[ -e "$root/public/hot" ]]; then
    echo "FATAL: public/hot still present under $root" >&2
    exit 1
  fi
}

# Package S only — no public/build touch
deploy_s() {
  require_app_stage
  for f in "${PACKAGE_S[@]}"; do
    mkdir -p "$APP_ROOT/$(dirname "$f")"
    cp -a "$STAGE/$f" "$APP_ROOT/$f"
    echo "deployed S: $f"
  done
  ensure_no_public_hot "$APP_ROOT"
  php "$APP_ROOT/artisan" optimize:clear
  echo "Package S deployed. Old public/build untouched."
}

# Package B with atomic build swap — old build remains until new tree is fully in place
deploy_b_atomic() {
  require_app_stage

  # 1) PHP/Blade/Vue sources + logos (no build yet)
  for f in "${PACKAGE_B_SOURCE[@]}"; do
    mkdir -p "$APP_ROOT/$(dirname "$f")"
    cp -a "$STAGE/$f" "$APP_ROOT/$f"
    echo "deployed B source: $f"
  done

  # 2) Stage new build beside old build (do not delete old yet)
  rm -rf "$APP_ROOT/public/build-next"
  cp -a "$STAGE/public/build" "$APP_ROOT/public/build-next"
  test -f "$APP_ROOT/public/build-next/manifest.json"
  echo "staged public/build-next with manifest"

  # 3) Atomic-ish swap: rename old → build-prev, next → build
  if [[ -d "$APP_ROOT/public/build" ]]; then
    rm -rf "$APP_ROOT/public/build-prev"
    mv "$APP_ROOT/public/build" "$APP_ROOT/public/build-prev"
  fi
  mv "$APP_ROOT/public/build-next" "$APP_ROOT/public/build"
  test -f "$APP_ROOT/public/build/manifest.json"
  echo "swapped build-next → build; previous kept at public/build-prev"

  ensure_no_public_hot "$APP_ROOT"
  php "$APP_ROOT/artisan" optimize:clear
  echo "Package B deployed. Verify, then optionally: rm -rf public/build-prev"
}

retire_prev_build() {
  require_app_stage
  if [[ -d "$APP_ROOT/public/build-prev" ]]; then
    rm -rf "$APP_ROOT/public/build-prev"
    echo "removed public/build-prev"
  fi
}

# Server-side / post-deploy verification (read-only). Exit 0 only if public/hot is absent.
verify_no_hot() {
  local root="${APP_ROOT:-}"
  [[ -n "$root" && -d "$root" ]] || { echo "Set APP_ROOT to the production app root" >&2; exit 1; }
  if [[ -e "$root/public/hot" ]]; then
    echo "FAIL: $root/public/hot exists (Vite HMR sentinel must not be on production)" >&2
    echo "contents:" >&2
    cat "$root/public/hot" >&2 || true
    exit 1
  fi
  echo "PASS: public/hot absent under $root"
}

case "$ACTION" in
  stage) stage ;;
  deploy-s) deploy_s ;;
  deploy-b-atomic) deploy_b_atomic ;;
  retire-prev-build) retire_prev_build ;;
  verify-no-hot) verify_no_hot ;;
  *)
    echo "Usage: $0 {stage|deploy-s|deploy-b-atomic|retire-prev-build|verify-no-hot}" >&2
    exit 1
    ;;
esac
