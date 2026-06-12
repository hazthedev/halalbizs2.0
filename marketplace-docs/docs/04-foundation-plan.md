# Phase 1 — Migrations & Model Scaffold (Claude Code Plan)

**Project:** Multi-vendor marketplace (Shopee reference)
**Stack:** Laravel 12.x · Livewire 4 · Tailwind v4 · MySQL 8 · Redis
**Usage:** Drop this file in the repo root, open Claude Code, and work through the Task Sequence in §9. Each task references the specs below.

---

## 1. Locked Decisions (do not re-litigate)

| Decision | Value |
|---|---|
| Foundation | Custom Laravel + Livewire — no ecommerce package base |
| App shape | One app, three contexts: `/` storefront, `/seller`, `/admin` |
| Money | Integers in **sen**, column suffix `_sen`, arithmetic via `brick/money`. Never floats. |
| Order structure | `orders` (parent, payment-level) → `sub_orders` (per store) → `order_items` |
| Item snapshots | `order_items` snapshot name, variant label, unit price at purchase |
| Default / fallback locale | `en` (with `ms` enabled at launch, `zh` later) |
| Display currencies | MYR (base), USD, SGD, IDR — display conversion only, settle in MYR |
| COD cap | RM500 (`cod_max_order_sen = 50000`) — settings value, adjustable |
| Return window / auto-complete | 7 days / 7 days after delivery |
| Commission | Resolver hierarchy: **seller override → category rate → global default**, all admin-editable |
| Stores per seller | One (`stores.user_id` unique) |
| Unpaid iPay88 expiry | 60 minutes, then auto-cancel + release stock |

---

## 2. Conventions

- Migrations: one table per file, FKs always `constrained()` with explicit `cascadeOnDelete()` / `nullOnDelete()` choices as specified below.
- Status columns: `string` backed by PHP enums in `App\Enums` — no DB enum columns.
- Translatable content: `spatie/laravel-translatable` JSON columns, fallback locale `en`. Translatable columns are marked `(t)` below.
- Slugs: `spatie/laravel-sluggable` from the `en` translation.
- Images: `spatie/laravel-medialibrary` collections — no image path columns on tables.
- Public identifiers: internal `id` bigint autoincrement; human-facing numbers are generated strings (`order_no`, `sub_order_no`, `payout_no`).
- Routes: RESTful resource conventions throughout (existing CLAUDE.md hook enforces this).
- Soft deletes only where marked — don't add them everywhere.

---

## 3. Packages to Install (Task 1)

```
composer require livewire/livewire spatie/laravel-permission spatie/laravel-medialibrary \
  spatie/laravel-sluggable spatie/laravel-translatable spatie/laravel-activitylog \
  spatie/laravel-settings spatie/laravel-backup laravel/scout meilisearch/meilisearch-php \
  http-interop/http-factory-guzzle brick/money barryvdh/laravel-dompdf laravel-lang/lang

composer require --dev pestphp/pest pestphp/pest-plugin-laravel larastan/larastan laravel/pint
```

