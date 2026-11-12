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
  cp -a "$REPO/public/build" "$STAGE/public/build"

  # Refuse dirty exclusions
  for bad in \
    resources/js/components/ConfirmationModal.vue \
    .env \
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

# Package S only — no public/build touch
deploy_s() {
  require_app_stage
  for f in "${PACKAGE_S[@]}"; do
    mkdir -p "$APP_ROOT/$(dirname "$f")"
    cp -a "$STAGE/$f" "$APP_ROOT/$f"
    echo "deployed S: $f"
  done
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

case "$ACTION" in
  stage) stage ;;
  deploy-s) deploy_s ;;
  deploy-b-atomic) deploy_b_atomic ;;
  retire-prev-build) retire_prev_build ;;
  *)
    echo "Usage: $0 {stage|deploy-s|deploy-b-atomic|retire-prev-build}" >&2
    exit 1
    ;;
esac
