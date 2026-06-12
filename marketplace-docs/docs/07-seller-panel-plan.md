# 07 — Seller Centre Plan (M3 core, M6 fulfilment, M8 finance)

Route prefix `/seller`, guard `EnsureSeller` (role + store `approved`). Same design tokens, **denser spacing** (design §4), table patterns from design §6. Components in `App\Livewire\Seller\*`. Every query is store-scoped — add a `BelongsToStore` trait/global-scope helper and test leakage (seller A must never see seller B's rows).

## A. M3 — Onboarding + Catalog

**A1. Application flow** (storefront-side, any logged-in buyer): `/seller/apply` — shop name (slug live-preview, uniqueness check), description, state, SST-registered toggle (+ number), bank details (json snapshot fields), document uploads (SSM, IC — `store_documents` + media). Submit → store `pending` → "Application received" status screen (replaces seller panel until approved; shows rejected state + reason + re-apply). Turnstile on. Email on submit/approve/reject.

**A2. Shell + Dashboard**: sidebar nav (Dashboard, Products, Orders, Vouchers M8, Earnings M8, Reviews M8, Shop settings), topbar with storefront link + notifications bell. Dashboard: stat cards (today's orders, to-ship count, live products, low-stock count — count-up per design §7), to-do strip ("3 orders waiting to ship" → deep links), recent orders table, 14-day sales sparkline (placeholder data until M4).

**A3. Products index**: datagrid — thumb, name, variant count, price range, stock total, sold, status pill, updated. Filters: status, category, low-stock. Search by name/SKU. Row actions: edit, duplicate, delist/relist. Bulk: delist, delete (drafts only).

**A4. Product form** (create/edit, single page with sections — the hardest UI of the project):
1. **Basics**: language tabs (EN | BM, en required, fallback note) for name + rich-text description (sanitized), category cascader (3 levels), brand select, condition, attributes (from category mapping).
2. **Images**: multi-upload (drag-sort via medialibrary order), 1:1 crop hint, max 9.
3. **Variations** (Alpine-heavy): toggle. Off → single price/stock/SKU (default variant). On → up to 2 option groups (name + values as chips) → live-generated **matrix table**: per-row image, price, stock, SKU; bulk-apply row ("set all prices"). Edits to options reconcile existing variants (keep matching value-id pairs, soft-handle removals: block removal if variant has order history — delist instead).
4. **Sale**: optional sale price + schedule, per-variant or apply-all.
5. **Shipping**: weight g, dims mm, COD toggle.
6. Save as **Draft** / **Publish** (→ `live`, or `pending_review` when `require_product_approval` on — banner explains). Server validation mirrors every client rule.

**A5. Shop settings**: profile (logo/banner crops, description language tabs, state), **holiday mode** (with "buyers can't order while on" warning), **shipping settings** — mode flat (one fee_sen) | state matrix (16 MY states + free-over-threshold optional), bank details update (re-snapshot on payout request only).

## B. M4/M6 — Orders & fulfilment
Tabs: New (confirmed — act fast indicator showing hours since paid) · To Ship (processing) · Shipped · Delivered · Completed · Cancelled · Returns (M8). Detail: items, buyer note, address (print packing slip PDF), timeline. Actions: **Confirm & pack** (confirmed→processing) · **Arrange shipment** modal (courier select from settings list + tracking_no, mono input) → shipped · cancel (pre-ship, reason) → restock via service. All transitions through SubOrderStatusService. New paid order → database+mail notification with sound-free badge bump (`wire:poll.30s` on counts).

## C. M8 — Finance, vouchers, reviews (built in `docs/09`, surfaced here)
Earnings: balance cards (pending=escrow note removed — available / paid out), ledger table (mono amounts, type chips), **Request payout** (≥ RM50 min, settings), payout history. Vouchers: shop-scope CRUD per 09 §B. Reviews: list + one reply per review.

## D. Task sequence
1) Shell + EnsureSeller + scoping trait + leakage tests → 2) Application flow + status screens → 3) Dashboard (stubs) → 4) Products index → 5) Product form basics+images → 6) Variant matrix builder (component-test the reconciliation) → 7) Sale + shipping sections + publish/approval states → 8) Shop settings + shipping matrix → 9) (M4) order queue + ship modal + packing slip → 10) Dusk: apply → approve (artisan helper) → create variant product → publish → appears on storefront.
