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

# Declared environment drives the prod-safety gates below. .env is server-managed
# (gitignored), so this is stable across deploys. Default to production when
# unreadable — the safe choice (skips demo data, enforces the debug guard).
# ponytail: grep the .env line directly; no need to boot the framework to read APP_ENV.
APP_ENV="$(grep -E '^APP_ENV=' .env 2>/dev/null | head -1 | cut -d= -f2- | tr -d " \"'")"
APP_ENV="${APP_ENV:-production}"
echo "→ environment: $APP_ENV"

# Fail-closed: a production deploy must never run with debug on — it renders the
# full Ignition page (SQL, stack trace, file paths, DB host) on any 500. Abort
# loudly rather than silently rewriting the operator's .env.
if [ "$APP_ENV" = "production" ] && grep -Eq '^APP_DEBUG=[[:space:]]*true' .env 2>/dev/null; then
    echo "✗ ABORT: APP_ENV=production but APP_DEBUG=true — refusing to deploy (would leak errors)."
    echo "  Set APP_DEBUG=false in the server .env, then re-run."
    exit 1
fi

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

# DEMO DATA (non-production only) — populates the reviews feature with demo
# ratings on first run (idempotent: skips once any review exists; faker-free so
# it runs under the web user with no dev deps). Auto-skipped when APP_ENV=production
# so real prod never gets demo content — no manual edit needed at cutover.
if [ "$APP_ENV" != "production" ]; then
    "$PHP_BIN" artisan db:seed --class=DemoReviewsSeeder --force || echo "  ! demo reviews seed reported errors — continuing"
else
    echo "→ skip demo data (production)"
fi

echo "→ clear caches"
"$PHP_BIN" artisan optimize:clear

echo "→ rebuild caches"
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan event:cache
"$PHP_BIN" artisan view:cache

echo
echo "✓ deploy done — hard-refresh your browser (Ctrl+F5) to bust old CSS / JS"
