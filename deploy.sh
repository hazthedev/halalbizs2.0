#!/bin/bash
#
# HalalBizs 2.0 — cPanel deploy script.
#
# Usage:
#   bash deploy.sh                 # interactive (cPanel Terminal)
#   public/deploy.php?token=...    # HTTPS webhook (LiteSpeed web user)
#
# Performs: sync to origin/main, dep sync, migrations, reference seeds, cache rebuild.
# Safe to re-run (idempotent).
#
# NOTE: when triggered by the deploy.php webhook this runs as the LiteSpeed
# web user with a minimal PATH — git is found but composer/php may not be, so
# we add the usual cPanel locations and resolve php/composer explicitly, and
# make composer optional (it's a no-op when no dependency changed).
#
# Frontend assets (Vite/Tailwind) are committed under public/build/ because the
# cPanel host has no Node — run `npm run build` + commit locally on FE changes.
#
set -e

cd "$(dirname "$0")"

export PATH="/usr/local/bin:/opt/cpanel/composer/bin:/usr/local/sbin:/usr/bin:/bin:$HOME/bin:$PATH"

PHP_BIN="$(command -v php || command -v ea-php84 || command -v ea-php83 || echo /usr/local/bin/php)"
echo "→ php: $PHP_BIN"

# Tolerate a repo owned by a different uid than the web user (git safety).
git config --global --add safe.directory "$(pwd)" 2>/dev/null || true

echo "→ sync to origin/main"
git fetch origin
git reset --hard origin/main
echo "  now at $(git rev-parse --short HEAD)"

echo "→ composer install --no-dev --optimize-autoloader"
if command -v composer >/dev/null 2>&1; then
    composer install --no-dev --optimize-autoloader
elif [ -f composer.phar ]; then
    "$PHP_BIN" composer.phar install --no-dev --optimize-autoloader
else
    echo "  ! composer not found in PATH — skipping (vendor/ assumed current; no new deps this deploy)"
fi

echo "→ php artisan migrate --force"
# Non-fatal: a benign "table already exists" (out-of-sync migrations record)
# must not block the cache rebuild below. Real errors still print above.
"$PHP_BIN" artisan migrate --force || echo "  ! migrate reported errors (see above) — continuing to cache rebuild"

echo "→ seed idempotent reference data"
# Only idempotent seeders belong here, safe to re-run every deploy. NOT the full
# DatabaseSeeder — that pulls in content/admin/demo seeders that aren't deploy-safe.
# RoleSeeder (roles + admin permissions via firstOrCreate) and CurrencySeeder
# (currencies via updateOrCreate) are both idempotent and required for the app
# to function. Add more reference seeders here only after confirming idempotency.
"$PHP_BIN" artisan db:seed --class=RoleSeeder --force || echo "  ! role seed reported errors — continuing"
"$PHP_BIN" artisan db:seed --class=CurrencySeeder --force || echo "  ! currency seed reported errors — continuing"

echo "→ clear caches"
"$PHP_BIN" artisan optimize:clear

echo "→ rebuild caches"
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan event:cache
"$PHP_BIN" artisan view:cache

echo
echo "✓ deploy done — hard-refresh your browser (Ctrl+F5) to bust old CSS / JS"