Config notes:
- `app.locale = en`, `app.fallback_locale = en`
- Scout driver: `meilisearch` (do **not** use the MySQL/database driver — translatable JSON columns don't play well with FULLTEXT)
- Queue + session + cache: `redis`
- Publish migrations for: permission, medialibrary, activitylog, settings, notifications

---

## 4. Enums (`App\Enums`)

| Enum | Cases |
|---|---|
| `StoreStatus` | pending, approved, suspended, rejected |
| `ProductStatus` | draft, live, delisted, banned |
| `ProductCondition` | new, used |
| `HalalStatus` | certified, self_declared, not_applicable *(columns exist now, UI deferred)* |
| `PaymentMethod` | cod, ipay88 |
| `PaymentStatus` (order-level) | pending, paid, failed, expired, refunded |
| `SubOrderStatus` | pending_payment, confirmed, processing, shipped, delivered, completed, cancelled, return_requested, returned, refunded |
| `GatewayPaymentStatus` (payments row) | pending, success, failed, expired |
| `VoucherScope` | platform, shop |
| `VoucherType` | fixed, percent, free_shipping |
| `DocumentStatus` | pending, verified, rejected |
| `LedgerEntryType` | sale, commission, shipping, adjustment, payout, cod_offset |
| `LedgerEntryStatus` | available, paid_out |
| `PayoutStatus` | requested, approved, paid, rejected |
| `ActorType` | buyer, seller, admin, system |

---

## 5. Migration Set

Format: `column  type  notes`. Timestamps on every table unless noted. Add the listed indexes.

### Batch 1 — Identity & catalog taxonomy

```
users
  name                string
  email               string unique
  email_verified_at   timestamp nullable
  password            string
  phone               string nullable
  preferred_locale    string(5) default 'en'
  preferred_currency  char(3) default 'MYR'
  status              string default 'active'        # active|suspended
  remember_token
  softDeletes

addresses
  user_id         FK users cascadeOnDelete, index
  label           string nullable                    # "Home", "Office"
  recipient_name  string
  phone           string
  line1           string
  line2           string nullable
  postcode        string(10)
  city            string
  state           string                             # MY state list in app
  country         char(2) default 'MY'
  is_default      boolean default false

stores
  user_id          FK users cascadeOnDelete, UNIQUE   # one store per seller
  name             string
  slug             string unique
  description      text nullable
  status           string default 'pending'           # StoreStatus
  rejection_reason string nullable
  holiday_mode     boolean default false
  commission_rate  decimal(5,2) nullable              # per-seller override; null = inherit
  state            string nullable                    # location filter
  rating_avg       decimal(3,2) default 0
  rating_count     unsignedInteger default 0
  approved_at      timestamp nullable
  softDeletes
  # media collections: logo (single), banner (single)

store_documents
  store_id   FK stores cascadeOnDelete, index
  type       string                                   # ssm|bank_statement|ic|other
  status     string default 'pending'                 # DocumentStatus
  notes      string nullable
  # media collection: file (single)

categories
  parent_id        FK categories nullOnDelete, nullable, index
  name             json (t)
  slug             string unique
  description      json (t) nullable
  commission_rate  decimal(5,2) nullable              # per-category; null = inherit global
  is_active        boolean default true
  position         unsignedInteger default 0
  # depth max 3 enforced in app/service, not DB
  # media collection: image (single)

brands
  name       string
  slug       string unique
  is_active  boolean default true

attributes
  name           json (t)
  slug           string unique
  is_filterable  boolean default true

attribute_values
  attribute_id  FK attributes cascadeOnDelete, index
  value         json (t)
  position      unsignedInteger default 0

category_attribute (pivot, no timestamps)
  category_id   FK cascadeOnDelete
  attribute_id  FK cascadeOnDelete
  unique(category_id, attribute_id)
```

### Batch 2 — Products

```
products
  store_id           FK stores cascadeOnDelete, index
  category_id        FK categories restrictOnDelete, index
  brand_id           FK brands nullOnDelete, nullable
  name               json (t)
  slug               string unique
  description        json (t)                         # longText
  condition          string default 'new'
  status             string default 'draft', index    # ProductStatus
  weight_grams       unsignedInteger nullable
  length_mm          unsignedInteger nullable
  width_mm           unsignedInteger nullable
  height_mm          unsignedInteger nullable
  cod_enabled        boolean default true
  sold_count         unsignedInteger default 0        # denormalized, "top sales" sort
  rating_avg         decimal(3,2) default 0
  rating_count       unsignedInteger default 0
  halal_status       string nullable                  # HalalStatus — UI deferred
  halal_cert_number  string nullable
  halal_cert_expiry  date nullable
  published_at       timestamp nullable
  softDeletes
  # media collection: images (multi, ordered)

product_options
  product_id  FK products cascadeOnDelete, index
  name        string                                  # seller-entered: "Colour"
  position    unsignedTinyInteger default 0           # max 2 per product, app-enforced

product_option_values
  product_option_id  FK cascadeOnDelete, index
  value              string                           # "Red"
  position           unsignedTinyInteger default 0

product_variants
  product_id        FK products cascadeOnDelete, index
  sku               string nullable
  options_label     string nullable                   # "Red / XL"; null for default variant
  option_value_ids  json nullable                     # [valueId, valueId]; resolve in PHP
  price_sen         unsignedBigInteger
  sale_price_sen    unsignedBigInteger nullable
  sale_starts_at    timestamp nullable
  sale_ends_at      timestamp nullable
  stock             unsignedInteger default 0
  is_default        boolean default false
  position          unsignedInteger default 0
  unique(product_id, sku)
  # media collection: image (single)
  # RULE: every product has >=1 variant; no-variation products get one default variant.
  #       Cart/order logic only ever touches variants.
```

### Batch 3 — Cart & order pipeline

```
carts
  user_id  FK users cascadeOnDelete, UNIQUE            # guest cart = session, merged on login

cart_items
  cart_id             FK carts cascadeOnDelete
  product_variant_id  FK product_variants cascadeOnDelete
  qty                 unsignedInteger
  unique(cart_id, product_variant_id)
  # no price column — price is live until checkout

orders                                                 # parent, one per checkout
  order_no            string unique                    # e.g. MP2606A1B2C3
  user_id             FK users restrictOnDelete, index
  payment_method      string                           # PaymentMethod
  payment_status      string default 'pending', index  # PaymentStatus
  shipping_address    json                             # full snapshot of chosen address
  subtotal_sen        unsignedBigInteger
  shipping_total_sen  unsignedBigInteger
  discount_total_sen  unsignedBigInteger default 0
  grand_total_sen     unsignedBigInteger
  display_currency    char(3) default 'MYR'            # what buyer was viewing
  display_rate        decimal(16,8) default 1          # rate snapshot at checkout
  placed_at           timestamp
  paid_at             timestamp nullable
  expires_at          timestamp nullable               # placed_at + 60min for ipay88

sub_orders                                             # one per store in the checkout
  sub_order_no      string unique
  order_id          FK orders cascadeOnDelete, index
  store_id          FK stores restrictOnDelete, index
  status            string default 'pending_payment', index   # SubOrderStatus
  items_subtotal_sen  unsignedBigInteger
  shipping_fee_sen    unsignedBigInteger default 0
  shop_discount_sen   unsignedBigInteger default 0
  total_sen           unsignedBigInteger
  commission_rate     decimal(5,2)                     # resolved + snapshotted at creation
  commission_sen      unsignedBigInteger nullable      # computed at completion
  tracking_courier    string nullable
  tracking_no         string nullable
  shipped_at          timestamp nullable
  delivered_at        timestamp nullable
  completed_at        timestamp nullable
  auto_complete_at    timestamp nullable               # delivered_at + 7d
  cancelled_at        timestamp nullable
  cancel_reason       string nullable

order_items
  sub_order_id        FK sub_orders cascadeOnDelete, index
  product_id          FK products nullOnDelete, nullable
  product_variant_id  FK product_variants nullOnDelete, nullable
  product_name        string                           # SNAPSHOT (en at purchase)
  variant_label       string nullable                  # SNAPSHOT
  unit_price_sen      unsignedBigInteger               # SNAPSHOT (effective price)
  qty                 unsignedInteger
  line_total_sen      unsignedBigInteger

order_status_histories (created_at only)
  sub_order_id  FK sub_orders cascadeOnDelete, index
  from_status   string nullable
  to_status     string
  actor_type    string                                 # ActorType
  actor_id      unsignedBigInteger nullable
  note          string nullable

payments
  order_id          FK orders cascadeOnDelete, index
  gateway           string                             # PaymentMethod
  ref_no            string index                       # = order_no sent to iPay88
  amount_sen        unsignedBigInteger
  currency          char(3) default 'MYR'
  status            string default 'pending', index    # GatewayPaymentStatus
  ipay88_payment_id string nullable                    # channel code used
  ipay88_trans_id   string nullable
  ipay88_auth_code  string nullable
  signature_valid   boolean nullable
  requery_result    string nullable
  request_payload   json nullable
  response_payload  json nullable
  paid_at           timestamp nullable
  unique(gateway, ipay88_trans_id)                     # idempotent callbacks
```

### Batch 4 — Vouchers, reviews, finance, localization, CMS

```
vouchers
  scope             string                             # VoucherScope
  store_id          FK stores cascadeOnDelete, nullable  # null = platform
  code              string
  type              string                             # VoucherType
  value_sen         unsignedBigInteger nullable        # fixed
  percent           decimal(5,2) nullable              # percent
  max_discount_sen  unsignedBigInteger nullable        # cap for percent
  min_spend_sen     unsignedBigInteger default 0
  quota             unsignedInteger nullable           # null = unlimited
  per_user_limit    unsignedInteger default 1
  used_count        unsignedInteger default 0
  starts_at         timestamp
  ends_at           timestamp
  is_active         boolean default true
  unique(store_id, code)
  # platform-code global uniqueness enforced in validation (MySQL allows repeated NULLs)

voucher_usages (created_at only)
  voucher_id    FK vouchers cascadeOnDelete, index
  user_id       FK users cascadeOnDelete
  order_id      FK orders cascadeOnDelete nullable     # platform voucher
  sub_order_id  FK sub_orders cascadeOnDelete nullable # shop voucher
  discount_sen  unsignedBigInteger
  index(voucher_id, user_id)

reviews
  order_item_id      FK order_items cascadeOnDelete, UNIQUE   # one review per item
  product_id         FK products cascadeOnDelete, index
  store_id           FK stores cascadeOnDelete, index
  user_id            FK users cascadeOnDelete
  rating             unsignedTinyInteger                # 1–5
  comment            text nullable
  seller_reply       text nullable                      # one reply, folded into row
  seller_replied_at  timestamp nullable
  is_hidden          boolean default false
  # media collection: photos (max 5, app-enforced)

store_ledger_entries (created_at only)
  store_id      FK stores cascadeOnDelete, index
  sub_order_id  FK sub_orders nullOnDelete, nullable
  payout_id     FK payouts nullOnDelete, nullable
  type          string                                  # LedgerEntryType
  amount_sen    bigInteger                              # SIGNED: credits +, debits −
  status        string default 'available', index      # LedgerEntryStatus
  description   string nullable
  # Entries created when sub_order hits `completed` (return window has passed by then):
  #   +sale (items+shipping), −commission. Post-completion refunds = admin `adjustment`.
  # COD: +sale, −commission, −cod_offset (cash already with seller) — nets commission owed.
  # Balance = SUM(amount_sen) WHERE status='available'.

payouts
  payout_no     string unique
  store_id      FK stores restrictOnDelete, index
  amount_sen    unsignedBigInteger
  status        string default 'requested', index      # PayoutStatus
  bank_snapshot json                                    # bank name + account at request time
  requested_at  timestamp
  approved_at   timestamp nullable
  paid_at       timestamp nullable
  reference     string nullable                         # bank transfer ref
  processed_by  FK users nullOnDelete, nullable         # admin

currencies
  code            char(3) unique
  name            string
  symbol          string(8)
  decimal_places  unsignedTinyInteger default 2
  is_base         boolean default false                 # MYR only
  is_active       boolean default true
  position        unsignedInteger default 0

exchange_rates                                          # append-only; latest row wins
  currency_code   char(3) index                         # FK currencies.code
  rate            decimal(16,8)                         # 1 MYR = rate × currency
  margin_percent  decimal(5,2) default 0
  source          string default 'manual'               # manual|api
  fetched_at      timestamp

banners
  title      json (t)
  link_url   string nullable
  position   unsignedInteger default 0
  starts_at  timestamp nullable
  ends_at    timestamp nullable
  is_active  boolean default true
  # media collection: image (single)

pages
  slug       string unique                              # about, terms, privacy, refund-policy, faq
  title      json (t)
  body       json (t)                                   # longText
  is_active  boolean default true
```

Plus published vendor migrations: `permission_tables`, `media`, `activity_log`, `notifications`, `settings`.

---

## 6. Settings Classes (`App\Settings`, spatie/laravel-settings)

```
GeneralSettings   site_name, default_locale='en', enabled_locales=['en','ms'],
                  base_currency='MYR', display_currencies=['MYR','USD','SGD','IDR']
CommissionSettings global_rate=5.00          # %, placeholder — confirm before launch
OrderSettings     return_window_days=7, auto_complete_days=7, unpaid_expiry_minutes=60
CodSettings       enabled=true, max_order_sen=50000        # RM500 — ASSUMPTION, confirm
Ipay88Settings    merchant_code, merchant_key (encrypted cast), sandbox=true
```

---

## 7. Model Scaffold

All models in `App\Models`. Money columns cast to `integer`; build `App\Support\Money` helper wrapping `brick/money` (`Money::ofMinor($sen,'MYR')`) plus a `@money($sen)` Blade directive. Display conversion later via a `CurrencyConverter` service reading latest `exchange_rates` — storage stays MYR.

| Model | Traits | Relationships & notes |
|---|---|---|
| User | HasRoles, Notifiable, SoftDeletes | hasMany addresses, orders, reviews; hasOne store, cart |
| Address | — | belongsTo user; booted: only one `is_default` per user |
| Store | HasSlug, InteractsWithMedia, SoftDeletes, LogsActivity | belongsTo user; hasMany products, subOrders, vouchers, ledgerEntries, payouts, documents; `scopeApproved` |
| StoreDocument | InteractsWithMedia | belongsTo store |
| Category | HasTranslations, HasSlug, InteractsWithMedia | parent/children self-relations; hasMany products; belongsToMany attributes |
| Brand | HasSlug | hasMany products |
| Attribute / AttributeValue | HasTranslations | belongsToMany categories / belongsTo attribute |
| Product | HasTranslations, HasSlug, InteractsWithMedia, Searchable, SoftDeletes, LogsActivity | belongsTo store, category, brand; hasMany options, variants; `defaultVariant()`; `scopeLive`; `toSearchableArray` flattens en+ms name/desc, category path, store name, price range |
| ProductOption / ProductOptionValue | — | option hasMany values; product hasMany options |
| ProductVariant | InteractsWithMedia | belongsTo product; `effectivePriceSen()` (sale window check); `resolveByValues(array $valueIds)` static — load product variants, match in PHP |
| Cart / CartItem | — | cart belongsTo user, hasMany items; item belongsTo variant; `CartService::mergeSessionCart()` on login |
| Order | — | belongsTo user; hasMany subOrders; hasOne payment; casts shipping_address json; `generateOrderNo()` |
| SubOrder | LogsActivity | belongsTo order, store; hasMany items, statusHistories; status transitions ONLY via `SubOrderStatusService` (writes history row + fires events) |
| OrderItem | — | belongsTo subOrder, product (nullable), variant (nullable); hasOne review |
| OrderStatusHistory | — | belongsTo subOrder |
| Payment | — | belongsTo order; json casts for payloads |
| Voucher / VoucherUsage | — | voucher belongsTo store (nullable), hasMany usages; `isRedeemableBy(User, int $cartSubtotalSen): bool` |
| Review | InteractsWithMedia | belongsTo orderItem, product, store, user; observer recalculates product + store rating aggregates |
| StoreLedgerEntry | — | belongsTo store, subOrder, payout |
| Payout | LogsActivity | belongsTo store, processedBy(User); `generatePayoutNo()` |
| Currency / ExchangeRate | — | `ExchangeRate::latestFor(string $code)` |
| Banner / Page | HasTranslations (+ media on Banner) | `scopeActive` with date window |

**Services to stub now (implement in later phases):** `CommissionResolver` (store → category chain upward → global; implement fully now — it's pure logic), `SubOrderStatusService`, `CartService`, `CheckoutService`, `Ipay88Service` (signature build/verify + requery; sandbox creds), `CurrencyConverter`.

---

## 8. Seeders & Factories

Seeders (idempotent):
1. `RoleSeeder` — roles `buyer`, `seller`, `admin`; admin permissions: `sellers.manage`, `products.moderate`, `orders.manage`, `finance.manage`, `vouchers.manage`, `cms.manage`, `settings.manage`, `localization.manage`
2. `CurrencySeeder` — MYR (base), USD, SGD, IDR + one manual exchange_rates row each
3. `AdminUserSeeder` — admin user from env vars
4. `CategorySeeder` — sample 3-level tree (Electronics, Fashion, Home & Living branches)
5. `PageSeeder` — about, terms, privacy, refund-policy, faq stubs (en + ms)
6. `DemoSeeder` (local only) — 10 approved stores, 100 live products with options/variants/images via factories

Factories for: User, Store, Category, Brand, Product, ProductOption, ProductOptionValue, ProductVariant, Address. Product factory state `->withVariants(colour: 3, size: 2)` builds the full option matrix.

---

## 9. Task Sequence for Claude Code

Work top to bottom; each task ends with passing tests + Pint clean.

1. **Bootstrap** — fresh Laravel 12 app, install packages (§3), configure locale/fallback `en`, Redis for cache/session/queue, Scout→Meilisearch, publish vendor migrations. Commit.
2. **Enums** — all of §4 as string-backed enums with `label()` methods.
3. **Migrations batch 1** (§5) + run.
4. **Migrations batch 2** + run.
5. **Migrations batch 3** + run.
6. **Migrations batch 4** + settings classes (§6) + run.
7. **Models** — per §7 with relationships, casts, traits, scopes. `Money` helper + Blade directive. `CommissionResolver` fully implemented + unit-tested (override > category chain > global).
8. **Factories & seeders** (§8). `migrate:fresh --seed` must produce a browsable dataset.
9. **Pest smoke tests** — every relationship resolves; variant matrix factory builds correctly; money helper formats RM correctly; voucher `isRedeemableBy` edge cases; CommissionResolver hierarchy.
10. **Route + middleware skeleton** — three RESTful route groups (`web.php` storefront, `routes/seller.php`, `routes/admin.php`); middleware: `SetLocale` (session → user pref → default), `SetDisplayCurrency`, `EnsureSeller` (role + store approved), `EnsureAdmin`. Empty Livewire page components per context so all routes render.
11. **CLAUDE.md update** — record §1 + §2 of this doc as project conventions.

Stop after Task 11. Next plan: Shopee-style storefront UI (the confirmed v1 priority).

---

## 10. Guardrails for the Build

- Never store or compute money as float — `*_sen` integers + brick/money only.
- Never transition `SubOrder.status` by direct assignment — service only.
- Translatable setters always write the `en` key at minimum; UI fallback handles `ms`.
- Product create flow must always create a default variant when no options are defined.
- Stock decrements happen at order placement inside a DB transaction with `lockForUpdate()` on variants.
- iPay88 fulfilment path (Phase 2) trusts BackendURL + requery only — wire the `payments` table now so nothing needs reshaping.
