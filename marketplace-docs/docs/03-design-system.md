# Design System — "Ink & Emerald"

**Brief (locked):** simple and modern · interactive and reactive · off-black + off-white base · emerald/dark-green accent.
**Applies to:** storefront first; seller and admin panels inherit the same tokens with denser spacing.
**Stack:** Tailwind CSS v4 (`@theme` tokens) · Livewire 4 · Alpine.js · `wire:navigate` throughout.

---

## 1. Direction

A marketplace people trust with money should feel **precise, calm, and fast** — the opposite of Shopee's carnival orange. We keep Shopee's *information architecture* (dense product grids, order tabs, seller storefronts) but render it like a well-set ledger:

- **The ink frame.** Off-black anchors the page at its edges — header, footer, toasts, the mobile sticky buy bar — framing an off-white canvas where products live on white cards. Dark frame, light canvas. This is the layout signature.
- **Emerald means money or action. Nothing else.** Every emerald pixel is a CTA, a price advantage (discount, savings, free shipping), or a trust mark (verified seller, order completed). Emerald is never decoration — no emerald section backgrounds, no emerald headings, no emerald icons for neutral things. This discipline is what makes the accent feel meaningful instead of themed.
- **Flat + outlined, not shadowed.** Cards sit flat with 1px `line` borders; hover/selection/focus are expressed as outlines (ink or emerald), not drop shadows. Shadows exist only on true overlays (drawers, modals, toasts). Flat surfaces + crisp outlines read modern and render fast.

---

## 2. Color Tokens

```css
/* resources/css/app.css */
@theme {
  /* canvas + ink */
  --color-paper:        #F7F7F4;  /* off-white page background */
  --color-surface:      #FFFFFF;  /* cards, inputs, sheets */
  --color-ink:          #191B1A;  /* off-black: frame surfaces + primary text */
  --color-ink-soft:     #5B615D;  /* secondary text */
  --color-ink-faint:    #8B918D;  /* placeholders, disabled text */
  --color-line:         #E5E7E2;  /* borders, dividers, skeletons */
  --color-line-strong:  #C9CEC9;  /* input borders, hover lines */

  /* emerald = money & action */
  --color-emerald:        #047857;  /* primary actions, links, savings */
  --color-emerald-deep:   #065F46;  /* hover */
  --color-emerald-night:  #03392B;  /* pressed; dark-green moments inside the ink frame */
  --color-emerald-tint:   #EAF4EF;  /* badge/selected-state backgrounds */

  /* semantic (used sparingly) */
  --color-warn:    #B45309;  /* pending payment, low stock */
  --color-danger:  #BE123C;  /* destructive, errors, cancelled */
  --color-warn-tint:   #FBF3E8;
  --color-danger-tint: #FBEDF0;
}
```

