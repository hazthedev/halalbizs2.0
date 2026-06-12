# 08 — Admin Panel Plan (M7)

Route prefix `/admin`, guard `EnsureAdmin` + spatie permission checks per section (`sellers.manage`, `products.moderate`, `orders.manage`, `finance.manage`, `vouchers.manage`, `cms.manage`, `settings.manage`, `localization.manage`). 2FA enforced for all admin users (Fortify/laragear-2fa task). Components in `App\Livewire\Admin\*`. Dense table-first UI per design §6; every destructive action confirms; everything writes `activity_log`.

## A. Dashboard
Stat row: GMV (paid, period picker), commission revenue, orders today, new buyers, **pending queues** (seller applications, product reviews, payout requests, return escalations — each card deep-links). Charts: 30-day GMV line, orders by status donut, top 5 stores / categories tables.

## B. Sellers
- **Applications queue**: table → review drawer (details, documents viewer with verify/reject per doc, bank info) → Approve (store `approved`, role granted, email) / Reject (reason required, email). 
- **Stores**: list (rating, products, GMV, commission eff. rate), detail: suspend/reinstate (reason), per-store **commission override** input, document re-verification, impersonate-view storefront link.

## C. Buyers
List + search, detail (orders summary, addresses), suspend/ban with reason (blocks login via status check), PDPA deletion request handling (anonymize per 09 §F).

## D. Catalog governance
- **Categories**: tree UI (drag-reorder within parent, 3-level max enforced), CRUD with language tabs, image, per-category commission_rate, attribute mapping multiselect. Deleting requires empty (or reassign products picker).
- **Attributes/Brands**: simple CRUD, filterable toggle.
- **Moderation queue**: `pending_review` (when approval setting on) + flagged products — preview-as-storefront drawer, Approve → live / Reject (reason → seller notification) / Ban. Bulk approve.

## E. Orders oversight
All sub_orders datagrid (filters: status, store, method, date; search order_no — mono). Detail mirrors seller view + admin powers: force-cancel (restock + payment flag), mark refunded (with iPay88 portal reference), reassign nothing (no edits to snapshots — read-only by design). Payments tab: reconciliation grid (payments vs iPay88 status, requery button per row, signature_valid flag highlighted).

## F. Finance
- **Commission settings**: global rate, category overrides summary (links to category form), store overrides summary. Effective-rate tester widget (pick store+category → shows resolver result — uses CommissionResolver, doubles as living documentation).
- **Payouts queue** (M8 activation): requested → review (store ledger drill-down) → approve → batch **Export bank CSV** (one file per run: account, name, amount RM, payout_no) → mark paid (+reference). Reject with reason returns funds to available.
- Ledger explorer per store; platform earnings report (commission by period).

## G. Promotions & content
- Platform **vouchers** CRUD (engine M8; form ready M7 behind flag).
- **Banners**: CRUD, image, schedule, position.
- **Home sections manager**: orderable list (banner | category_grid | product_carousel{source: latest/top/category/manual ids} | recently_viewed), payload editor per type, enable toggle — drives storefront B1.
- **CMS pages**: list + editor (language tabs, rich text sanitized), slug-locked system pages.

## H. Localization
Languages: enable/disable `ms`/`zh` (en locked on). Currencies: CRUD + active toggles. **Exchange rates**: latest-per-currency table, inline manual update (writes new row), API-sync toggle + margin %, sync-now button, history drawer.

## I. System
Settings screens grouped by class (General, Order, COD, Commission, Moderation, Security/Turnstile, Tracking pixels, iPay88 — key fields masked, test-connection button hits requery with dummy ref expecting structured error). Admin staff: invite, role assignment (spatie roles editor scoped to admin permissions). **Audit log** viewer: activity_log datagrid (causer, subject, diff render), filter by section.

## J. Task sequence
1) Shell + 2FA + permission middleware map → 2) Seller applications + stores → 3) Categories tree + attributes/brands → 4) Moderation queue → 5) Buyers → 6) Orders oversight + payments reconciliation → 7) Commission settings + tester → 8) Banners + home sections manager (unblocks storefront B1 admin control) → 9) CMS → 10) Localization + rates → 11) Settings screens + staff/roles + audit viewer → 12) Dashboard last (all data exists by then). Pest: permission matrix (each section × role), commission tester cases, rate update writes history.
