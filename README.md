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

0. Holds (`DEPLOY HELD: load <n>`, exit 1, nothing touched) when the 1-minute
   load in `/proc/loadavg` is above 17. Override from a terminal only:
   `DEPLOY_IGNORE_LOAD=1 bash deploy.sh`.
1. Aborts if `APP_ENV=production` and `APP_DEBUG=true` (would leak errors).
2. `git fetch origin && git reset --hard origin/main` — a hard reset, always.
   Prints `now at <new> (was <previous>)`: keep the previous sha, it is the
   way back.
3. Clears caches, then `composer install --no-dev --optimize-autoloader` —
   **only if a `composer` binary is on PATH**; otherwise it skips this step
   and assumes `vendor/` is already current (no new deps that deploy).
4. `php artisan migrate --force`. **A failed migrate stops the deploy**: the
   seeders are skipped, the caches are rebuilt for the code on disk (so the
   site does not run uncached), and the script exits 1 with
   `DEPLOY STOPPED at <sha>`. The code at `<sha>` is live against a schema
   that may be part-way — read the error, then fix forward or roll back
   (below). A benign "table already exists" (out-of-sync migrations record)
   stops too: fix the record by hand (`php artisan migrate:status`), re-run.
5. Seeds idempotent reference data every deploy: `RoleSeeder`, `CurrencySeeder`,
   `PageSeeder` (create-only, never overwrites an edited page).
6. `DemoReviewsSeeder` runs only when `APP_ENV != production`.
7. `SEED_DEMO_CATALOGUE=true` in the server `.env` opts in to a full demo
   catalogue (19 sellers, 166 products, certificates, artwork) — off by
   default, independent of `APP_ENV`, safe to re-run.
8. `VietnameseContentSeeder` backfills missing `vi` translations without
   touching admin-authored content.
9. Rebuilds config/route/event/view caches.

The **last line** is the result: `DEPLOYED <sha>` (all steps ok),
`DEPLOY STOPPED at <sha>` (a step failed, any step) or `DEPLOY HELD: load <n>`.
A caller compares `<sha>` with `origin/main`; anything else is not a deploy.
A change to `deploy.sh` itself needs the webhook triggered **twice** (bash runs
the old in-memory copy on the first pass).

### Rollback (the way back)

Not yet tried on the testing copy (OPS-23): these are written steps, not a
tested drill. Haze decides a rollback on production.

1. **Find the previous sha**: the `now at <new> (was <previous>)` line of the
   deploy output, or `git reflog -5` in the cPanel Terminal.
2. **Normal way back = revert on `main`, then deploy.** The webhook always
   resets to `origin/main`, so a manual reset on the server is undone by the
   next deploy. `git revert <bad merge sha>` → PR → merge → trigger the
   webhook. Use this whenever the site still works well enough to wait for a PR.
3. **Emergency (site broken now), cPanel Terminal only:**
   ```bash
   cd ~/<site folder>
   git reset --hard <previous sha>
   php artisan optimize:clear
   php artisan config:cache && php artisan route:cache && php artisan event:cache && php artisan view:cache
   ```
   Do **not** run `deploy.sh` afterwards — it resets back to `origin/main`.
   Follow up with step 2 so `main` matches what is live.
4. **Migrations.** Code rollback leaves the schema as it is, and that is the
   default: an additive migration (new table, new nullable column, new index)
   works with the older code, so leave it in place. Run
   `php artisan migrate:rollback --step=<n>` only when ALL of these hold:
   the migration has a written `down()`, it only added things, and no real
   rows were written into what it added (rollback deletes them). It is
   **not safe** when the migration dropped or renamed a column or table,
   changed a column type, or moved/rewrote data — `down()` cannot bring the
   old values back. Take a `mysqldump` of the database before any rollback.

Frontend assets (`public/build/`) are **committed to the repo** and built
locally with `npm run build` — the deploy script does not build them. This is
a deliberate choice, not a platform limitation: the host does carry Node
(`/opt/alt/alt-nodejs*`), it's just not wired into the deploy PATH yet
(`deploy.sh`, top-of-file comments).

### Admin form pages

Platform vouchers, banners, pages and help articles have separate `/create` and
`/{record}/edit` pages under their existing list paths and permission gates.
Save and Cancel return to the list. Content saves still stage drafts; publish,
discard, delete, toggles and ordering stay on the list. All fields, uploads,
translations, sanitization and voucher payloads keep their existing behavior.
Coverage: `FormPagesTest` and `FormPagePayloadTest`, plus the existing content suites.

### Photo privacy: EXIF/GPS (HB-B2)

A phone photo can carry the GPS position it was taken at. Every upload that
goes through medialibrary (reviews, returns, product and variant images, store
logo and banner, KYC and certificate images, CMS images) is rewritten on save
by `App\Listeners\StripPhotoMetadata`: JPEG, PNG and WebP originals are turned
upright from their EXIF orientation and re-encoded by GD without EXIF, XMP or
IPTC (JPEG keeps its colour profile). File name, disk, path and media row stay
the same; only `media.size` changes. PDFs, videos, GIF and BMP are untouched.
HEIC is never accepted by an upload rule.

**Cleaning files stored before the fix** (`php artisan media:strip-exif`):

1. Dry run first (the default, writes nothing): `php artisan media:strip-exif`.
   It prints counts only, per model/collection: files, images, with EXIF,
   with GPS, errors. No paths, no names. Show the counts to the owner.
2. Only after the owner's OK: `php artisan media:strip-exif --apply`. It
   rewrites each image that has metadata in place and lists the rewritten
   paths in `storage/logs/media-strip-exif-<stamp>.log`. Images without
   metadata are skipped, so a re-run is safe; a second run shows 0 with EXIF.
3. Take a file backup of `storage/app` before `--apply` on production (the
   rewrite is not reversible). Conversions (thumbnails) are GD copies and
   already carry no EXIF.

Coverage: `tests/Feature/Security/StripPhotoMetadataTest.php`.

### User guides (HB-SOP)

The buyer, seller and admin guides live in `resources/sop/{buyer,seller,admin}.{en,vi}.md`
(Vietnamese has its own files; every other locale reads English). `/help/buyer` and
`/help/seller` are public, with two cards on the `/help` hub; `/admin/help` is for any
admin (Support > Admin guide). `App\Support\SopGuide` renders them with
`Str::markdown` and raw HTML stripped, so the reviewers' `<!-- CHECK -->` and
`<!-- SHOT -->` comments never reach the page. Edit a guide by editing its file.
Coverage: `tests/Feature/Help/SopGuideTest.php`.

## Docs

- `CLAUDE.md` — stack, architecture conventions, and the hard rules (money in
  sen, atomic stock/voucher locks, status transitions, snapshots, etc).
- `docs/` — audits, feature gap analysis, and roadmap.
- `marketplace-docs/docs/` — the full functional specs; start at
  `00-overview.md`.

### Seller form pages

Shop vouchers use `/seller/vouchers/create` and `/seller/vouchers/{voucher}/edit`.
Group-buy creation uses `/seller/group-buys/create`; deals retain their existing End action.
Live-session creation uses `/seller/live/create`; Manage rail opens `/seller/live/{session}`.
Lists contain no editor. Save and Cancel return to the list; seller records remain store-scoped.
Payload, navigation and ownership coverage: `SellerVoucherPagesTest`, `GroupBuyPagesTest`, `LiveSessionPagesTest`.
