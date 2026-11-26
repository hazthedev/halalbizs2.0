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
IPTC (JPEG keeps its colour profile). A JPEG too big to decode within
`memory_limit` (or one GD cannot read) is not re-encoded: its EXIF/XMP/IPTC
blocks are cut from the bytes and only the Orientation tag is put back. An
image GD cannot read in another format (animated WebP) is kept as uploaded
and the error is logged. File name, disk, path and media row stay
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

**Erasing accounts deleted before HB-DEL2** (`php artisan accounts:erase-deleted`):
accounts deleted before #282 (live 2026-11-25) were only anonymised, so their
addresses, wishlist, sessions etc. are still stored. The command runs today's
erase step (`AccountDeletionService::erase`) over soft-deleted users whose email
is exactly `deleted-<id>@anonymized.local`; a live account can never match.
Sellers among them get their shop closed. Money tables are never touched.

1. Count first, writes nothing: `php artisan accounts:erase-deleted --dry-run`.
   It prints counts only (accounts matched, rows per table), never names or
   emails. Show the counts to the owner.
2. Only after the owner's OK: `php artisan accounts:erase-deleted`. One
   transaction per account. Safe to rerun: a second run shows 0 rows.
3. Rows keyed only by the original email (password reset tokens, guest
   newsletter rows) cannot be matched any more and are skipped by design.

Coverage: `tests/Feature/Security/StripPhotoMetadataTest.php`.

### Encrypted columns (HB-G198)

These columns are encrypted with `APP_KEY` (Laravel `encrypted` casts); the app
reads them as plain values, the database holds ciphertext:

- `stores.bank_details` (bank name, holder, account number)
- `payouts.bank_snapshot` and `affiliate_payouts.bank_snapshot` (the payout copies)
- `webhook_subscriptions.secret` (queued `SendWebhookJob` payloads are encrypted too)
- `users.tin`

**Losing `APP_KEY` loses these values for good.** Keep a copy of `APP_KEY` off
the server before the migration that encrypts them
(`2026_11_24_170100_encrypt_bank_secret_and_tin_columns`) runs. To rotate the
key, put the old key in `APP_PREVIOUS_KEYS` so old rows still decrypt. None of
these columns can be searched, sorted or uniquely indexed in SQL; compare them
in PHP after reading. The migration's `down()` decrypts the rows back.

### Unshipped orders auto-cancel (`orders:cancel-unshipped`, hourly)

PAY-14 (HB-DEC-1124d). A sub-order still **confirmed** or **processing**
(not shipped) 7 days after its order was placed (online: after `paid_at`) is
cancelled through `OrderService::cancel` as `system`: restock, vouchers and
coins back, a paid online order refunded in full, and the usual
"Order cancelled" notification (database + mail) to the buyer and the seller.
On day 5 the seller gets one "Ship soon" notification (database + mail;
`sub_orders.unshipped_warned_at` keeps it to one). Days are settings
`order.unshipped_cancel_days` (7) and `order.unshipped_warn_days` (5).
The row is locked before the cancel, so a ship that commits first wins.
Check it is scheduled: `php artisan schedule:list | grep cancel-unshipped`.
Coverage: `tests/Feature/Orders/CancelUnshippedOrdersTest.php`.

### Security limits and redirects (HB-B3B)

- **Send limits** (`config/limits.php`): chat is 10 messages a minute per user
  per conversation (buyer and seller inbox); the help centre allows 5 new
  support tickets an hour per user. Over the limit the form shows "Too many
  requests / attempts, try again in …" and nothing is saved. The app API keeps
  its own route throttles (`AppServiceProvider`, `api-chat` and friends).
- **Upload names**: stored names come from `UploadName::safe()` (slug of the
  name + extension from the detected type), `UploadName::unguessable()` or a
  fixed name; the extension is always the detected one, never the client's.
- **Webhook URLs** (`WebhookSubscription::isUrlSafe`): every A and AAAA answer
  must be public; a name that does not resolve is refused (nothing is sent;
  the job retries with its backoff, in case it was a resolver hiccup);
  delivery never follows a redirect (a 3xx fails the job, no retries). Tests never use real
  DNS: call `fakeHostResolver([...])` from `tests/Pest.php`.
- **https and www** (`public/.htaccess`, live host only): `www.halalbizs.com`
  301s to `https://halalbizs.com` (any scheme), then plain
  `http://halalbizs.com` 301s to https. `/.well-known/` is never redirected
  (AutoSSL / ACME checks). Local `*.test` and the testing copy are
  untouched. Check after a deploy:
  `curl -sI http://halalbizs.com/x?y=1 | grep -i location` and
  `curl -sI https://www.halalbizs.com/x?y=1 | grep -i location`, both
  `https://halalbizs.com/x?y=1`.
- **Counted on success** (HB-G240): the chat and ticket limits take a slot with
  one increment under a cache lock (`App\Support\SendLimit`) and give it back
  when the save fails, so a failed send never uses up the allowance and two
  sends at the same moment cannot both slip past the last slot. The lock is
  needed because the file cache store increments as a read then a write; a
  lock not had within 2 seconds is a refusal.
- **Product attribute ticks** (HB-G238): at most 100 per product
  (`Form::MAX_ATTRIBUTE_VALUES`); over that the list is refused before any
  per-id lookup. "Generate copy" checks the ticks on their own and leaves the
  form's other errors in place (HB-G239).
- **Webhook pinning** (HB-G243): delivery connects only to the addresses the
  URL check approved (`CURLOPT_RESOLVE`), so a DNS answer that changes between
  check and connect is never used. Multicast (224.0.0.0/4, ff00::/8), NAT64
  (64:ff9b::/96, 64:ff9b:1::/48) and the old site-local fec0::/10 are refused
  with the private ranges.
