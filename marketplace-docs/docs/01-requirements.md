# Multi-Vendor Ecommerce Marketplace — Requirements & Tech Stack

**Reference model:** Shopee (Malaysia)
**Foundation:** Custom Laravel build (confirmed — no Bagisto/Dokan base)
**Version:** 1.0 — June 2026

---

## 1. Overview

A multi-vendor marketplace where independent sellers run storefronts under one platform. The platform earns commission on completed orders. One Laravel application, one database, three contexts separated by route groups + middleware:

| Context | Route prefix | Audience |
|---|---|---|
| Storefront | `/` | Guest + Buyer |
| Seller Centre | `/seller` | Approved sellers |
| Admin Panel | `/admin` | Platform operators |

Target market: Malaysia first (BM/EN, MYR settlement, iPay88 + COD), built to display additional languages and currencies from day one.

---

## 2. User Roles & Capabilities

### 2.1 Guest (no account)
- Browse homepage, categories, product listings, product detail pages, seller store pages
- Keyword search with filters and sorting
- Add to cart (session cart, merged into DB cart on login)
- Switch display language and display currency (stored in session)
- Register / login
- **Cannot:** checkout, wishlist, review, chat

### 2.2 Buyer (registered)
Everything Guest can do, plus:
- Profile management, password, email verification
- Multiple shipping addresses with a default
- Wishlist
- Full checkout: address → shipping option per seller → voucher → payment method (COD / iPay88) → place order
- Order tabs Shopee-style: **To Pay / To Ship / To Receive / Completed / Cancelled / Return-Refund**
- Cancel order before seller ships; request return/refund within window (e.g. 7 days after delivery)
- Product reviews: 1–5 stars, text, up to 5 photos — only for purchased items, one per item
- In-app + email notifications
- Phase 2+: buyer↔seller chat

### 2.3 Seller
- Seller application: shop name, business info, documents (SSM, bank account) → **pending admin approval** before selling
- Shop profile: logo, banner, description, slug, holiday/vacation mode
- **Product management:**
  - CRUD with multi-image upload and drag ordering
  - Up to 2 variation groups (e.g. Colour × Size) → variant matrix with per-variant price, stock, SKU, image
  - Sale price with start/end schedule
  - Stock tracking + low-stock alerts
  - Status: draft / live / delisted / banned (admin)
  - Per-language name & description (fallback to default language)
- **Order management:** new order queue → confirm/pack → enter tracking no. → mark shipped; handle cancellations and return requests
- Shop vouchers: fixed or %, min spend, quota, per-user limit, validity window
- **Earnings:** ledger of order income minus commission, pending vs available balance, payout requests, payout history
- Basic analytics: sales, orders, top products
- View and reply to reviews (one reply per review)

### 2.4 Admin
- Dashboard: GMV, commission revenue, orders today, new users, pending seller applications
- **Seller management:** approve/reject applications with reason, suspend shops, per-seller commission override
- **Buyer management:** list, suspend/ban
- **Catalog governance:** category tree (3 levels), attributes, brands, product moderation (flag/delist)
- **Order oversight:** view all orders, intervene in disputes, force cancel/refund
- Return/refund dispute resolution (buyer escalation path)
- **Finance:** iPay88 payment reconciliation, commission settings (global %, per-category, per-seller), approve + mark payouts paid, export payout CSV
- **Promotions:** platform vouchers, homepage banners, featured sections
- **CMS:** static pages (About, T&C, Privacy, Refund Policy, FAQ)
- **Localization management:** enable/disable languages, manage translations; manage currencies and exchange rates (manual entry + optional API sync)
- **System:** admin staff roles/permissions, audit log, site settings, payment config

---

## 3. Functional Requirements by Module

### 3.1 Authentication & Accounts
- Single `users` table; roles via spatie/laravel-permission (`buyer`, `seller`, `admin`, plus granular admin permissions)
- Seller = user + approved `stores` record (one store per user for v1)
- Email + password login, email verification, password reset, rate limiting on auth routes
- Route protection: `/seller/*` requires seller role + store status `approved`; `/admin/*` requires admin role
- Phase 2: phone OTP, Google social login

### 3.2 Catalog
- Categories: 3-level tree, image per category, translatable names, category→attribute mapping
- Products: title, rich-text description, brand, condition, weight & dimensions (for shipping calc later), images via media library
- Variants as separate rows holding price/stock/SKU; product without variations = single default variant (keeps cart/order logic uniform)
- All prices stored as **integers in sen** (confirmed decision — no floats)

### 3.3 Search & Discovery
- Laravel Scout + Meilisearch: typo-tolerant keyword search on name/description
- Filters: category, price range, rating, seller state/location; sort by relevance, latest, top sales, price
- Homepage: banner carousel, category grid, featured/new product sections
- MVP fallback if Meilisearch is deferred: MySQL FULLTEXT behind the same Scout interface

