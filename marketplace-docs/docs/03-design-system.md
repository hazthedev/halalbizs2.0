# Design System — "Souk: Emerald & Brass"

**Brief:** halal-friendly, welcoming, sophisticated · simple yet modern · warm ivory canvas + warmed ink frame · emerald = money/action · brass = premium/ornament · Islamic geometric craft.
**Applies to:** storefront, seller centre, and admin — all three share the same tokens; seller/admin run denser.
**Stack:** Tailwind CSS v4 (`@theme` tokens) · Livewire 4 · Alpine.js · `wire:navigate` throughout.

> **History.** This supersedes the original austere "Ink & Emerald" system (cool greys, Bricolage Grotesque, strictly flat / no-shadow / no-gradient, emerald-only). The redesign keeps emerald's discipline as the money/action colour but warms the whole canvas, swaps to a sophisticated serif display, and **sanctions** soft elevation + Islamic geometric ornament for warmth and craft.

---

## 1. Direction

A marketplace Malaysian Muslims trust with money should feel **warm, crafted, and calm** — like a well-kept Madinah souk rendered as a modern, well-set ledger. We keep Shopee's *information architecture* (dense product grids, order tabs, seller storefronts) but render it with quiet luxury:

- **The ink frame, warmed.** A warm near-black anchors the page edges — header, footer, topbars, the mobile sticky buy bar — framing a warm ivory canvas where products live on softly-elevated cards. The dark frame now carries a faint **girih** (interlaced-square / khatam) watermark and a **brass** keyline. Dark frame, warm canvas. This is the layout signature.
- **Emerald means money or action. Brass means premium or ornament.** Every emerald pixel is a CTA, a price advantage, or a trust mark (verified seller, order completed). Brass carries the brand glyph, ornament, section marks, and "premium/boost" affordances. Never recolour a money/action element brass; never decorate with emerald. This two-accent discipline is what keeps each colour meaningful.
- **Soft, not flat.** Cards sit on a warm hairline border **with a low, warm-tinted shadow** (`shadow-soft`/`shadow-card`); hover lifts them a hair. Overlays use `shadow-pop`. Subtle depth + crafted ornament reads welcoming and premium, not austere.

---

## 2. Color Tokens

```css
/* resources/css/app.css */
@theme {
  /* canvas + ink — warm */
  --color-paper:        #FAF7F0;  /* warm ivory page background */
  --color-surface:      #FFFDF8;  /* warm white — cards, inputs, sheets */
  --color-ink:          #1A1714;  /* warm near-black: frame surfaces + primary text */
  --color-ink-soft:     #5C544B;  /* secondary text */
  --color-ink-faint:    #8E867A;  /* placeholders, disabled text */
  --color-line:         #E7E1D5;  /* borders, dividers, skeletons */
  --color-line-strong:  #D2C9B8;  /* input borders, hover lines */

  /* emerald = money & action (hexes kept exact — charts/tests depend on them) */
  --color-emerald:        #047857;  /* primary actions, links, savings */
  --color-emerald-deep:   #065F46;  /* hover */
  --color-emerald-night:  #03392B;  /* pressed */
  --color-emerald-tint:   #E7F1EB;  /* badge/selected-state backgrounds */

  /* brass = premium & ornament */
  --color-brass:        #A8772E;  /* khatam glyph, keylines, premium/boost CTAs, marks */
  --color-brass-deep:   #8A5F22;  /* brass hover/active */
  --color-brass-tint:   #F3EAD6;  /* brass badge/medallion backgrounds */

  /* semantic (used sparingly) */
  --color-warn:    #B45309;  /* pending payment, low stock */
  --color-danger:  #BE123C;  /* destructive, errors, cancelled */
  --color-warn-tint:   #FBF3E8;
  --color-danger-tint: #FBEDF0;

  /* elevation (warm-tinted, low) */
  --shadow-soft: 0 1px 2px rgba(26,23,20,.05);
  --shadow-card: 0 1px 2px rgba(26,23,20,.04), 0 10px 28px -14px rgba(26,23,20,.14);
  --shadow-pop:  0 8px 40px -8px rgba(26,23,20,.22);

  /* radius */
  --radius-control: 11px;  /* buttons, inputs, chips */
  --radius-card:    14px;  /* cards, images, containers */
}
```

