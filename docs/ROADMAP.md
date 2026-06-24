# ROADMAP — HalalBizs Marketplace

> Prioritised, sequenced build plan derived from `GAP_ANALYSIS.md`. Three milestones: **M0** make it real & compliant, **M1** make it competitive, **M2** make it remarkable. Each item lists *what*, *why*, *effort*, *dependencies*, and the concrete files/migrations/components it touches. Scored by impact × effort; ordered within each milestone by leverage and dependency.
>
> **Engagement scope (owner-approved):** deliver the four docs, then build **M0 then M1** in small reviewable chunks, pausing with a summary after each. M2 is specced here but deferred to a later session unless re-scoped.
>
> **Worldwide framing:** tax, e-invoicing and payments are built as pluggable, multi-jurisdiction concerns with Malaysia as the first implementation. MYR settlement + COD/iPay88 remain the launch default.
>
> **Authorized new dependencies (Hard Rule 10):** EasyParcel · Claude API · MyInvois signature cert/SDK · vector-search package. Where live credentials are unavailable in this environment, integrations are built against sandbox config + recorded fixtures and gated on owner-supplied keys before any production cutover.

## Conventions every item obeys

Money is integer **sen** (`_sen` columns, brick/money). Sub-order status changes **only** via `SubOrderStatusService::transition()`. Stock + voucher mutations are **atomic** under `lockForUpdate`. Snapshots are sacred. Translatable writes always include `en`. UI uses the Souk tokens (emerald = money/action, brass = ornament). New features ship with Pest tests including race/edge cases. Each item ends green on `migrate:fresh --seed` + `php artisan test` + `pint --dirty`.

---

## M0 — Make it real & compliant (Tier-1)

### M0.1 — Worldwide tax engine (MY SST first ruleset) · impact 5 · L · deps: none
**What.** Add per-line tax to the money path. Migration adds `tax_sen` to `orders` + `sub_orders` and `tax_per_item_sen` + `tax_rate_bp` to `order_items`; `products.tax_class`. New `App\Enums\TaxClass` (exempt / sales_5 / sales_10 / service_6 / service_8) with `label()` + `rateBasisPoints()`. New `App\Services\TaxService` with a **jurisdiction resolver** (destination country/region × seller registration × product tax class), Malaysia SST as the first concrete ruleset. Wire the tax term into `CheckoutService::place()` per-store + grand totals and snapshot it; render the SST line in `InvoiceService`; record tax-collected as a separate `LedgerService` entry.
**Why.** Today `grand_total` has no tax term and `stores.sst_registered` is dead. No store can transact compliantly. This is the first chunk — it exercises the money path everything depends on and needs no external credentials.
**Touches.** new migration; `App\Services\TaxService`; `App\Enums\TaxClass`; `products.tax_class`; `CheckoutService.php:171`; `InvoiceService`; `LedgerService`; `CheckoutServiceTest` + new `TaxServiceTest`.

### M0.2 — E-invoicing provider interface + MyInvois driver · impact 5 · XL · deps: M0.1
**What.** `App\Services\EInvoice\EInvoiceProvider` contract + `MyInvoisProvider` (Laravel Http + OAuth2 token cache + UBL builder + digital signature, against the LHDN **sandbox**). `einvoice_documents` table (order_id, provider, uin, long_id, status, validation_url, submission_uid). Fires on the verified-paid event; monthly B2C consolidator command for un-requested orders; reads only sacred order snapshots. Production cutover gated on owner creds.
**Why.** LHDN MyInvois is a hard compliance obligation; the platform files as Intermediary for sellers. Built behind a provider interface so other countries' regimes plug in later (worldwide).
**Touches.** `App\Services\EInvoice\*`; `einvoice_documents` migration; `users.tin` / `stores.einvoice_required`; scheduled consolidator; listener on payment-verified; fixtures-based tests.

### M0.3 — Driver-based ShippingService + EasyParcel · impact 5 · L · deps: none
**What.** Refactor `ShippingCalculator` into a driver interface (`FlatDriver` / `MatrixDriver` / `EasyParcelDriver`). `EasyParcelService` HTTP client quotes live rates by postcode + weight (`products.weight_grams`) + dimensions (`*_mm`), books shipment, returns AWB label PDF. Store AWB + label on the sub-order. Tracking webhook → `SubOrderStatusService::transition` (Shipped/Delivered).
**Why.** Shipping is a 27-line flat/matrix stub that ignores the weight/dimension data sellers already enter. Real launch needs real rates, labels and automated tracking.
**Touches.** `App\Services\Shipping\*` (refactor `ShippingCalculator`); `EasyParcelService`; `sub_orders` AWB/label columns; `routes/web.php` webhook; `ShippingCalculatorTest` + driver tests with fixtures.

