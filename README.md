# HalalBizs 2.0

A Shopee-style multi-vendor marketplace for halal groceries in Malaysia —
storefront, seller centre, and admin panel in one Laravel app
(`CLAUDE.md:3`). Buyers, sellers, and admins share one codebase and one
`SetLocale` middleware in English, Bahasa Melayu, and Vietnamese
(`config/locales.php:15-17`).

## Stack

- PHP `^8.3`, Laravel `^13.8`, Livewire `^4.3` (`composer.json`)
- MariaDB locally, SQLite in-memory for tests (`phpunit.xml`)
- Tailwind CSS v4 + Vite for the front end (`package.json`)

## Local setup

1. Site is served by [Herd](https://herd.laravel.com) at `halalbizs2.0.test`.
2. Copy `.env.example` to `.env` and set your own `APP_KEY` (`php artisan key:generate`).
3. `composer install`
4. `npm ci && npm run build`
5. `php artisan migrate` (seeders below are optional locally; `migrate:fresh --seed`
   gives a full demo dataset per `CLAUDE.md`)

## Tests

```
php artisan test        # Pest, runs against SQLite in-memory (phpunit.xml)
```

One test intentionally fails under SQLite by design — it exists only to flag
that engine (`CLAUDE.md`: "one engine-guard test FAILS by design"). The real
gate is MariaDB: `php artisan test -c phpunit.mariadb.xml` (local-only config,
gitignored).

## Deploy

Production is cPanel, deployed by `deploy.sh` (interactive or via the
`public/deploy.php` webhook). What it actually does, in order:

1. Aborts if `APP_ENV=production` and `APP_DEBUG=true` (would leak errors).
2. `git fetch origin && git reset --hard origin/main` — a hard reset, always.
3. Clears caches, then `composer install --no-dev --optimize-autoloader` —
   **only if a `composer` binary is on PATH**; otherwise it skips this step
   and assumes `vendor/` is already current (no new deps that deploy).
4. `php artisan migrate --force`.
5. Seeds idempotent reference data every deploy: `RoleSeeder`, `CurrencySeeder`,
   `PageSeeder` (create-only, never overwrites an edited page).
6. `DemoReviewsSeeder` runs only when `APP_ENV != production`.
7. `SEED_DEMO_CATALOGUE=true` in the server `.env` opts in to a full demo
   catalogue (19 sellers, 166 products, certificates, artwork) — off by
   default, independent of `APP_ENV`, safe to re-run.
8. `VietnameseContentSeeder` backfills missing `vi` translations without
   touching admin-authored content.
9. Rebuilds config/route/event/view caches.

Frontend assets (`public/build/`) are **committed to the repo** and built
locally with `npm run build` — the deploy script does not build them. This is
a deliberate choice, not a platform limitation: the host does carry Node
(`/opt/alt/alt-nodejs*`), it's just not wired into the deploy PATH yet
(`deploy.sh`, top-of-file comments).

## Docs

- `CLAUDE.md` — stack, architecture conventions, and the hard rules (money in
  sen, atomic stock/voucher locks, status transitions, snapshots, etc).
- `docs/` — audits, feature gap analysis, and roadmap.
- `marketplace-docs/docs/` — the full functional specs; start at
  `00-overview.md`.
