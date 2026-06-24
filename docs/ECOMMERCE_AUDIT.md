# ECOMMERCE_AUDIT — HalalBizs Marketplace (current state)

> Evidence-based inventory of what exists today. Produced from direct reads of the load-bearing code plus a 14-agent parallel audit, with every headline claim re-verified against the source. Status legend: ✅ present & working · 🟡 partial/stubbed · ❌ missing · 🐛 broken.
>
> **Headline:** this is a **mature, well-engineered** Shopee-style multi-vendor marketplace, not an incomplete skeleton. M1–M8 are genuinely shipped. The transactional core is high-craft and money-correct. Real gaps cluster at tax/compliance, logistics, refund completeness, and the competitive + AI-native frontier — tracked in `GAP_ANALYSIS.md`.
>
> **Update (this engagement): M0 + M1 + M2 are now built & tested.** Worldwide tax engine (MY SST), LHDN MyInvois e-invoicing, driver-based courier shipping (EasyParcel) + tracking, line-item refunds + payment channel, stock-movement trail + packing slip + low-stock (**M0**); full voucher stacking + free-shipping, flash sales, faceted attribute search, escrow visibility + abandoned-cart + seller health, analytics exports + take-rate, bulk import + AI listing copy, public read API + signed webhooks, review depth (verified badge / helpfulness / FBT), payment-gateway driver abstraction + Stripe + multi-currency/geo groundwork (**M1**); Loyalty Coins + check-in + spin-to-win, bilingual AI concierge, semantic + visual search, live-commerce shell, affiliate/creator program, group-buy / share-to-unlock, halal metafields, subscribe-and-save (**M2** — see `ROADMAP.md` §M2 for the per-feature file map). The three checkout-touching M2 features (Coins, Group-buy, Subscribe) ship behind a documented canonical lock order (`variants → flash → group-buy → vouchers → wallet`) with a full checkout re-run gating each; every external integration (Claude, embeddings, MyInvois, EasyParcel, Stripe) is config-gated with a deterministic local fallback. Verified: **688 Pest tests green (run twice), `migrate:fresh --seed` clean, Pint clean repo-wide, Vite build clean, Playwright journeys green.**

## Stack (confirmed)

Laravel 13.8 · PHP 8.4 · Livewire 4.3 + Alpine + Blade · Tailwind v4 (Vite 8) · SQLite locally (MySQL-compatible schema), sync queue · Scout 11 (`collection` driver locally, Meilisearch target) · brick/money · spatie (permission, medialibrary, translatable, sluggable, activitylog, settings) · barryvdh/laravel-dompdf · laravel/socialite · Pest 4 · Pint · Larastan 3. **No float in the money path.**

Scale: **39 migrations · ~45 models · 16 services · 21 enums · 81 Livewire components · 65 test files (~838 cases).** Three route contexts: `routes/web.php` (storefront), `routes/seller.php`, `routes/admin.php`.

---

## Domain model (entity map)

**Identity & access:** `User` (+ Google OAuth, phone OTP via `OtpCode`, 2FA, `KnownDevice`), spatie roles/permissions, `Address` (book + order snapshot).

**Catalog:** `Category` (self-referencing tree, per-node `commission_rate`), `Brand`, `Attribute` + `AttributeValue` (translatable, `is_filterable`, `category_attribute` pivot), `Product` (translatable, sluggable, medialibrary, `weight_grams`/`length_mm`/`width_mm`/`height_mm`, `tax_class` **absent**) → `ProductOption` + `ProductOptionValue` → **`ProductVariant`** (every product has ≥1; stock, `price_sen`). `ProductView` (server-side view log), `SearchLog`, `Wishlist`, `Review` (+ seller service ratings).

**Store:** `Store` (one per seller, `sst_registered`/`sst_number` captured, shipping settings, `holiday_mode`, `commission_rate` override), `StoreDocument` (KYC), `ProductBoost` (paid placement), `StoreLedgerEntry`, `Payout`.