### 3.4 Cart & Checkout
- Cart grouped **by seller** (Shopee-style), per-seller subtotal and shipping
- Guest: session cart → merged on login; Buyer: DB cart
- Stock validated at add-to-cart and again at order placement
- **Order splitting (confirmed structure):** one checkout → 1 `order` (parent, payment-level) → N `sub_orders` (one per store) → `order_items`
- `order_items` snapshot product name, variant name, and unit price at purchase time (confirmed decision)
- Shipping fee v1: seller-defined flat rate or per-state matrix; courier API (EasyParcel/J&T) is Phase 3

### 3.5 Payments

**COD**
- Toggleable per seller (and optionally per product); optional max-order-amount cap for COD
- Order proceeds straight to `confirmed` with `payment_status = pending`, settled on delivery
- COD orders excluded from iPay88 reconciliation; collected amount enters the seller ledger as cash-collected

**iPay88 (Malaysia, hosted page redirect)**
- Methods through one integration: FPX online banking, credit/debit cards, e-wallets (TNG, GrabPay, Boost, ShopeePay)
- Flow: form POST to iPay88 entry URL → customer pays → browser returns via **ResponseURL** → iPay88 server posts to **BackendURL** (must reply `RECEIVEOK`)
- **Trust only the BackendURL callback + signature; never fulfil from the browser redirect alone**
- Request signature: SHA256 of `MerchantKey + MerchantCode + RefNo + Amount + Currency` (amount stripped of decimals/commas — sen storage makes this trivial); verify response signature which additionally includes `PaymentId` and `Status`
- **Requery API** as the final source of truth before marking paid; run via queued job
- Idempotent webhook handling — callbacks can arrive multiple times
- Auto-cancel unpaid iPay88 orders after 60 minutes and release reserved stock
- RefNo = parent order number; one payment record per parent order
- Refunds: manual via iPay88 merchant portal for v1; system records refund state
- Confirm exact PaymentId channel codes and current signature spec against iPay88 technical doc (v1.6.x) during merchant onboarding

### 3.6 Orders & Fulfilment
- Sub-order status flow: `pending_payment → confirmed → processing → shipped → delivered → completed`, plus `cancelled` and `return_requested → returned/refunded`
- Status history table (who/when/what) per sub-order
- Auto-complete N days after delivered if buyer takes no action (starts payout clock)
- PDF invoice per sub-order
- Email + database notification on every status change

### 3.7 Reviews & Ratings
- Gate: order item must be `completed`; one review per order item
- Aggregates cached on product (avg rating, count) and store (shop rating)
- Admin can hide abusive reviews; seller gets one reply

### 3.8 Multilanguage
- **UI strings:** Laravel lang files — `ms`, `en` at launch, `zh` ready; locale switcher persists to session + user preference; middleware sets `app()->setLocale()`
- **Content (products, categories, banners, CMS pages):** spatie/laravel-translatable JSON columns with fallback to default locale (`ms` or `en` — decide before seeding)
- Seller UI provides tabbed inputs per enabled language; untranslated fields fall back, never blank
- URL strategy v1: session/preference based (no locale prefix); revisit `/en`, `/ms` prefixes if SEO becomes a priority

### 3.9 Multicurrency
- **Base currency MYR. All storage, checkout, and iPay88 settlement in MYR.** Other currencies are display-only conversion
- `currencies` table (code, symbol, decimals, active) + `exchange_rates` table (rate vs MYR, source, fetched_at)
- Rates: admin manual entry, plus optional scheduled sync from an FX API with a configurable margin %
- Converted prices shown with "approximate" disclaimer; checkout summary always shows the MYR amount that will be charged
- True multi-currency settlement is explicitly out of scope for v1

### 3.10 Promotions
- Voucher engine shared by platform (admin) and shop (seller) vouchers: fixed/% discount, free-shipping type, min spend, discount cap, total quota, per-user limit, validity window, usage tracking
- One platform voucher + one shop voucher per seller stackable at checkout (Shopee behaviour)
- Phase 2/3: flash sales (time-boxed price + allocated stock), homepage campaign slots

### 3.11 Seller Finance (escrow-style)
- On sub-order **completed**: ledger entry = items total + shipping − commission (− payment fee share if configured)
- Balance states: `pending` (inside return window) → `available` → `paid_out`
- Payout: seller requests from available balance (or scheduled cycle); admin approves → exports CSV → marks paid with bank reference. Automated disbursement is a later phase

### 3.12 Notifications
- Channels: database (in-app bell) + queued email for all order, payout, and approval events
- Phase 3: WhatsApp/SMS for shipped/delivered

---

## 4. Non-Functional Requirements

