# 09 — Marketplace Depth Plan (M8)

The features that turn a working shop into a marketplace people trust. Order within M8 matters: Ledger → Vouchers → Reviews → Returns → Search/SEO → polish.

## A. Seller finance (LedgerService + payouts)

**Trigger**: sub_order → `completed` (buyer confirm or auto-complete). LedgerService writes, in one transaction:
- `+ sale` = items_subtotal + shipping_fee − shop_discount
- `− commission` = round(items_subtotal × commission_rate); persist `commission_sen` on sub_order
- COD orders additionally `− cod_offset` = sale amount (seller already holds the cash) → net ledger effect = commission owed; platform recovers it from future online-payment balance (negative available balance allowed; payout blocked while negative).
- Increment product/variant `sold_count` here (not at checkout — completion is the truth).
**Post-completion refunds** = admin `adjustment` entries (signed, reason required).
**Payout flow**: seller requests (min RM50 setting, ≤ available) → entries earmarked `payout_id` → admin approve → batch CSV → mark paid (`paid_out` status). Reject releases earmark. Pest: full lifecycle math, COD math, negative-balance block, concurrent request guard.

## B. Voucher engine (replaces `voucher_lite`)

`VoucherService::validate(code, user, sellerGroups)` pipeline: exists+active+window → scope match → min_spend (platform: order subtotal; shop: that store's subtotal) → quota remaining → per-user count → returns Discount DTO (fixed | percent-capped | free_shipping which zeroes that store's shipping). **Stacking**: one platform + one shop voucher per store group (Shopee model). Redemption stays atomic in CheckoutService (already specced). Discount allocation: platform discounts prorate across sub_orders by subtotal (largest-remainder in sen — unit test rounding to exact total). 
**Seller UI**: voucher CRUD (code uniqueness per store, stats: used/quota). **Admin UI**: platform vouchers + global usage report. Checkout UI: replace stub with picker drawer (available vouchers listed per scope, best-savings hint).

## C. Reviews

Gate: order_item completed + no existing review. Entry from order detail "Rate your order" → per-item: stars, text (min 10 chars optional), up to 5 photos. **ReviewObserver** recalcs product + store aggregates (cached columns) on create/hide. PDP reviews tab: summary (avg big-number Bricolage, star distribution bars), list (photos lightbox, variant label, relative date, seller reply block), filters (with-photos, star). Seller: review list + one reply (edit within 24h). Admin: hide/unhide (reason logged). Anti-abuse: Turnstile on submit, rate limit, only-purchased gate already structural.

## D. Returns & refunds

Buyer (within `return_window_days` of delivered, status delivered/completed): request per sub_order → reason (`return_reasons`), description, up to 5 photos → sub_order `return_requested`. Seller 48h (setting) to **Accept** (→ `returned`; buyer ships back manual v1; on seller "received" → admin refund task) or **Dispute** → escalates to admin queue. Timeout = auto-escalate (scheduled job). Admin resolution: refund buyer (iPay88 manual portal / COD = ledger adjustment) → `refunded`, or side with seller → restore previous status. Ledger: refund after completion → adjustment reversing sale & commission proportionally. Notifications at every hop. Status math tests: each path's ledger net.

## E. Search & discovery upgrades
Meilisearch **synonyms** (admin-editable key→list UI → pushes index settings) · trending terms (cached top search_logs 7d) in overlay · zero-result report for admin (top terms with results_count=0) · "Sort: top sales" now backed by real sold_count.

## F. SEO, compliance & housekeeping jobs
`spatie/laravel-sitemap` nightly (products live, categories, stores approved, pages) · `url_redirects` middleware (301 lookup on 404 path, hit counter) + auto-insert on slug change observers (product/store/category) · rich-snippet audit (Product, BreadcrumbList, Store as LocalBusiness-lite) · PDPA: **export my data** (queued zip: profile, addresses, orders json → signed download link, 7-day expiry) and **delete account** (anonymize user row, keep order snapshots — legal/financial records; document retention stance in privacy page).

## G. Notification matrix completion
Single `NotificationMap` table-driven doc in code comments: event × (buyer mail, buyer db, seller mail, seller db, admin db). Add digest guard (no more than 1 mail/min/user via rate-limited queue middleware). In-app notification center gets type icons + deep links.

## H. Multicurrency polish
CurrencyConverter rounding rules (display: round to currency decimals, never sum converted line items — convert totals only), stale-rate banner (rate older than 48h → admin warning), switcher persists to user record.

## I. Task sequence
1) LedgerService + completion hook + COD math + payout request → 2) Admin payout queue + CSV (activates 08 §F) → 3) VoucherService + checkout integration + seller/admin CRUD + proration tests → 4) Reviews end-to-end + aggregates → 5) Returns flow + escalation job + ledger adjustments → 6) Search upgrades → 7) SEO jobs + redirects observers → 8) PDPA export/delete → 9) Notification matrix audit → 10) Full-regression Dusk: buy (voucher, iPay88 sandbox) → ship → deliver → complete → ledger → review → payout request.