Usage rules:
- Text on `paper`/`surface`: `ink`; secondary `ink-soft`. Text inside the ink frame: `#F7F7F4` at 100%, secondary at 64% opacity.
- White text on `emerald` (#047857) passes WCAG AA — never put white text on lighter emerald fills.
- **Prices are `ink`, bold.** A price is information; only the *advantage* is emerald: `RM 89.00` in ink, `-30%` and `Save RM 38` in emerald, struck original price in `ink-faint`.
- One emerald-filled CTA per view region. Competing actions are ink-outline or ghost.
- Dark mode: not v1. The ink frame already gives the brand its dark register.

---

## 3. Typography

| Role | Face | Weights | Used for |
|---|---|---|---|
| Display | **Bricolage Grotesque** | 600–800 | Page titles, section headers, hero lines, big numbers (dashboard stats). Use with restraint — never below 20px. |
| Body / UI | **Figtree** | 400 / 500 / 600 | Everything else. Product titles 500, prices 700 with `font-feature-settings: "tnum"`. |
| Utility mono | **JetBrains Mono** | 500 | Machine identifiers only: order numbers, SKUs, tracking numbers, voucher codes. Renders "system truth" — a `MP2606A1B2C3` in mono reads as a receipt, not a sentence. |

```css
@theme {
  --font-display: "Bricolage Grotesque", "Figtree", ui-sans-serif, system-ui, sans-serif;
  --font-sans: "Figtree", ui-sans-serif, system-ui, "Noto Sans SC", sans-serif;
  --font-mono: "JetBrains Mono", ui-monospace, "SFMono-Regular", monospace;
}
```

- Self-host via `@fontsource` packages (no Google Fonts CDN — performance + PDPA-friendly).
- The `Noto Sans SC` fallback keeps the future `zh` locale from falling to ugly defaults; revisit pairing when zh ships.
- Scale: 12 · 13 · 14 (UI base) · 16 (long-form) · 18 · 22 · 28 · 36 · 48. Line-height 1.5 body, 1.15 display. Sentence case everywhere — no ALL-CAPS labels except tiny badge text (11px, +0.04em tracking).

---

## 4. Shape, Space, Layout

- Spacing on a 4px grid. Storefront breathes (sections `py-12/16`); seller/admin run denser (`py-6/8`).
- Radius: cards & images `10px` · controls (buttons/inputs) `8px` · badges/chips full pill. Slightly tighter than Tailwind defaults — precise, not bubbly.
- Container `max-w-7xl`. Product grids: 2 cols mobile → 3 sm → 4 lg → 6 xl, `gap-3`/`gap-4`.
- Borders 1px `line`; section dividers are hairlines, never gray bands.
- Product card anatomy: image (1:1, `object-cover`, paper background while loading) → title (2-line clamp, 13–14px/500) → price row (ink bold + emerald `-x%` chip) → meta row 12px `ink-soft`: ★ 4.8 · 1.2k sold · state. Hover: border → `ink`, image scales 1.02 (150ms). No shadow.

---

## 5. The Ink Frame (key surfaces)

- **Header (ink):** logo in off-white, prominent center search field (`surface` input set into the ink bar), right side cart + account. A 1px `emerald-night` keyline at the header's bottom edge is the only decorative accent permitted.
- **Footer (ink):** off-white text, emerald only on the newsletter submit and payment/trust marks.
- **Mobile sticky buy bar (ink):** PDP bottom bar — chat icon, Add to cart (ink-outline on ink: off-white 1px outline), Buy now (emerald fill).
- **Toasts (ink):** off-white text, emerald check icon, bottom-right desktop / bottom-center mobile.
- Drawers, modals, and menus stay light (`surface`) — the frame is dark, the work is done in the light.

---

## 6. Components (token-level spec)

- **Buttons** — primary: emerald fill, white text, hover `emerald-deep`, pressed `emerald-night`; secondary: 1px ink outline, ink text, hover `paper` fill; ghost: ink-soft text, hover ink; destructive: danger outline (fill only in confirm dialogs). Loading: spinner replaces icon, label keeps its verb, `wire:loading.attr="disabled"`.
- **Inputs** — `surface` bg, 1px `line-strong` border, focus: 2px emerald ring (`focus-visible` only), error: danger border + 13px danger help text that says how to fix it. Labels 13px/500 ink.
- **Quantity stepper** — outlined pill, − / value / +, mono value.
- **Badges** — Sale/discount: `emerald-tint` bg + emerald text · Free shipping: emerald outline · COD: ink outline · Verified seller: emerald fill + check · Out of stock: `line` bg + ink-faint. 11px caps.
- **Order-status tabs** — Shopee-style horizontal scroll tabs; active = ink text + 2px ink underline; count chips `emerald-tint`. Status colors: pending → warn · shipped/processing → ink · completed → emerald · cancelled/refund → danger.
- **Tables (seller/admin)** — hairline rows, 13px, mono for IDs/amount columns, right-aligned tabular numbers, sticky header, row hover `paper`.
- **Skeletons** — `line` blocks with a subtle shimmer; match real layout (image square + two text lines).
- **Empty states** — one Bricolage line + one sentence + a single emerald action. "No orders to ship. New orders appear here the moment a buyer pays."

---

## 7. Reactive Behavior (the "interactive, reactive" brief)

`wire:navigate` on all internal links — the whole storefront feels SPA-instant with zero SPA complexity.

| Pattern | Spec |
|---|---|
| **Search overlay** | Header search opens a command-palette-style overlay (also `/` shortcut). `wire:model.live.debounce.300ms` → grouped results (products / stores / categories), keyboard navigable, recent + trending terms from `search_logs` when empty. |
| **Add to cart** | Optimistic: header cart count bumps + scale-pulses immediately; toast "Added to cart" with View cart action; server reconciles. Failure rolls back count + danger toast with the reason. |
| **Mini-cart** | Right slide-over (320ms ease-out), grouped by seller, qty steppers live-update totals (`wire:model.live`), line removal is optimistic with Undo in the toast. |
| **Variant picker** | Option chips; selecting swaps price/stock/image in place. Unavailable combos rendered disabled at 40%, never hidden. Selected chip: emerald outline + `emerald-tint`. |
| **Filters** | Sidebar (desktop) / bottom sheet (mobile). Every change applies instantly, syncs to the query string (shareable URLs), applied filters render as removable chips above the grid; results area uses `wire:loading` opacity-60 + skeleton rows, layout never jumps. |
| **Listings** | "Load more" button auto-triggered by intersection observer = infinite scroll with a real fallback. |
| **Checkout** | Single page, seller-grouped. Any mutation (address, shipping option, voucher, payment method) disables Place order until totals settle (guardrail from the Bagisto race-condition lesson). Voucher apply inline with success/fail feedback in 13px. |
| **Order tracking** | Vertical timeline; status events slide-fade in on first paint, current state pulses once. |
| **Seller dashboard** | Stat cards count up on first load (400ms, once); new-order rows enter with a brief `emerald-tint` highlight that fades. |

**Motion rules:** enter 150ms / exit 100ms, `ease-out`, distances ≤ 8px. Transform + opacity only. One orchestrated moment per page (search overlay, drawer, or timeline) — everything else is instant. `prefers-reduced-motion: reduce` kills transforms and the count-up (values render final).

---

## 8. Writing in the UI

- Buttons say what happens: **Add to cart · Buy now · Arrange shipment · Request payout**. The verb survives the flow: Place order → "Order placed".
- Errors state what happened and what to do: "This voucher needs a RM50 minimum — you're RM12 away." Never "Something went wrong."
- Numbers are honest and specific: "1.2k sold", "Only 3 left", "Free shipping over RM40".
- BM and EN are both first-class — keep strings short and concrete so neither locale wraps awkwardly; never concatenate sentence fragments (breaks translation).

## 9. Quality Floor (non-negotiable)

44px minimum touch targets · visible `focus-visible` ring on every interactive element (2px emerald, 2px offset) · AA contrast on all text including inside the ink frame · alt text on product images (product name + variant) · reduced motion respected · CLS-safe (aspect-ratio boxes on all images, skeletons match real dimensions).

## 10. Don'ts

No gradients · no drop shadows outside overlays · no emerald decoration · no carousel autoplay · no more than one emerald-filled CTA per region · no ALL-CAPS headings · no spinners where a skeleton fits · no layout shift on filter/sort.