- **Security:** CSRF everywhere, rate limiting, signed + requery-verified iPay88 callbacks, no card data ever touches the server (hosted page → SAQ-A scope), 2FA for admin accounts, spatie/activitylog audit trail on admin and finance actions
- **Performance:** Redis cache for home/category/product pages, queued media conversions, eager-loading discipline, image CDN via Cloudflare; target p95 < 500 ms on cached storefront pages
- **Scalability:** stateless app (Redis sessions, S3-compatible media) so workers/web can scale horizontally; Horizon-managed queues
- **Compliance:** PDPA Malaysia — consent at registration, privacy policy, account deletion request flow
- **SEO:** server-rendered Blade/Livewire pages, slugs, sitemap, meta/OG tags, Product structured data
- **Reliability:** nightly DB backups to object storage (spatie/laravel-backup), error tracking, uptime monitoring

---

## 5. Tech Stack

### Application
| Layer | Choice | Notes |
|---|---|---|
| Framework | Laravel 12.x+ (PHP 8.4) | Your daily stack; use latest stable at kickoff |
| Frontend | Livewire 4 + Alpine.js + Blade | Full-page Livewire components per context |
| Styling | Tailwind CSS v4 + Vite | Shopee-like orange-led design system |
| DB | MySQL 8 | InnoDB, utf8mb4 |
| Cache / Session / Queue | Redis + Laravel Horizon | |
| Search | Laravel Scout + Meilisearch | MySQL FULLTEXT driver acceptable for MVP |
| Media | spatie/laravel-medialibrary + Intervention Image | Conversions queued; S3-compatible disk |
| Storage | Cloudflare R2 / DO Spaces (S3 API) | Local disk in dev |

### Key packages (confirmed + additions)
- `spatie/laravel-permission` — roles & admin permissions
- `spatie/laravel-medialibrary` — product/store/review images
- `spatie/laravel-sluggable` — product, store, category slugs
- `spatie/laravel-translatable` — translatable model content
- `spatie/laravel-activitylog` — audit trail
- `spatie/laravel-settings` — site/payment/commission settings
- `spatie/laravel-backup` — DB + media backups
- `laravel/scout` (+ Meilisearch driver) — search
- `brick/money` — money math on sen integers
- `barryvdh/laravel-dompdf` or `spatie/laravel-pdf` — invoices
- `laravel-lang/lang` — base BM/ZH UI translations
- **iPay88: small in-house service class** (signature builder, entry form, callback verifier, requery client) — no maintained official Composer package worth depending on

### Quality & tooling
- Pest (unit/feature, full coverage on checkout, voucher, ledger, callback paths), Laravel Dusk on the checkout happy path
- Pint + Larastan, Telescope/Debugbar locally
- Claude Code with CLAUDE.md conventions (your existing route-enforcement hooks and audit prompt templates apply directly)

### Infrastructure
- VPS (start ~4 vCPU / 8 GB): Ubuntu 24.04, Nginx, PHP-FPM, MySQL, Redis on one box; split DB out when needed
- Laravel Forge or Ploi for provisioning + zero-downtime deploys; Cloudflare in front (DNS, CDN, WAF)
- Mail: SES / Mailgun / Brevo, all queued
- Monitoring: Sentry or Flare + Laravel Pulse
- Separate staging environment with iPay88 sandbox credentials

---

## 6. Core Data Model (high level)

```
users, addresses
stores (seller shops), store_documents
categories, brands, attributes, attribute_values
products, product_variants            ← prices in sen
carts, cart_items
orders            ← parent: buyer, totals, payment method, currency snapshot
sub_orders        ← per store: status flow, shipping fee, commission
order_items       ← snapshot of name/variant/unit price (sen)
order_status_histories
payments          ← iPay88 ref, signature payload, status, requery result
vouchers, voucher_usages
reviews, review_replies
store_ledger_entries, payouts
currencies, exchange_rates
notifications, banners, pages, settings, activity_log
```

---

## 7. Build Phases

**Phase 1 — Foundation + Storefront (your confirmed v1 priority)**
Auth & roles, category/product/variant models, Shopee-style storefront UI (home, category, search, product page, store page), session/DB cart, seller product CRUD, admin category + seller approval. Language switcher (BM/EN) live from the start — retrofitting translations is painful.

**Phase 2 — Transactions**
Checkout with order splitting, COD end-to-end, iPay88 sandbox → production, order lifecycle for all three roles, notifications, invoices, multicurrency display.

**Phase 3 — Marketplace depth**
Reviews, voucher engine, seller ledger + manual payouts, return/refund flow, analytics dashboards, ZH locale.

**Phase 4 — Growth**
Chat, flash sales, courier API, automated payouts, mobile API (Sanctum), recommendations.

*Project-specific extension (deferred per earlier decision): halal certification layer — per-product cert fields, certificate uploads, seller verification badges — slots into the product and store models in Phase 3+ without schema disruption since cert columns are already planned on products.*

---

## 8. Open Decisions Before Kickoff

1. Default/fallback language: BM or EN?
2. Display currencies at launch (suggest MYR, USD, SGD, IDR)?
3. COD max order cap amount?
4. Return window and auto-complete window (suggest 7 / 7 days)?
5. Commission: flat % to start, or per-category from day one?
6. One store per seller account confirmed for v1?