### M0.4 — Line-item refunds + automated gateway refund + payment channel · impact 4 · L · deps: M0.1
**What.** `return_request_items` table (order_item_id, qty, refund_sen) + drop `unique(sub_order_id)` so returns scope to items/quantities with a computed refund. `Ipay88Service::refund()` (or store-credit fallback if the merchant contract lacks a refund API); `LedgerService` per-line reversal + atomic restock under lock. `payments.channel` + `bank_code` for FPX/wallet/card reconciliation.
**Why.** RMA is whole-sub-order only, refunds are manual portal actions, and the payment channel is unrecorded. Completes the money lifecycle so returns + reconciliation work at scale.
**Touches.** `return_request_items` migration; `ReturnRequest` model; `Ipay88Service`; `LedgerService`; `payments` migration; `Admin/Orders/Returns`; `ReturnsTest`.

### M0.5 — Stock-movement trail + packing slip + low-stock alerts · impact 3 · M · deps: none
**What.** `stock_movements` table (variant_id, type, qty_delta, reference, balance_after) + observer on the checkout decrement and every restock. `packing-slip.blade.php` PDF per sub-order for sellers. `product_variants.low_stock_threshold` + seller dashboard low-stock widget + scheduled alert.
**Why.** Forensic stock trail, picking documents and reorder alerts are table-stakes ops the docs promised but are absent. Low risk; rounds out M0 operability.
**Touches.** `stock_movements` migration + observer; `packing-slip.blade.php` + seller action; `product_variants.low_stock_threshold`; seller dashboard widget; scheduled alert; tests.

---

## M1 — Make it competitive (Tier-2)

### M1.1 — Full voucher stacking (+ free-shipping tier) · impact 5 · M · deps: M0.3
One platform + one free-shipping + one shop voucher, fixed precedence, typed `kind` + `funded_by`; `CheckoutService` writes a `shipping_subsidy_sen` line; `LedgerService` attributes subsidy cost to the funder. *Touches:* `vouchers.kind`/`funded_by`; `VoucherService`; `CheckoutService`; `LedgerService`; race tests. *(Free-shipping subsidy needs real shipping cost → after M0.3.)*

### M1.2 — Flash sales · impact 5 · M · deps: none
`flash_sales` + `flash_sale_items` (promo_price_sen, allocated_qty, per_buyer_limit, sold_qty); effective-price resolver at read time; `CheckoutService` locks the flash-sale-item row alongside the variant; `FlashSaleTimer` Livewire + Alpine countdown + "% claimed" bar; scheduled activation command. *Reuses the existing atomic stock pattern.*

### M1.3 — Faceted attribute search + Meilisearch tuning · impact 4 · L · deps: none
`product_attribute` pivot (per-product values); `config/scout.php` filterableAttributes + BM↔EN synonyms + ranking rules; `Listing.php` facet rendering (only 5 hardcoded filters today); admin synonym CRUD + zero-result report in `System/SearchInsights`.

### M1.4 — Escrow release + abandoned-cart + seller health · impact 4 · M · deps: M0 lifecycle
**Escrow (owner-selected):** `StoreLedgerEntry` pending→available release tied to `auto_complete_at`; `LedgerService::release()`; scheduled command; seller finance UI shows pending vs available. **Abandoned-cart:** `carts.last_activity` + scheduled scan + notifications + `back_in_stock_subscriptions`. **Seller health:** `seller_health` rollup from `order_status_histories` + scorecard + admin policy gate.

### M1.5 — Seller/admin analytics + exports · impact 3 · L · deps: none
Daily rollup tables via the scheduler; `Seller/Dashboard` + `Admin/Dashboard` metrics (take-rate, GMV, period-over-period, conversion from `ProductView`); cohort/LTV/funnel; CSV/PDF export actions.

### M1.6 — Bulk product import + AI listing copy · impact 3 · M · deps: Claude API
Seller bulk-import Livewire + validated CSV importer + template download; "Generate with AI" action calling Claude (haiku) writing translatable EN/BM fields (`en` always) into a draft state (no auto-publish).

### M1.7 — Public read API + outbound webhooks · impact 3 · L · deps: none
`routes/api.php` + API Resources reusing `App\Services` (no parallel write path); Sanctum tokens; `webhook_subscriptions` table; listeners on the already-firing `SubOrderStatusService` events POST signed payloads (placed/paid/shipped/refunded) with queue + retry. Substrate for the future mobile app + AI feeds.