**Commerce:** `Cart` (`user_id` unique → guests use session) + `CartItem`; `Order` (address json snapshot, totals in sen, `display_currency`/`display_rate`, `expires_at`) → **`SubOrder`** per store (`commission_rate` snapshot, status, shipping/tracking, `auto_complete_at`) → `OrderItem` (name/variant/price snapshot). `Payment` (gateway, ref, amount_sen), `OrderStatusHistory`, `ReturnRequest` (+ `ReturnReason`, `CancellationReason`). `Voucher` + `VoucherUsage`. `Currency` + `ExchangeRate`.

**Content/support:** `Banner`, `HomeSection`, `Page`, `ThemeAsset`, `HelpArticle`, `SupportTicket` + `TicketReply`, `Conversation` + `Message` (buyer↔seller chat), `NewsletterSubscriber`, `UrlRedirect`.

**Relationship spine:** `User 1─* Order 1─* SubOrder 1─* OrderItem *─1 ProductVariant *─1 Product *─1 Store`. Commission resolves Store-override → Category-chain → global. Money flows `Order.grand_total_sen = Σ SubOrder.total_sen` with platform discount at order level, shop discount at sub-order level.

---

## Routes & flows that work

**Storefront (`web.php`):** home, category/listing/search, PDP (variant picker + JSON-LD), store page, store **subdomains**, cart, static/CMS pages, newsletter, locale/currency preferences. Auth: register, login, forgot/reset, email verification, Google OAuth, phone-OTP, 2FA challenge. Checkout (auth-gated) → success; iPay88 pay/processing/status + ResponseURL/BackendURL callbacks. Buyer account: dashboard, profile (PDPA export/delete), messages, addresses, wishlist, notifications, orders + order detail + invoice (PDF). Help centre + support tickets. Seller application + status.

**Seller centre (`seller.php`):** dashboard, product index + create/edit (variant matrix), order index + detail + packing slip (route exists), vouchers, earnings, reviews, messages, notifications, boosts, settings.

**Admin (`admin.php`):** dashboard; seller applications/stores/detail; buyers; catalog categories/attributes/brands/moderation/reviews; orders + returns + detail; payments; finance commission/payouts/boosts; content banners/home-sections/pages/theme; vouchers; support articles/tickets; localization; system settings/staff/audit-log/search-insights.

**Scheduler (`console.php`):** `orders:expire-unpaid` (every minute), `orders:auto-complete` (hourly), `sitemap:generate` (daily 03:00), `returns:auto-escalate` (hourly), `boosts:expire` (hourly). All real.

---

## Feature inventory by domain

### Catalog & search
✅ Variants/options/option-values (matrix builder, default-variant pattern) · ✅ category tree + descendant filtering · ✅ brands · ✅ attributes + values (`is_filterable`, `category_attribute`) · ✅ media conversions (thumb/card) · ✅ Scout `Searchable` (name/desc/category/store/min_price/sold_count) · ✅ search logs + trending · ✅ search overlay (grouped, debounced) · ✅ PDP `Product` JSON-LD · ✅ related products + recommendations · ✅ XML sitemap (`GenerateSitemap`, scheduled).
🟡 Search typo-tolerance/ranking (Meilisearch wired for prod, untuned; collection driver is substring locally) · 🟡 autocomplete (grouped results, no "did you mean").
❌ **Faceted attribute filtering in storefront** (pivot/`is_filterable` modelled but never rendered/queried — only 5 hardcoded filters: category, price, rating, seller-state, COD) · ❌ synonyms · ❌ zero-result admin report · ❌ BreadcrumbList/Store JSON-LD · ❌ product types beyond variant (intentional).

### Cart, checkout & tax
✅ Session cart (guest) + DB cart (buyer); selected-line model · ✅ `CheckoutService` — integer-sen, atomic variant + voucher `lockForUpdate` in one transaction, per-store splitting, commission snapshot, item snapshots, stock decrement, COD cap, voucher stacking (1 platform + 1 shop) with exact largest-remainder proration · ✅ free-shipping voucher waiver.
✅ **Tax/SST (M0.1)** — jurisdiction-aware `TaxService` (MY SST first ruleset, worldwide-ready), `TaxClass` enum, `tax_sen`/`tax_total_sen`/`tax_rate_bp` snapshotted on orders/sub_orders/order_items, wired into checkout grand-total + invoice + seller form + ledger, gated by `stores.sst_registered` · ❌ abandoned-cart capture/recovery · 🟡 cart price/stock-drift revalidation (validated at place-time, not pre-checkout surface).

