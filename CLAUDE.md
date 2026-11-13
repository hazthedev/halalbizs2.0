## Hard rules (violations = rejected PR)

1. **Money is integers in sen.** Columns end `_sen`. Arithmetic via brick/money or plain int. Floats are banned for money — in PHP, JS, and Blade.
2. **Sub-order status never changes by assignment.** Only `SubOrderStatusService::transition()` — it validates the transition, writes `order_status_histories`, fires events.
3. **Stock and voucher mutations are atomic.** `lockForUpdate()` on variants and vouchers inside the checkout transaction. Validate + consume in the same lock.
4. **iPay88 fulfilment trusts only the BackendURL callback + a successful requery.** The browser ResponseURL is UX-only. Callbacks are idempotent.
5. **Snapshots are sacred.** `order_items` carry name/variant/price at purchase; `orders` carry the address json; `sub_orders` carry commission_rate. Never read live product data for historical orders.
6. **Every product has ≥1 variant.** No-variation products get a default variant. Cart/checkout/order code only touches variants.
7. **Translatable writes include `en` at minimum** (fallback locale). UI strings go in `lang/` files, never hardcoded.
8. **Emerald = money/action; brass = premium/ornament.** "Souk (Emerald & Brass)" design language per `marketplace-docs/docs/03-design-system.md`: warm ivory canvas, Fraunces + Plus Jakarta Sans, soft elevation (`shadow-soft/card/pop`), and Islamic geometric ornament (khatam/girih) are sanctioned. Never recolour money/action elements brass, or decorate with emerald.
9. **Tests accompany features.** Checkout, vouchers, ledger, status transitions, and iPay88 callback paths require Pest coverage including the race-condition cases.
10. **No new packages** without noting why in the PR description.

Master copy: `/Users/Apple/Desktop/Hermes/projects/halal-ecosystem/hard-rules.md`.

