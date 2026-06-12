# 05 — Storefront Plan (M2)

Buyer-facing surface. Everything here follows `docs/03-design-system.md` exactly — ink frame, emerald discipline, reactive patterns from §7. All internal links use `wire:navigate`. Components live in `App\Livewire\Storefront\*`.

## A. Layout shell

**`layouts/storefront.blade.php`**
- **Ink header**: logo (off-white wordmark) · centered search field (opens overlay) · right: locale switcher, currency switcher, cart button with count badge, account menu. 1px emerald-night keyline under header. Sticky.
- Secondary strip (paper): category links (top 8 + "All categories" mega-menu on hover/tap).
- **Ink footer**: link columns (About, Help, Seller centre CTA), newsletter signup (emerald submit → writes `newsletter_subscribers`), payment method marks, locale/currency repeat, copyright.
- Toast container (Alpine store `$store.toasts`), ink surface, auto-dismiss 4s, action slot (View cart / Undo).
- `SetLocale` + `SetDisplayCurrency` already run; switchers POST preference → session + user record, then redirect back.

Components: `Layout\SearchOverlay`, `Layout\MiniCart` (slide-over), `Layout\LocaleSwitcher`, `Layout\CurrencySwitcher`, `Layout\CartBadge` (listens `cart-updated` event).

## B. Pages

### B1. Home `/`
Renders active `home_sections` by position (admin-managed, M7; until then seed fixed sections):
1. Banner carousel (Swiper, no autoplay, swipe + arrows)
2. Category grid (2×4 mobile, 8×1 desktop, category image + translated name)
3. "New on the market" product carousel (latest live products)
4. "Popular now" grid (by `sold_count`, 12 items, View all → listing)
5. Recently viewed strip (localStorage IDs → hydrate via one query; hidden when empty)
Each section: Bricolage section title + ghost "View all".

### B2. Listing `/c/{category:slug}` and Search `/search?q=`
One engine, two entries (`Listing\ProductGrid` + `Listing\Filters`):
- Grid per design §4 product card. 24/page, intersection-observer "Load more".
- Filters (desktop sidebar / mobile bottom sheet): category children, price range (min/max inputs, sen-converted), rating ≥, seller state, COD available. Sort: relevance (search only) | latest | top sales | price ↑↓.
- Every filter change: instant, query-string synced (`#[Url]` attributes), applied-filter chips above grid, `wire:loading` opacity + skeletons, zero layout shift.
- Search entry logs to `search_logs` (term, results_count, user). Breadcrumbs on category pages.
- Meilisearch query via Scout; filterable attributes configured in `config/scout.php` index settings task.

### B3. Search overlay (header / `/` key)
Per design §7: debounced 300ms, grouped results (products with thumb+price, stores, categories), keyboard nav (↑↓ Enter Esc), empty state shows recent searches (localStorage) + trending (top `search_logs` terms, cached 1h). Enter → full search page.

### B4. Product detail `/p/{product:slug}`
Two-column desktop, stacked mobile:
- **Gallery**: main image + thumb strip, Alpine-driven, variant image overrides on selection, pinch-zoom mobile.
- **Buy box**: title (16/600) · rating ★ + count (anchors to reviews) + sold count · price block (effective price ink-bold; if sale: struck original ink-faint + emerald `-x%` chip + countdown if `sale_ends_at` < 48h) · variant option chips (design §7 picker — unavailable combos disabled 40%) · qty stepper (max = variant stock; "Only N left" warn under 10) · shipping row (ships from {store.state}, fee preview via store shipping settings + buyer default address state) · badges row (COD if `cod_enabled` && setting, Free shipping voucher hint later) · **Add to cart** (ink-outline) + **Buy now** (emerald fill → cart+checkout direct).
- **Seller card**: logo, name, ★ shop rating, products count, state, "Visit store" ghost button, Verified badge.
- Tabs: Description (rich text, translated w/ fallback) · Specifications (attributes table) · Reviews (M8 — render "No reviews yet" gate now).
- Related products carousel (same category, exclude self).
- Mobile **ink sticky buy bar**: store chat icon (disabled, tooltip "Coming soon"), Add to cart, Buy now.
- Records product ID into recently-viewed localStorage. JSON-LD Product schema in head.

### B5. Store page `/s/{store:slug}`
Banner + logo header (ink-tinted overlay for text contrast), name, ★ rating, joined date, state, products count. Holiday-mode notice banner when active. Tabs: Products (listing engine scoped to store, with store-category filter) · About (description). Follow button = parked (P4).

### B6. Cart `/cart`
- Groups by seller (store header row with name + link). Item rows: thumb, title, variant label, unit price, qty stepper (`wire:model.live`), line total, remove (optimistic + Undo toast).
- Per-seller subtotal; sticky summary card: items total, "shipping calculated at checkout", grand total, **Checkout** (emerald). Select-all + per-item checkboxes (checkout only selected — Shopee behavior; cart_items get `selected` boolean column — add tiny migration).
- Stock revalidation on load: items over stock get warn badge + qty clamped. Empty state per design §6.
- Guest: session cart mirror of same UI; checkout button → login (intended URL preserved); `CartService::mergeSessionCart` on auth.

### B7. Auth
Login, Register (name/email/password + ToS consent checkbox → PDPA), Verify email notice, Forgot/Reset. Cloudflare Turnstile on register + login + forgot (server-side verify via `SecuritySettings`). Rate limit 5/min. Post-register → email verification gate before checkout.

### B8. Buyer account shell `/account`
Left nav (mobile: top tabs): Profile (name, phone, email, password, preferred locale/currency) · Addresses (CRUD, default toggle, MY state select) · Wishlist (grid of saved products; heart toggle on product cards + PDP writes `wishlists` table — add migration: user_id + product_id unique) · Orders (M4 placeholder route) · Notifications (database notifications list, mark-read).

### B9. Static pages `/page/{slug}` + locale-aware rendering of seeded pages.

## C. New tiny migrations this milestone
```
cart_items += selected boolean default true
wishlists: user_id FK, product_id FK, unique(user_id, product_id), timestamps
```

## D. Task sequence
1. Tailwind tokens + fonts (fontsource) + base Blade components (button, input, badge, card, skeleton, toast system) — build a `/dev/ui` kitchen-sink page first, screenshot-review against design §6.
2. Storefront layout: ink header/footer, switchers, toasts.
3. Home with seeded sections + recently-viewed.
4. Listing engine: grid, filters, sort, URL sync, load-more. Wire Meilisearch index settings.
5. Search overlay + search page + search_logs.
6. PDP: gallery, variant picker, buy box, seller card, tabs, related, sticky bar, JSON-LD.
7. Store page.
8. Cart (+selected column) + mini-cart slide-over + optimistic add-to-cart from cards/PDP.
9. Auth + Turnstile + verification gate.
10. Account shell + addresses + wishlist (+migration).
11. Dusk happy path: browse → search → PDP variant select → add to cart → cart qty change. Pest: CartService merge, filter query-string round-trip, search logging.

Done = M2 demo: a stranger can browse a seeded marketplace on a phone and it feels fast and finished.
