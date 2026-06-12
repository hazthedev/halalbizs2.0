# Bagisto Feature Analysis → Marketplace Feature Decisions

**Sources reviewed:** `bagisto/bagisto` repo (v2.4 branch, README, releases/changelog) and docs.bagisto.com full feature taxonomy, including the Multi-Vendor Marketplace extension docs.
**Purpose:** Bagisto is the most mature Laravel ecommerce codebase available — this doc mines its feature set and engineering lessons, and records what we adopt, adapt, or deliberately skip. We are still building custom (locked decision); Bagisto is a reference, not a base.

---

## 1. Bagisto's Feature Taxonomy (what they ship)

**Catalog:** 7 product types (simple, configurable, virtual, bundle, grouped, downloadable, booking) · EAV attributes with attribute families and input types · category tree.
**Orders:** admin-created orders · invoices, shipments, refunds as separate documents · transactions ledger · RMA (return merchandise authorization) · EU withdrawal.
**Customers:** customer groups · group-based pricing · reviews.
**Marketing:** cart rules (coupons/auto cart discounts) · catalog rules (pre-cart price rules by attribute conditions, priority, stacking control) · email templates, events, campaigns · newsletter subscriptions · sitemaps · URL rewrites · search terms · search synonyms.
**Reporting:** sales / customers / products report families.
**Settings:** locales, currencies + exchange rates (auto-update services) · multi-warehouse inventory sources · channels (multi-store) · admin users + ACL roles · themes/theme customization · taxes · data transfer (import/export).
**Configure:** guest checkout toggle · captcha (Google reCAPTCHA) · back orders · GDPR module · rich snippets · social share · custom scripts injection · invoice settings · image sizes · Magic AI (AI-generated content) · 2FA for admin.
**Multi-Vendor Marketplace extension:** vendor management · product approval workflow · commission management · seller ratings · mass payout management · seller catalog bulk upload · seller attributes · seller subscription plans · buyer↔seller communications · marketplace RMA.

**Engineering lessons from their changelog worth stealing:**
- They shipped a fix for a real race condition where two concurrent checkouts could both redeem a single-use coupon — resolved by making coupon validation + consumption atomic with row-level locking inside the order transaction.
- A checkout UI race let the normal Place Order button fire while the payment method was mid-switch, creating an order with the wrong method.
- Admin 2FA and reCAPTCHA were added as core security hardening.

---

## 2. Decision Matrix

### ✅ Adopt — gaps Bagisto exposed in our spec

| # | Feature (Bagisto) | Our implementation | Phase |
|---|---|---|---|
| A1 | Captcha on auth/forms | **Cloudflare Turnstile** on register, login, review submit, seller application. We had no bot protection — real gap. | 1 |
| A2 | Atomic coupon redemption | Guardrail: voucher quota + per-user limit checked and consumed with `lockForUpdate()` **inside** the checkout DB transaction. Same for stock (already specced). | 2 |
| A3 | Checkout method-switch race | Place-order button disabled (wire:loading) while payment method / totals are recalculating; server re-validates method on submit. | 2 |
| A4 | Product approval workflow (marketplace ext.) | Setting `require_product_approval` (bool). On: new/edited products enter `pending_review` before `live`. Off: post-moderation only. Add `pending_review` to ProductStatus enum **now**. | 1 (schema), 2 (UI) |
| A5 | URL rewrites | `url_redirects` table (old_path, new_path, 301). Auto-created when a product/store/category slug changes — sellers rename things constantly. | 2 |
| A6 | Sitemaps | `spatie/laravel-sitemap` scheduled nightly: products, categories, stores, pages. | 2 |
| A7 | Search terms log | `search_logs` (term, results_count, user_id nullable, created_at). Powers trending searches, zero-result reports for admin, and recent-search suggestions in the search overlay. | 2 |
| A8 | Search synonyms | Managed via Meilisearch synonyms config, editable from admin localization/search settings. | 3 |
| A9 | Newsletter subscriptions | `newsletter_subscribers` (email, user_id nullable, verified_at, unsubscribed_at) + footer signup. Sending stays manual/external (Brevo) until P4. | 2 |
| A10 | Reporting families | Structure admin analytics exactly as their three families: **Sales** (GMV, AOV, by day/category/store), **Customers** (new, returning, top buyers), **Products** (top sellers, zero-sale, low stock). | 3 |
| A11 | Data transfer | Seller **bulk product upload via CSV/XLSX** (their marketplace ext. has this — sellers with big catalogs will demand it) + admin exports (orders, payouts, sellers). | 3 |
| A12 | RMA reasons | Admin-managed `return_reasons` and `cancellation_reasons` tables instead of free text — makes dispute reporting quantifiable. | 3 |
| A13 | Mass payout management | Batch-approve payout requests + single consolidated bank CSV export per cycle. Extends our manual payout flow. | 3 |
| A14 | Custom scripts | Settings fields for GA4 / Meta Pixel / TikTok Pixel snippets injected in storefront layout. | 2 |
| A15 | Social share | Share buttons (WhatsApp first — Malaysia, then FB/X/copy-link) on product + store pages. | 2 |
| A16 | GDPR module → PDPA | Add **data export request** (download my data) alongside the deletion flow we already specced. | 3 |
| A17 | Recently viewed | Session/localStorage-based recently-viewed strip on home + PDP. No schema. | 2 |
| A18 | Magic AI | AI listing assistant for sellers (generate/translate product descriptions BM↔EN via API). Natural fit given our tooling. | 4 |
| A19 | Seller subscription plans | Park as a monetization option (featured-seller tiers) — schema untouched until decided. | 4+ (parked) |