### Payments & order lifecycle
✅ COD + iPay88 (entry, ResponseURL UX, **BackendURL callback + requery is the source of truth**, idempotent, 60-min unpaid expiry job) · ✅ full `SubOrderStatus` machine (pending_payment→confirmed→processing→shipped→delivered→completed, + cancelled/return_requested/returned/refunded) via `SubOrderStatusService::transition()` only, with history + events · ✅ **delivery auto-complete window wired** (`auto_complete_at` set on Delivered + hourly job + buyer `confirmReceived()`) · ✅ buyer/seller invoices + packing-slip route · ✅ returns paperwork (request → seller response → auto-escalate → admin resolve).
✅ **Refunds (M0.4)** — `RefundService` does proportional (line-item) reversal + payment refund tracking; `return_request_items`; best-effort gateway refund seam (manual portal fallback); fixed the registered-seller tax-reversal · ✅ **payment channel (M0.4)** — `payments.channel`/`bank_code` captured from the iPay88 callback · ✅ **e-invoicing (M0.2)** — pluggable `EInvoiceProvider` (MyInvois driver + Null default), `einvoice_documents` per seller, individual-on-paid (`OrderPaid` event/listener) vs monthly B2C consolidator (`einvoice:consolidate`), reads snapshots only.

### Inventory, shipping & fulfilment
✅ Per-variant stock, atomic decrement under lock; restock on cancel · ✅ seller fulfilment actions; tracking fields (free-text).
✅ **Driver-based shipping (M0.3)** — `ShippingCalculator` dispatches Flat/Matrix/**EasyParcel** drivers; `EasyParcelService` quotes live courier rates by postcode+weight, books shipments, returns AWB/label (config-gated, flat-fee fallback); tracking webhook → `OrderService::markDelivered`.
✅ **stock-movement trail (M0.5)** — `StockService` + `stock_movements` on every checkout decrement/restock · ✅ **low-stock thresholds (M0.5)** — per-variant `low_stock_threshold` + `lowStock` scope, surfaced on the seller dashboard · ✅ **packing slip (M0.5)** — prices-free picking PDF · ❌ multi-warehouse · 🟡 direct courier drivers (J&T/Ninja Van) behind the same interface — later.

### Customers & identity
✅ Email/Google/phone-OTP auth · ✅ 2FA + device guard · ✅ address book · ✅ wishlist · ✅ buyer↔seller chat (`ChatService`) · ✅ notifications · ✅ PDPA data-export/account-deletion (`Profile.php`).
❌ Saved payment methods · ❌ customer segmentation/tags · ❌ B2B/company accounts + quotes.

### Promotions & loyalty
✅ **Voucher engine** (`VoucherService`): types fixed/percent/free-shipping; scopes platform/shop; min-spend, quota, per-user limit, window; atomic consumption under lock; exact proration · ✅ product boosts (paid placement).
❌ Loyalty coins/points · ❌ tiers · ❌ referrals · ❌ store credit/gift cards · ❌ **flash sales** · ❌ group-buy · ❌ daily check-in/gamification · ❌ tiered/BOGO cart rules.

### Admin, finance & analytics
✅ Admin governance across sellers/buyers/catalog/orders/content/support/system · ✅ `LedgerService` (signed integer entries, sale/commission/COD-offset, available balance) + `Payout` flow · ✅ `CommissionResolver` (override→category→global) · ✅ moderation queues · ✅ CMS (banners/home-sections/pages/theme) · ✅ settings (spatie) · ✅ audit log · ✅ search insights shell.
🟡 Dashboards are read-only snapshots.
❌ **Escrow/held-funds release** tied to delivery (ledger has pending/available concept but no delivery-gated release) · ❌ cohort/LTV/funnel/retention · ❌ GMV/take-rate reporting depth · ❌ exports beyond bank CSV · ❌ seller account-health scorecards.

### Seller centre
✅ Onboarding/application + KYC document upload + admin approval · ✅ product CRUD with 2-group variant matrix · ✅ order queue + fulfilment · ✅ shipping settings · ✅ earnings + payout request · ✅ seller vouchers · ✅ boosts · ✅ reviews + reply · ✅ messages.
❌ **Bulk product import (CSV)** · ❌ AI listing copy · ❌ seller analytics depth · ❌ multi-user staff per store · ❌ deeper store customization.

### Reviews, recommendations & UGC
✅ Product reviews (rating + text) · ✅ seller/service ratings · ✅ `RecommendationService` (content-based affinity: purchase/view/wishlist → category+store weights, popularity cold-start, cached) · ✅ `ProductView` recording · ✅ related products.
🟡 No verified-purchase badge despite the order-item link; review photos lack conversions.
❌ Review helpfulness voting · ❌ product Q&A · ❌ **frequently-bought-together** (co-purchase) · ❌ trending-by-velocity.

### Cross-cutting quality
✅ **Money:** integer sen throughout; brick/money; `@money`/`@price`; `RinggitInput`; no float in monetary values · ✅ **Tests:** strong on checkout race / voucher locking / ipay88 callback idempotency / ledger; thinner on the gap areas · ✅ **i18n:** EN + MS via translatable + lang files (ZH deferred) · ✅ **SEO:** sitemap + `Product` JSON-LD (BreadcrumbList/Store absent) · ✅ **Security:** `SecurityHeadersTest`, middleware auth, Turnstile (dormant w/o keys) · ✅ scheduler with 5 real jobs.
🟡 Performance: lazy components used; verify eager-loading on listing/home/dashboard hot paths at scale.

---

## Payment & checkout reality check

- **Gateways wired:** COD (with RM500 cap setting) + iPay88 (FPX, cards, TNG/GrabPay/Boost/ShopeePay via the aggregator). No international gateway, no saved cards/3DS tokenization, no BNPL.
- **Checkout is end-to-end functional** and correct: atomic, snapshotted, multi-store split, voucher-stacked, COD + online both reach a placed order with a payment row.
- **iPay88 is verified against simulated callbacks** (sandbox/production cutover pending — docs/10). Fulfilment trusts only BackendURL + requery; ResponseURL is UX-only; callbacks idempotent.
- **Missing for transactability:** tax line, e-invoice, real shipping rates, line-item refund + automated refund, payment channel persistence.

## Tech debt & smells (specific)

- `ShippingCalculator` is a stub that discards captured weight/dimension data.
- `return_requests.sub_order_id` is `unique` — architecturally blocks partial/line-item returns until a `return_request_items` table exists.
- No `stock_movements` — checkout decrement and restock are unlogged (no oversell forensics).
- `payments` lacks a `channel`/`bank_code` field — reconciliation can't distinguish FPX vs wallet vs card.
- `commission_rate` is `decimal(5,2)` (a *rate*, not money) — converted to exact integer basis points in `LedgerService.php:44`; **not a money-rule violation**, but a consistency cleanup candidate.
- Attribute faceting is modelled but unused — dead `is_filterable` surface until storefront wiring lands.

## Corrections to automated analysis (verified false claims — not gaps)

1. **Auto-complete is NOT a dead job** — `SubOrderStatusService.php:82-85` populates `auto_complete_at` on the Delivered transition; `orders:auto-complete` runs hourly; buyers can `confirmReceived()` early (`OrderDetail.php:84`).
2. **`commission_rate` decimal is NOT a Hard-Rule-1 violation** — it's a rate, exactly converted to basis points; `commission_sen` is integer.
3. **XML sitemap EXISTS** — `app/Console/Commands/GenerateSitemap.php`, scheduled daily.

---

*Updated as roadmap items flip ❌/🟡 → ✅ during M0/M1 implementation.*
