#!/usr/bin/env bash
# Clear all Laravel and app caches

set -e
cd "$(dirname "$0")"

echo "Clearing all caches..."

php artisan optimize:clear
php artisan cache:clear
php artisan event:clear
php artisan module:clear-compiled 2>/dev/null || true
php artisan schedule:clear-cache 2>/dev/null || true

echo "Done. All caches cleared."