### 🔁 Adapt — Bagisto solves it differently; ours is deliberate

| Bagisto approach | Our approach | Why |
|---|---|---|
| EAV attribute system (attribute families, values per channel/locale) | Plain relational columns + JSON translations + a lean attributes module for filters | EAV is Magento heritage — flexible but slow and painful to query. Our catalog is marketplace-simple; Meilisearch handles faceting. |
| 7 product types | Everything is a variant (default variant pattern). Virtual/downloadable = possible P4 column additions, not new types | Bundle/grouped/booking don't exist on Shopee-model marketplaces. |
| Invoices/shipments/refunds as separate document entities (Magento-style, supports partials) | Single shipment + invoice per sub_order; refund state on sub_order + payment | Marketplace sellers ship a sub_order as one parcel. Partial fulfilment is enterprise B2C complexity we don't need. |
| Catalog rules (attribute-conditioned auto pricing with priority/stacking) | Per-variant `sale_price` + schedule now; P3 flash-sale module = time-boxed price + stock allocation | Full rule engine is overkill; flash sales cover the marketplace use case. |
| Cart rules engine | Our voucher engine (platform + shop scopes) | Equivalent coverage for marketplace needs; ours adds the Shopee platform/shop stacking model Bagisto core doesn't have. |
| Multi-warehouse inventory sources | Per-seller stock IS the multi-source model | Each store is a "warehouse"; nothing to add. |
| Channels (multi-store) | Single storefront | Locked scope. |
| Exchange-rate auto-update services | Already specced (manual + API sync with margin) | Parity confirmed. |
| Buyer↔seller communications | Already phased (P4 chat) | Parity confirmed. |
| Seller ratings | Already specced (store rating aggregate from reviews) | Parity confirmed. |

### ❌ Skip — deliberate non-goals (record so we don't relitigate)

- **Guest checkout** — Bagisto makes it a toggle; we require accounts (Shopee model). COD without an account invites fraud and breaks order tabs/returns/chat.
- **Customer groups + group pricing** — B2B/wholesale concept; our buyers are one tier.
- **Back orders** — no overselling; stock is hard-reserved at placement.
- **Admin-created orders** — marketplace admins don't place orders on behalf of buyers.
- **EU withdrawal** — EU-specific consumer law; PDPA + our return flow covers MY.
- **Product compare** — not a marketplace pattern; wishlist + recently-viewed suffice.
- **Theme system / page builder** — we ship one designed theme (see `docs/03-design-system.md`); admin gets the `home_sections` manager (P2) for ordering homepage blocks, not arbitrary page building.
- **B2B marketplace modules** (RFQ, buying leads, supplier microsites, requisition lists) — different product entirely.
- **Multi-tenant SaaS mode** — out of scope.

---

## 3. Gap Bagisto Doesn't Solve for Us: Malaysian Tax & e-Invoice

Bagisto ships a generic tax engine (tax categories/rates per zone). Our spec had **nothing** — surface it now:

- **v1 position:** seller-entered prices are final/tax-inclusive; platform is not the seller of record and computes no tax. State this in seller T&C.
- **SST:** sellers above the registration threshold handle their own SST within their pricing. A per-store "SST registered" flag + SST number field on `stores` is cheap groundwork — add columns now (nullable), UI later.
- **LHDN e-Invoice (MyInvois):** Malaysia's phased e-invoicing mandate is rolling through 2025–2026 and will eventually touch marketplace transactions. **Action: verify the current LHDN timeline and marketplace obligations before Phase 2 launch** — this is a compliance checkpoint, not a build task yet.

---

## 4. Schema & Settings Delta (apply to Phase 1 plan)

New migrations (slot into Batch 4):
```
url_redirects            old_path unique, new_path, status_code default 301, hits
search_logs              term index, results_count, user_id nullable      (created_at only)
newsletter_subscribers   email unique, user_id nullable, verified_at, unsubscribed_at
return_reasons           label json (t), is_active, position
cancellation_reasons     label json (t), is_active, position
home_sections            type, title json (t), payload json, position, is_active   (P2 homepage manager)
```

Column additions:
```
stores    += sst_registered boolean default false, sst_number string nullable
ProductStatus enum += pending_review
```

Settings additions:
```
ModerationSettings   require_product_approval=false
SecuritySettings     turnstile_site_key, turnstile_secret (encrypted)
TrackingSettings     ga4_id, meta_pixel_id, tiktok_pixel_id
```

Guardrails additions (append to plan §10):
- Voucher redemption is atomic: validate + increment `used_count` + insert usage row under `lockForUpdate()` inside the checkout transaction.
- Place-order is disabled while any checkout mutation (method switch, voucher apply, address change) is in flight; server re-validates totals + method on submit regardless.

---

## 5. Practical Tip for the Claude Code Workflow

Bagisto's repo ships `CLAUDE.md` and `AGENTS.md` and is MIT-licensed. Keep a local clone as a read-only reference inside the project (gitignored):

```
git clone --depth 1 https://github.com/bagisto/bagisto reference/bagisto
echo "reference/" >> .gitignore
```

Then when implementing a hairy area (voucher locking, exchange-rate sync, RMA flow), point Claude Code at the equivalent Bagisto implementation to study — *read their approach, write ours* — e.g. "Read how reference/bagisto handles coupon usage locking, then implement our voucher redemption per plan §10."
