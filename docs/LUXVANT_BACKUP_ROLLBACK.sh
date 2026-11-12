#!/usr/bin/env bash
# Luxvant Package S+B — backup / rollback helpers
# DO NOT RUN until deployment is explicitly approved.
# Usage:
#   APP_ROOT=/var/www/crm-p ./docs/LUXVANT_BACKUP_ROLLBACK.sh backup
#   APP_ROOT=/var/www/crm-p BACKUP_DIR=/path/to/stamp ./docs/LUXVANT_BACKUP_ROLLBACK.sh rollback

set -euo pipefail

APP_ROOT="${APP_ROOT:-}"
if [[ -z "$APP_ROOT" || ! -d "$APP_ROOT" ]]; then
  echo "Set APP_ROOT to the production application root (must exist)." >&2
  exit 1
fi

ACTION="${1:-}"
STAMP="$(date +%Y%m%d_%H%M%S)"

# Files that may already exist on production and will be replaced.
REPLACED_FILES=(
  app/Http/Middleware/JwtAuthMiddleware.php
  app/Http/Controllers/Api/AuthController.php
  app/Providers/AppServiceProvider.php
  bootstrap/app.php
  app/Http/Middleware/RequireTrustedReferer.php
  routes/api.php
  resources/views/welcome.blade.php
  resources/js/main.js
  resources/css/auth-landing.css
  resources/js/components/Authentication/AuthLandingShell.vue
  resources/js/components/Authentication/SignIn.vue
  resources/js/components/Authentication/SignUp.vue
  resources/js/components/Authentication/ForgotPassword.vue
  resources/js/components/Authentication/ResetPassword.vue
  resources/js/components/layout/AppLoader.vue
  resources/js/components/layout/BrandLoader.vue
  resources/js/components/layout/header/index.vue
  resources/js/components/layout/navbar/index.vue
)

# Files introduced by this deployment (delete on rollback if present).
NEW_FILES=(
  app/Http/Middleware/EnsureBrandAccess.php
  app/Http/Middleware/BlockRiskyPublicOnLuxvant.php
  app/Http/Middleware/ResolveBrandFromHost.php
  app/Http/Controllers/Api/BrandController.php
  app/Support/Brand/BrandContext.php
  app/Support/Brand/BrandResolver.php
  config/brands.php
  resources/js/composables/useBrand.js
  public/assets/images/brands/luxvant/logo-green.png
  public/assets/images/brands/luxvant/logo-white.png
  public/assets/images/brands/luxvant/favicon.svg
  public/assets/images/brands/luxvant/logo-mark.svg
)

NEW_DIRS=(
  app/Support/Brand
  public/assets/images/brands/luxvant
)

backup() {
  local BACKUP_DIR="${BACKUP_DIR:-$APP_ROOT/storage/deploy-backups/luxvant_sb_$STAMP}"
  mkdir -p "$BACKUP_DIR"

  echo "Backing up to $BACKUP_DIR"

  for f in "${REPLACED_FILES[@]}"; do
    if [[ -e "$APP_ROOT/$f" ]]; then
      mkdir -p "$BACKUP_DIR/$(dirname "$f")"
      # -a preserves mode, ownership (when permitted), timestamps, structure
      cp -a "$APP_ROOT/$f" "$BACKUP_DIR/$f"
      echo "  backed up $f"
    else
      echo "  skip missing $f"
    fi
  done

  if [[ -d "$APP_ROOT/public/build" ]]; then
    mkdir -p "$BACKUP_DIR/public"
    cp -a "$APP_ROOT/public/build" "$BACKUP_DIR/public/build"
    echo "  backed up public/build (complete tree + manifest)"
  else
    echo "  WARNING: public/build missing on production" >&2
  fi

  # Record inventory for audit
  (
    cd "$BACKUP_DIR"
    find . -type f | sort > "$BACKUP_DIR/BACKUP_MANIFEST.txt"
  )
  echo "BACKUP_DIR=$BACKUP_DIR"
  echo "Files backed up: $(wc -l < "$BACKUP_DIR/BACKUP_MANIFEST.txt")"
}

rollback() {
  local BACKUP_DIR="${BACKUP_DIR:-}"
  if [[ -z "$BACKUP_DIR" || ! -d "$BACKUP_DIR" ]]; then
    echo "Set BACKUP_DIR to the stamp directory created by backup." >&2
    exit 1
  fi

  echo "Rolling back from $BACKUP_DIR"

  # 1) Restore every replaced file (including public/build) with permissions.
  if [[ -d "$BACKUP_DIR/public/build" ]]; then
    rm -rf "$APP_ROOT/public/build"
    mkdir -p "$APP_ROOT/public"
    cp -a "$BACKUP_DIR/public/build" "$APP_ROOT/public/build"
    echo "  restored public/build + manifest.json"
  fi

  for f in "${REPLACED_FILES[@]}"; do
    if [[ -e "$BACKUP_DIR/$f" ]]; then
      mkdir -p "$APP_ROOT/$(dirname "$f")"
      cp -a "$BACKUP_DIR/$f" "$APP_ROOT/$f"
      echo "  restored $f"
    fi
  done

  # 2) Delete only newly introduced files/dirs from this deployment.
  for f in "${NEW_FILES[@]}"; do
    if [[ -e "$APP_ROOT/$f" ]]; then
      rm -f "$APP_ROOT/$f"
      echo "  deleted new file $f"
    fi
  done
  for d in "${NEW_DIRS[@]}"; do
    if [[ -d "$APP_ROOT/$d" ]]; then
      rm -rf "$APP_ROOT/$d"
      echo "  deleted new dir $d"
    fi
  done

  if [[ -f "$APP_ROOT/artisan" ]]; then
    php "$APP_ROOT/artisan" optimize:clear || true
  fi

  echo "Rollback complete."
}

case "$ACTION" in
  backup) backup ;;
  rollback) rollback ;;
  *)
    echo "Usage: APP_ROOT=/path BACKUP_DIR=/path $0 {backup|rollback}" >&2
    exit 1
    ;;
esac