1. **Main is live.** Never push to main. One PR per change; Hermes reviews, merges and deploys.
2. **Deploy only through the repo's `bin/ship`** (HalalBizs: Hermes's deploy step). Never while the shared server's 1-minute load is above 13. Every server command gets a line in Hermes's ledger (`main/records/server-command-ledger.md`). [bin/ship enforces the load gate]
3. **No live data writes** (a live database, a real user's account, a live customer's record) without the owner's written OK. Never run migrations, seeds, tinker or SQL against a live database by hand. Silence means nothing is written.
4. **No secrets anywhere visible.** Never print, commit or log a password, token or key; never put one in a URL or chat. Read `.env` by key name only. Deploy tokens live in `~/.hermes-secrets`. [TEST: no `env()` outside `config/`]
5. **Commit explicit file paths only.** Never `git add -A`, `git add .`, a directory or a glob; never `commit -a`, `stash`, `reset --hard` or a force push.
6. **Bugs are fixed test-first:** a red test that shows the bug, then the fix. Run targeted Pest per change. Full suite once before a PR, and never while ATP release gates are waiting (the Mac is shared).
7. **End-to-end tests run locally or on a demo site** with fresh `e2e-<timestamp>` accounts, cleaned up afterwards. Never on a site with real customers (HalalBizs: local only).
8. **The builder never audits its own work.** Security audits are source-only and run by a separate session.
9. **No crontab edits.** Schedules go in `routes/console.php`.
10. **No new package, paid service or external API** without the owner's OK, written in the PR. Demo sites use fake gateways guarded by `config('app.demo')`.
11. **Rate limiters are defined in `AppServiceProvider`, never in a routes file**: production caches routes, so a limiter in a routes file never runs (SertuCert live /verify 500, fixed #19). [TEST]
12. **No debug leftovers in app code** (`dd`, `dump`, `ray`, `var_dump`). [TEST]
13. **A changed screen or feature updates its docs in the same PR** (README, coverage notes, feature map).
14. **This Mac has 8 GB:** at most 2 subagents, one browser at a time, kill what you start by pid, background commands get `</dev/null`.
15. **Settled decisions are not re-argued.** Read `projects/<slug>/<slug>-decisions.md` in Hermes before changing behaviour; a change to a ruling needs Haze's word.

- Keep the 10 existing hard rules in `CLAUDE.md` (money in sen, status via service, atomic stock, iPay88 callback, snapshots, variants, translations, design colours, tests, packages).
- **WHIP is read-only:** SELECT only, through the `whip` connection; no WHIP file, table or screen changes. Tests fake WHIP; the real `whip` connection is never used in tests. [TEST: no write call on the `whip` connection in `app/`]
- **halalbizs.com and the old preview share ONE database:** a write on the preview is a write on live.
- WHIP calls run outside database transactions; locks stay short (#215).

# CLAUDE.md — HalalBizs Multi-Vendor Marketplace

Shopee-style multi-vendor marketplace for the Malaysian market. One Laravel app, three contexts:
storefront `/` (guest + buyer), seller centre `/seller`, admin `/admin`.
Full specs live in `marketplace-docs/docs/` — **read `marketplace-docs/docs/00-overview.md` before your first task.**

## Stack

- Laravel 13.x · PHP 8.4 · MariaDB locally (`hbiz2_qa`; since 2026-08-06 — tests still default to SQLite in-memory) · database queue drained by the scheduler
- Livewire 4 + Alpine.js + Blade · Tailwind CSS v4 (tokens in `resources/css/app.css`) · Vite
- Scout (`collection` driver locally; Meilisearch in prod) · spatie: permission, medialibrary, translatable (v5 namespaces: `Models\Concerns\LogsActivity`, `Support\LogOptions`), sluggable, activitylog, settings
- brick/money · Pest · Pint · Larastan

## Commands

```bash
php artisan migrate:fresh --seed   # full demo dataset (local only — NEVER against hbiz2_qa's preview mirror data you care about)
php artisan test                   # Pest on SQLite — one engine-guard test FAILS by design (it exists to flag this config)
php artisan test -c phpunit.mariadb.xml   # the real gate: full suite on MariaDB (local-only config, gitignored — see .gitignore)
vendor/bin/pint --dirty            # run before any commit
composer analyse                   # Larastan level 1 — must be clean before any commit
npm run build                      # Vite/Tailwind
npm run e2e                        # 15 Playwright journey specs — NOT a gate (not in CI, unrun since 2026-06-24). See the note below
```

Site served by Herd at **http://halalbizs2.0.test**. Demo logins (local seed): `admin@halalbizs.test`,
`seller@halalbizs.test`, `buyer@halalbizs.test` — all password `password`.

**On the e2e specs (corrected 2026-08-14).** This file used to say they "predate every UI rewrite
since". That was not true and it kept them shelved: a static re-check found every `page.goto()`
target still resolves to a live route, all four `data-testid`s still in blade, and all eight
`e2e:*` fixture commands still present. Dormant here means **unrun, not unrunnable** — nothing
in CI invokes them and `halalbizs2.0.test` does not resolve on every dev machine (the subdomain
instructions below are Windows/Valet-era), so nobody has had a working host to point them at.
They were last executed 2026-06-24 and have NOT been re-run since, so treat them as unproven
rather than broken. One real rot was found and fixed in that check — see `13-dashboards.spec.ts`.

**Store subdomains** (`{slug}.halalbizs2.0.test`): served via the wildcard nginx
`server_name` plus a `Sites\0` junction (Valet's wildcard lookup resolves the
LAST dot-segment of the site name — `0` for this repo; recreate with
`cmd /c mklink /J %USERPROFILE%\.config\herd\config\valet\Sites\0 <repo>` if lost).
Herd's DNS only resolves exact site names, so to BROWSE a store subdomain add a
hosts-file line (`127.0.0.1 <slug>.halalbizs2.0.test`, admin shell) — Playwright
maps `*.halalbizs2.0.test → 127.0.0.1` via launch args instead. Production needs
wildcard DNS + a wildcard TLS cert (docs/10).

## Architecture conventions

- Route files: `routes/web.php` (storefront), `routes/seller.php`, `routes/admin.php`. RESTful resource naming only.
- Livewire namespaces mirror contexts: `App\Livewire\Storefront\*`, `App\Livewire\Seller\*`, `App\Livewire\Admin\*`. Shared UI in Blade components `resources/views/components`.
- Business logic lives in `App\Services\*` (CheckoutService, SubOrderStatusService, CommissionResolver, Ipay88Service, CartService, CurrencyConverter, VoucherService, LedgerService). Livewire components stay thin.
- Enums in `App\Enums` — string-backed, with `label()`. No DB enum columns.
- Middleware: `SetLocale`, `SetDisplayCurrency`, `EnsureSeller` (role + approved store), `EnsureAdmin`.
- Time: storage and `app.timezone` are UTC; users see and type Malaysia time (`config('app.display_timezone')`). Render a stored time with `$t->display()->format(...)`; read typed input with `DisplayTime::parse()`; "today"/day filters use `DisplayTime::dayStart()/dayEnd()/today()` (`App\Support\DisplayTime`). `tests/Feature/Ops/DisplayTimeTest.php` guards the views.
- Money formatting: `@money($sen)` Blade directive (plain MYR) and `@price($sen)` (display-currency conversion).

## Definition of done (per task)

Migrations run clean on `migrate:fresh --seed` · Pest green · Pint clean · UI matches design tokens · mobile-checked at 390px · focus states visible.

## Doc map

| Doc | Use when |
|---|---|
| `marketplace-docs/docs/00-overview.md` | Start here — vision, locked decisions, build order |
| `marketplace-docs/docs/01-requirements.md` | What any feature must do |
| `marketplace-docs/docs/02-bagisto-feature-analysis.md` | Why features exist/don't; schema deltas |
| `marketplace-docs/docs/03-design-system.md` | Every UI decision — tokens, components, motion, microcopy |
| `marketplace-docs/docs/04-foundation-plan.md` | Migrations, models, seeders, enums (M1 — done) |
| `marketplace-docs/docs/05-storefront-plan.md` | Buyer-facing pages (M2 — done) |
| `marketplace-docs/docs/06-checkout-payments-plan.md` | Checkout, COD, iPay88, order lifecycle (M4–M6 — done) |
| `marketplace-docs/docs/07-seller-panel-plan.md` | Seller centre (M3, M6 — done) |
| `marketplace-docs/docs/08-admin-panel-plan.md` | Admin panel (M7 — done) |
| `marketplace-docs/docs/09-marketplace-depth-plan.md` | Reviews, vouchers, returns, seller finance (M8 — done) |
| `marketplace-docs/docs/10-deployment-ops.md` | Infra, scheduler, env, go-live checklists (prod cutover pending) |

## Build status

M1–M8 complete (June 2026). **Design: "Souk" (Emerald & Brass) redesign
applied across storefront, seller and admin (June 2026)** — warm ivory canvas,
Fraunces + Plus Jakarta Sans, brass = premium/ornament, soft elevation +
Islamic geometric ornament; see `marketplace-docs/docs/03-design-system.md`.
Local adaptations vs the docs: MariaDB local DB (`hbiz2_qa`, was SQLite until
2026-08-06), database queue drained by the scheduler (no Horizon on shared
hosting), Scout `collection` driver, Turnstile dormant
without keys, iPay88 verified against simulated callbacks — see plan notes.
Remaining for production: real iPay88 sandbox/production cutover (docs/10
checklist), Meilisearch + Redis + MySQL infra, ZH locale, Phase-4 features.