- **HSTS** (HB-G242, `SecurityHeaders`): `Strict-Transport-Security:
  max-age=31536000` on https requests to `halalbizs.com` only. No
  `includeSubDomains` (store subdomains are dropped, HB-DEC-1124e) and no
  `preload`. The `www` and plain-http 301s come from `.htaccess` and carry none.
- **CORS** (HB-G180, `config/cors.php`): browser calls to `api/*` are allowed
  from `CORS_ALLOWED_ORIGINS` (comma separated, default
  `https://halalbizs.com`). The phone app sends no Origin, so it is unaffected.
- **Bare server IP** (HB-G244): a request whose Host is the server IP never
  reaches this app (LiteSpeed answers it from the server's default site), and
  `trustHosts()` in `bootstrap/app.php` refuses any Host outside the APP_URL
  domain in production anyway.
- **Admins** (HB-DEC-1124f item 9, HB-G234, HB-G237): an admin may remove
  another admin, or change their permissions, only when holding every
  permission that admin holds (a superadmin holds them all). The keep-one
  superadmin count is a locking read inside the same transaction as the
  removal, demotion or account deletion, and the last superadmin cannot delete
  their own account (web or app).
- **Password reset** (HB-G235): a reset on a never-verified account drops its
  Google link (it came from a Google email Google never verified, maybe a
  squatter's).

Coverage: `tests/Feature/Security/ChecklistSmallItemsTest.php`,
`tests/Feature/Security/CorsTest.php`, `tests/Feature/Admin/SuperadminTest.php`,
`tests/Feature/Auth/GoogleAuthTest.php`, `tests/Feature/Ops/LaunchHardeningTest.php`.

### User guides (HB-SOP)

The buyer, seller and admin guides live in `resources/sop/{buyer,seller,admin}.{en,vi}.md`
(Vietnamese has its own files; every other locale reads English). `/help/buyer` and
`/help/seller` are public, with two cards on the `/help` hub; `/admin/help` is for any
admin (Support > Admin guide). `App\Support\SopGuide` renders them with
`Str::markdown` and raw HTML stripped, so the reviewers' `<!-- CHECK -->` and
`<!-- SHOT -->` comments never reach the page. Edit a guide by editing its file.
Coverage: `tests/Feature/Help/SopGuideTest.php`.

## Hosting runbook

**PHP on the host (HOST-01), read 2026-11-20 on the live account:** PHP 8.4.28
CLI (`/opt/alt/php84`, supported to 31 Dec 2028). Extensions the app needs, all
present: `bcmath ctype curl dom fileinfo gd intl mbstring openssl pdo_mysql
tokenizer xml zip`. Re-read with `php -v` and `php -m` after any host PHP change
and update this line with the new date. The web side runs lsphp; its version and
limits are in the host panel (CloudLinux selector), not visible from the shell.

**Cron (OPS-05).** One scheduler line per site runs `php artisan schedule:run`
every minute; everything in `routes/console.php` hangs off it (the queue worker
too). Live `halalbizs.com` has 2 lines of the account crontab; the testing copy
has none. The account crontab holds other sites' lines and tokens, so never
print it whole. Edit it in six steps, never with a pipe into `crontab -`
(a failed filter installs an empty table):

1. `crontab -l > ~/backups/crontab-<date>-<why>` (the way back).
2. `cp` that file to `/tmp/crontab.new`.
3. Edit the copy only.
4. `diff` backup against the copy: only the intended line may differ.
5. `crontab /tmp/crontab.new`.
6. `crontab -l > /tmp/crontab.after; cmp /tmp/crontab.after /tmp/crontab.new`,
   then count the job lines against the count before.

The account line count is logged daily (21 lines on 2026-11-20 to 2026-11-23);
the old alert said 25 expected, and the owner has not yet said which count is
right. Until that is closed, treat 21 as the current record and do not "repair"
the table up to 25.

Failed scheduled tasks send one mail to `OPS_ALERT_EMAIL` (falls back to
`ADMIN_EMAIL`); with neither set, or when the mail cannot be sent, the admin
bell gets the alert instead. Tasks with the alert: `backup:run`,
`certificates:watch-expiry`, and every job that moves money or order state
(G249): `orders:expire-unpaid`, `orders:auto-complete`,
`orders:cancel-unshipped`, `returns:auto-escalate`, `coins:expire`,
`affiliates:lock-commissions`, `ledger:release-holds`, `subscriptions:process`.
Cap: at most one alert per task per Malaysia calendar day (an every-minute job
failing all day sends one mail, not 1,440); every failure is still written to
the log. Test: `tests/Feature/Ops/ScheduleFailureAlertTest.php`.

**Backups (G246).** `backup:run` writes each dump file mode 0600 (owner only)
on a local backup disk.

**Time zones (G248).** Times are stored in UTC and shown in Malaysia time
(APP_DISPLAY_TIMEZONE). The database session time zone on live is the server's
(IST). Never change the DB connection time zone without first converting the
TIMESTAMP columns to DATETIME, or every stored time shifts 5h30m.

**WHIP auto-approve (G247, HB-DEC-1125b).** System > Settings > Moderation,
**Approve WHIP-confirmed certificates without admin review**, superadmin only,
default OFF (set OFF on live by the settings migration). OFF: a certificate WHIP
confirms waits in Catalog > Halal certificates marked **WHIP confirmed** until an
admin approves it, and the 4-hourly WHIP sync never approves a certificate an
admin has not. ON: WHIP-confirmed certificates are approved at once (the
behaviour before 2026-11-25).

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
