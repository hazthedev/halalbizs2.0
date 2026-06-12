# 10 — Deployment & Operations

## Environments
**local** (Herd/Sail, Meilisearch + Redis via Docker, Mailpit, iPay88 sandbox) → **staging** (small VPS, sandbox iPay88, robots noindex, seeded demo data, basic-auth gate) → **production**.

## Production topology (start)
One VPS, 4 vCPU / 8 GB, Ubuntu 24.04 — Nginx, PHP-FPM 8.4 (opcache, `pm.max_children` tuned), MySQL 8 (dedicated 2–4 GB buffer pool), Redis, Meilisearch (systemd, master key). Provision + deploy via **Laravel Forge** (zero-downtime deploy script: composer install → migrate --force → config/route/view cache → scout sync check → horizon:terminate → restart fpm). Cloudflare in front: DNS, CDN on media domain, WAF, rate-limit rules on `/login`, `/register`, `/payments/*`. Scale path documented: split DB → add worker box → managed MySQL.

## Services & processes
- **Horizon** (supervisor): queues `default`, `mail`, `media`, `payments` (payments = dedicated worker, low concurrency, high priority — callbacks and requery jobs never wait behind image conversions).
- **Scheduler** — single source of truth, keep in sync with `routes/console.php`:

| Job | Cadence |
|---|---|
| `orders:expire-unpaid` (requery rescue → cancel + restock) | everyMinute |
| `orders:auto-complete` (delivered + 7d → completed, ledger) | hourly |
| `returns:auto-escalate` (seller response timeout) | hourly |
| `rates:sync` (if API enabled, with margin) | daily 06:00 |
| `sitemap:generate` | daily 03:00 |
| `backup:run` (DB + .env reference, → R2) / `backup:clean` | daily 02:00 / 02:30 |
| `horizon:snapshot` | everyFiveMinutes |
| `activitylog:clean` (retain 12 months) | weekly |
| `search-logs:trim` (retain 90 days) | weekly |

## Storage, media, mail
S3 driver → **Cloudflare R2** (public bucket behind CDN domain for product media; private bucket for store documents, invoices, PDPA exports — signed URLs only). Medialibrary conversions queued (`media` queue). Mail: SES or Brevo, queued, DKIM/SPF/DMARC set, separate `mail` queue.

## Observability
Sentry (PHP + JS) with release tagging · Laravel Pulse at `/admin/pulse` (admin-gated) · uptime checks on `/` and `/up` health route (checks DB, Redis, Meilisearch) · alert channels: Sentry → email/Telegram; Forge deploy notifications.

## Env checklist (beyond defaults)
`APP_LOCALE=en` `APP_FALLBACK_LOCALE=en` · DB/Redis creds · `SCOUT_DRIVER=meilisearch` + host/key · R2 keys + buckets + media CDN url · mail creds · `IPAY88_MERCHANT_CODE/KEY` + `IPAY88_SANDBOX` · Turnstile pair · Sentry DSN · `HORIZON_*` auth.

## Security hardening checklist
Forced HTTPS + HSTS · secure/same-site cookies · CSP (self + CDN + Turnstile + pixels per TrackingSettings) · rate limits: auth 5/min, search 60/min, checkout 10/min, callbacks excluded · admin 2FA enforced · `/payments/ipay88/*` CSRF-exempt but signature-gated (never exempt anything else) · uploads: mime+size validation, images re-encoded via conversion (strips payloads), documents bucket private · SQL/file perms standard Forge · dependabot/`composer audit` in CI.

## iPay88 go-live checklist
1. Production MerchantCode/Key received; staging proved on sandbox across FPX + 1 card + 1 wallet.
2. ResponseURL + BackendURL registered with iPay88 exactly as deployed (https, no redirects).
3. Signature unit tests pass against the official doc's worked example (record doc version in 06 §D).
4. Requery verified in production with a RM1 live test order → refund it via portal, confirm `refunded` handling.
5. Expiry job observed cancelling an abandoned live order + restock confirmed.
6. Reconciliation view (08 §E) matches iPay88 portal for the test set.
7. Alert wired: backend callback signature mismatch → Sentry fatal + admin notification.

## Launch checklist
Legal pages live (T&C incl. tax-inclusive pricing stance, Privacy/PDPA, Refund policy — `docs/02` §3) · LHDN e-Invoice obligation re-verified for marketplaces (compliance checkpoint) · seeded categories real · admin accounts 2FA'd, default seeder admin removed · robots + sitemap submitted to Search Console · `migrate:fresh` banned on prod (confirm no destructive scripts in deploy) · backup restore **tested once** · load sanity: `wrk` on home/listing/PDP cached paths · Dusk full-regression green on staging · rollback plan: Forge previous release + DB backup point.