### M1.8 — Review depth (badges/media/helpfulness/FBT) · impact 3 · M · deps: none
Verified-purchase badge via `Review.orderItem()`; `Review::registerMediaConversions()`; `reviews.helpful_count` + `ReviewHelpful` pivot; `sold_count_7d` trending signal; `RecommendationService` frequently-bought-together co-purchase query on `order_items`.

### M1.9 — Payment-gateway driver abstraction + multi-currency/geo groundwork · impact 4 · M · deps: M0.4
Refactor iPay88 into a `PaymentGateway` driver behind an interface; add a **Stripe** driver for international cards/wallets/3DS (flagged for explicit go-live approval per Hard Rule 10); data-driven method/channel picker. Lay multi-currency settlement + geo-pricing groundwork required by the worldwide scope.

---

## M2 — Make it remarkable (Tier-3) — ✅ BUILT & TESTED (June 2026)

Delivered after a parallel design pass that sequenced the work to protect the
money path: the three **checkout-touching** features (Coins, Group-buy,
Subscribe) were built strictly sequentially, each followed by a full checkout
re-run, with a documented canonical lock order
(`variants → flash → group-buy → vouchers → wallet`). Every external integration
is config-gated with a deterministic local fallback (test-safe with no keys).

1. **Loyalty "Coins" + daily check-in + spin-to-win** ✅ — `CoinService`/`SpinService`, `coin_wallets`/`coin_transactions` FIFO-expiring ledger (`config/coins.php`), wallet-locked redemption in `CheckoutService::place($coinsToRedeem)` (default 0), earn-on-completion listener, refund on unpaid cancel, `/account/coins` hub, `coins:expire`.
2. **Bilingual EN/BM AI shopping concierge** ✅ — `ConciergeService` Claude tool-use over Scout (`ShopAssistant` global overlay) on the shared config-gated `App\Services\Ai\ClaudeClient`; deterministic Scout-search fallback offline.
3. **Semantic/hybrid + visual search** ✅ — `EmbeddingProvider` (local hash + remote driver) + `ImageEmbedder` (colour histogram) → `product_embeddings`, `VectorSearchService`, `EmbedProductJob` + observers + `search:embed`; Listing **Smart** mode + `/search/visual` (`config/search.php`).
4. **Live-commerce shoppable shell** ✅ — `LiveSession`/`LiveSessionService` (`live_sessions` + rail pivot), seller studio + `/live` hub + `/live/{slug}` room (video embed, spotlight, add-to-cart through the unchanged checkout, pinned voucher, polled "just sold" feed).
5. **Affiliate / creator commerce** ✅ — `AffiliateService`, `/r/{code}` link + cookie attribution observer (checkout-safe), commission-on-completion listener, `/account/affiliate` dashboard (`config/affiliate.php`).
6. **Group-buy / share-to-unlock** ✅ — refund-free unlock-first model; `GroupBuyService` (team lock + atomic unlock), `CheckoutService` applies the deal price for members of an unlocked team + `order_items.group_buy_id` snapshot, PDP panel + `/group-buy/{code}` + seller deals (`config/groupbuy.php`).
7. **Metafields (halal-cert/SIRIM/ingredients/expiry)** ✅ — `product_metafields` + config registry (`config/metafields.php`), seller form section, PDP trust panel (brass badges), searchable values folded into `toSearchableArray`.
8. **Subscribe-and-save / predictive replenishment** ✅ — `SubscriptionService` + `subscriptions:process` (lock-then-advance idempotency) reusing `place(explicitLines:)` (backward-compatible optional param), PDP subscribe panel + `/account/subscriptions` (`config/subscriptions.php`).

**Verified:** 688 Pest tests green (run twice), `migrate:fresh --seed` clean, Pint clean repo-wide, Vite build clean, Playwright journeys green. M2.0 shared foundations (ClaudeClient, wallet/ledger discipline, post-commit `OrderPaid`/completion listener convention) underpin the above.

---

## Dependency graph (critical path)

```
M0.1 tax ──► M0.2 e-invoice
        └──► M0.4 refunds ──► M1.9 payment drivers
M0.3 shipping ──► M1.1 voucher stacking (free-ship subsidy)
M0 lifecycle ──► M1.4 escrow release
(independent: M0.5, M1.2 flash, M1.3 search, M1.5 analytics, M1.6 import, M1.7 API, M1.8 reviews)
```

## Definition of done (every milestone item)

`migrate:fresh --seed` clean · Pest green (incl. race/edge cases) · `pint --dirty` clean · Larastan clean · UI matches Souk tokens · mobile-checked at 390px · focus states visible · `docs/ECOMMERCE_AUDIT.md` updated as the item flips ❌/🟡 → ✅.
