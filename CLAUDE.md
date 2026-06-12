# CLAUDE.md — HalalBizs Multi-Vendor Marketplace

Shopee-style multi-vendor marketplace for the Malaysian market. One Laravel app, three contexts:
storefront `/` (guest + buyer), seller centre `/seller`, admin `/admin`.
Full specs live in `marketplace-docs/docs/` — **read `marketplace-docs/docs/00-overview.md` before your first task.**

## Stack

- Laravel 13.x · PHP 8.4 · SQLite locally (schema MySQL-compatible) · sync queue locally
- Livewire 4 + Alpine.js + Blade · Tailwind CSS v4 (tokens in `resources/css/app.css`) · Vite
- Scout (`collection` driver locally; Meilisearch in prod) · spatie: permission, medialibrary, translatable (v5 namespaces: `Models\Concerns\LogsActivity`, `Support\LogOptions`), sluggable, activitylog, settings
- brick/money · Pest · Pint · Larastan

## Commands

```bash
php artisan migrate:fresh --seed   # full demo dataset (local only)
php artisan test                   # Pest — must pass before any commit
vendor/bin/pint --dirty            # run before any commit
npm run build                      # Vite/Tailwind
npx playwright test                # browser journeys against http://halalbizs2.0.test
```

Site served by Herd at **http://halalbizs2.0.test**. Demo logins (local seed): `admin@halalbizs.test`,
`seller@halalbizs.test`, `buyer@halalbizs.test` — all password `password`.

## Architecture conventions

- Route files: `routes/web.php` (storefront), `routes/seller.php`, `routes/admin.php`. RESTful resource naming only.
- Livewire namespaces mirror contexts: `App\Livewire\Storefront\*`, `App\Livewire\Seller\*`, `App\Livewire\Admin\*`. Shared UI in Blade components `resources/views/components`.
- Business logic lives in `App\Services\*` (CheckoutService, SubOrderStatusService, CommissionResolver, Ipay88Service, CartService, CurrencyConverter, VoucherService, LedgerService). Livewire components stay thin.
- Enums in `App\Enums` — string-backed, with `label()`. No DB enum columns.
- Middleware: `SetLocale`, `SetDisplayCurrency`, `EnsureSeller` (role + approved store), `EnsureAdmin`.
- Money formatting: `@money($sen)` Blade directive (plain MYR) and `@price($sen)` (display-currency conversion).

## Hard rules (violations = rejected PR)

1. **Money is integers in sen.** Columns end `_sen`. Arithmetic via brick/money or plain int. Floats are banned for money — in PHP, JS, and Blade.
2. **Sub-order status never changes by assignment.** Only `SubOrderStatusService::transition()` — it validates the transition, writes `order_status_histories`, fires events.
3. **Stock and voucher mutations are atomic.** `lockForUpdate()` on variants and vouchers inside the checkout transaction. Validate + consume in the same lock.
4. **iPay88 fulfilment trusts only the BackendURL callback + a successful requery.** The browser ResponseURL is UX-only. Callbacks are idempotent.
5. **Snapshots are sacred.** `order_items` carry name/variant/price at purchase; `orders` carry the address json; `sub_orders` carry commission_rate. Never read live product data for historical orders.
6. **Every product has ≥1 variant.** No-variation products get a default variant. Cart/checkout/order code only touches variants.
7. **Translatable writes include `en` at minimum** (fallback locale). UI strings go in `lang/` files, never hardcoded.
8. **Emerald = money or action only** in the UI; ink frame surfaces per `marketplace-docs/docs/03-design-system.md` §5. No shadows outside overlays, no gradients.
9. **Tests accompany features.** Checkout, vouchers, ledger, status transitions, and iPay88 callback paths require Pest coverage including the race-condition cases.
10. **No new packages** without noting why in the PR description.

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
| `marketplace-docs/docs/05-storefront-plan.md` | Buyer-facing pages (M2) |
| `marketplace-docs/docs/06-checkout-payments-plan.md` | Checkout, COD, iPay88, order lifecycle (M4–M6) |
| `marketplace-docs/docs/07-seller-panel-plan.md` | Seller centre (M3, M6) |
| `marketplace-docs/docs/08-admin-panel-plan.md` | Admin panel (M7) |
| `marketplace-docs/docs/09-marketplace-depth-plan.md` | Reviews, vouchers, returns, seller finance (M8) |
| `marketplace-docs/docs/10-deployment-ops.md` | Infra, scheduler, env, go-live checklists |