Usage rules:
- Text on `paper`/`surface`: `ink`; secondary `ink-soft`. Text inside the ink frame: warm off-white at 100%, secondary at ~64% opacity; brass-tint for section labels.
- White text on `emerald` (#047857) and on `brass` (#A8772E) passes WCAG AA — never put white text on the lighter tints.
- **Prices are `ink`, bold.** Only the *advantage* is emerald: `RM 89.00` in ink, `-30%`/`Save RM 38` in emerald, struck original in `ink-faint`.
- One emerald-filled CTA per view region; competing actions are ink-outline or ghost. Brass-filled buttons are reserved for premium/boost actions.
- Dark mode: not v1 — the warm ink frame already gives the brand its dark register.

---

## 3. Typography

| Role | Face | Weights | Used for |
|---|---|---|---|
| Display | **Fraunces** (variable serif, optical sizing on) | 500–700 | Page titles, section headers (via `<x-ui.section-heading>`), hero lines. Warm, editorial, sophisticated. Never below 18px. |
| Body / UI | **Plus Jakarta Sans** | 400 / 500 / 600 | Everything else. Product titles 500, prices 700 with `font-feature-settings:"tnum"`. |
| Utility mono | **JetBrains Mono** | 500 | Machine identifiers only: order numbers, SKUs, tracking numbers, voucher codes. |

```css
@theme {
  --font-display: 'Fraunces Variable', ui-serif, Georgia, Cambria, serif;
  --font-sans: 'Plus Jakarta Sans Variable', ui-sans-serif, system-ui, 'Noto Sans SC', sans-serif;
  --font-mono: 'JetBrains Mono Variable', ui-monospace, 'SFMono-Regular', monospace;
}
```

- Self-host via `@fontsource-variable` packages (no Google Fonts CDN — performance + PDPA-friendly).
- `.font-display` sets `font-optical-sizing: auto` + slight negative tracking so Fraunces reads crisp at display sizes.
- The `Noto Sans SC` fallback keeps a future `zh` locale from ugly defaults.
- Scale: 12 · 13 · 14 (UI base) · 16 (long-form) · 18 · 22 · 26 · 32 · 40. Line-height 1.5 body, 1.15 display. Sentence case everywhere — no ALL-CAPS except tiny badge text (11px, +0.03em tracking).

---

## 4. Shape, Space, Layout

- Spacing on a 4px grid. Storefront breathes (`py-12/16`); seller/admin run denser (`py-6/8`).
- Radius: cards & images `var(--radius-card)` (14px) · controls (buttons/inputs/chips) `var(--radius-control)` (11px) · badges/pills/avatars full pill.
- Container `max-w-7xl`. Product grids: 2 cols mobile → 3 sm → 4 lg → 6 xl, `gap-3`/`gap-4`.
- Borders 1px `line`; ornamental section breaks use `<x-ui.divider>` (a hairline with a centred khatam mark). Never gray bands.
- Product card anatomy: image (1:1, `object-cover`, paper background while loading) → title (2-line clamp, 13px/500) → price row (ink bold + emerald `-x%`) → meta row 12px `ink-soft`: ★ 4.8 · 1.2k sold · state. Card carries `shadow-soft`; hover lifts (`-translate-y-0.5`) to `shadow-card`, image scales 1.02 (200ms).

---

## 5. The Ink Frame (key surfaces)

- **Header (ink):** `surface-girih` watermark + 1px `brass/25` keyline. Khatam `<x-ui.star-mark>` beside the Fraunces wordmark; prominent center search field (`surface` input set into the ink bar); right side cart + notifications + account.
- **Footer (ink):** girih watermark + brass keyline; warm off-white text; brass-tint section labels; emerald only on the newsletter submit and trust marks; star-mark by the wordmark.
- **Seller/admin topbars (ink):** same girih + brass keyline + star-mark; sidebars on `surface` with **brass-tint active-nav pills** (emerald stays for money/action).
- **Mobile sticky buy bar (ink):** brass keyline; chat icon, Add to cart (ink-outline), Buy now (emerald fill).
- **Toasts (ink):** warm off-white text, emerald check icon, brass edge, `shadow-pop`, bottom-right desktop / bottom-center mobile.
- Drawers, modals, and menus stay light (`surface`) with `shadow-pop` — the frame is dark, the work is done in the warm light.

---

## 6. Components (token-level spec)

- **Buttons** (`<x-ui.button>`) — primary: emerald fill, white text, soft depth + hover lift, hover `emerald-deep`, pressed `emerald-night`; secondary: 1px `line-strong`, ink text, hover ink border + `paper`; **brass**: brass fill (premium/boost only); ghost: ink-soft, hover paper; danger: danger outline (fill in confirm dialogs). Loading: spinner replaces icon, label keeps its verb, `wire:loading.attr="disabled"`.
- **Inputs** — `surface` bg, 1px `line-strong`, control radius, focus: 2px emerald ring + emerald border (`focus-visible`), error: danger border + 13px fix-it help. Labels 13px/500 ink.
- **Cards** (`<x-ui.card>`) — warm border + `shadow-card`, card radius; `:ornament` adds a brass keyline, `:pattern` a faint zellij field.
- **Section heading** (`<x-ui.section-heading>`) — Fraunces title + brass khatam mark + optional "view all" link / actions slot. Use for page (`as="h1"`) and section titles.
- **Empty state** (`<x-ui.empty-state>`) — brass medallion with khatam motif + one Fraunces line + one sentence + a single action. "No orders to ship. New orders appear here the moment a buyer pays."
- **Badges** — Sale: `emerald-tint`+emerald · Free shipping: emerald outline · COD: `line-strong` outline · Verified: emerald fill+check · **Premium: `brass-tint`+brass** · Out of stock: `line`+ink-faint. 11px caps.
- **Order-status tabs** — horizontal scroll; active = ink + 2px ink underline; count chips `emerald-tint`. Status colours: pending → warn · confirmed/processing → ink · shipped/delivered → warm taupe · completed → emerald · cancelled/refund → danger.
- **Tables (seller/admin)** — hairline rows, 13px, mono for IDs/amounts, right-aligned tabular numbers, sticky header, row hover `paper`.
- **Skeletons** — warm shimmer sweep; match real layout (image square + two text lines).
- **Ornament** — `<x-ui.star-mark>` (khatam glyph), `surface-girih` (light lines, dark frames) / `surface-zellij` (brass lines, light surfaces), `<x-ui.divider>` (ornamental rule).

---

## 7. Reactive Behavior (the "interactive, reactive" brief)

`wire:navigate` on all internal links — the whole storefront feels SPA-instant with zero SPA complexity.

| Pattern | Spec |
|---|---|
| **Search overlay** | Header search opens a command-palette overlay (also `/`). `wire:model.live.debounce.300ms` → grouped results (products / stores / categories), keyboard navigable, recent + trending from `search_logs` when empty. |
| **Add to cart** | Optimistic: header cart count bumps + scale-pulses; toast "Added to cart" with View cart; server reconciles. Failure rolls back + danger toast with the reason. |
| **Mini-cart** | Right slide-over, grouped by seller, qty steppers live-update totals (`wire:model.live`), removal optimistic with Undo. |
| **Variant picker** | Option chips; selecting swaps price/stock/image in place. Unavailable combos disabled at 40%, never hidden. Selected chip: emerald outline + `emerald-tint`. |
| **Filters** | Sidebar (desktop) / bottom sheet (mobile). Instant apply, syncs to query string, applied filters as removable chips; results use `wire:loading` opacity + skeletons, layout never jumps. |
| **Listings** | "Load more" auto-triggered by intersection observer. |
| **Checkout** | Single page, seller-grouped. Any mutation disables Place order until totals settle. Voucher apply inline with 13px feedback. |
| **Order tracking** | Vertical timeline; events slide-fade in on first paint, current state pulses once. |
| **Dashboards** | Stat cards count up on first load (400ms, once); new-order rows enter with a brief `emerald-tint` highlight. Charts (ApexCharts) use solid fills, the warm palette, emerald for money. |

**Motion rules:** enter 150ms / exit 100ms, `ease-out`, distances ≤ 10px. Transform + opacity only. Page-load reveal via the `reveal` utility (staggered fade-up) on hero/heading groups. One orchestrated moment per page. `prefers-reduced-motion: reduce` kills transforms, reveals, shimmer, and the count-up.

---

## 8. Writing in the UI

- Buttons say what happens: **Add to cart · Buy now · Arrange shipment · Request payout**. The verb survives the flow: Place order → "Order placed".
- Errors state what happened and what to do: "This voucher needs a RM50 minimum — you're RM12 away." Never "Something went wrong."
- Numbers are honest and specific: "1.2k sold", "Only 3 left", "Free shipping over RM40".
- BM and EN are both first-class — short, concrete strings; never concatenate sentence fragments (breaks translation).

## 9. Quality Floor (non-negotiable)

44px minimum touch targets · visible `focus-visible` ring on every interactive element (2px emerald, 2px offset) · AA contrast on all text including inside the ink frame · alt text on product images (product name + variant) · reduced motion respected · CLS-safe (aspect-ratio boxes on all images, skeletons match real dimensions).

## 10. Don'ts

No emerald decoration · no brass on money/action controls · no carousel autoplay · no more than one emerald-filled CTA per region · no ALL-CAPS headings (except tiny badge caps) · no spinners where a skeleton fits · no layout shift on filter/sort · keep elevation soft and ornament subtle — depth and craft, never heavy shadows or busy pattern fields.
