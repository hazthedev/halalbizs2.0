# Storefront speed (HB-SPEED-1)

## Part A

Home public sections and footer links use the existing HomeLayout cache (five minutes, locale-specific). Content saves, scheduled layouts and theme changes flush them; staff draft previews bypass them. Scheduled switches cap the home TTL. Product/stock displays follow the existing app-home TTL; cart actions always validate live variants. Personal recommendations, wishlist, cart and recently viewed history are never shared through this cache.

Card add/heart and recent-history hydration return without redrawing their parent. Hearts update optimistically and reconcile with the server result. The mini-cart reads personal contents only when opened, then refreshes through cart events. Listing filters use one responsive form, one filtered query, cached states (five minutes), search IDs (30 seconds per locale/mode/term) and sponsored products (30 seconds per scope, invalidated by content/boost changes). The wishlist page still refreshes its grid after removal.

Measured on MariaDB `hbiz2_t4`, same fixtures on main `42e37e9` and this branch, using `StorefrontSpeedATest` query logging. Warm means initial visit completed before the measured visit/action. Counts include all SQL executed by that component request; layout is a full HTTP visit. Setup SQL is excluded.

| Request/action | Main | Part A |
| --- | ---: | ---: |
| Home, warm component | 15 | 2 |
| Card heart | 12 | 4 |
| Card add | 11 | 4 |
| Three-level category descendants, cold | 3 | 1 |
| Category filter change | 13 | 9 |
| Recent-history hydration | 19 | 6 |
| Mini-cart initial component | 7 | 0 |
| Warm full layout, empty home | 5 | 2 |
| Search filter change | 9 | 7 |

The speed tests fail on main and pass here; card action tests also assert that the response has no HTML redraw. Existing recently-viewed coverage now checks returned HTML because Alpine inserts just that strip. No packages, migrations, search-engine changes or deployment.

Cached models (home sections, footer, sponsored set, flash sale) go through `HomeLayout::remember`: stored encrypted, because `cache.serializable_classes` is off and a plain cache of Eloquent objects would come back broken. A cache entry that cannot be read is rebuilt.

## Part B

PDP: variant pick, option availability, qty stepper, price, stock note and the notify button run in the browser from one per-variant data block. The server is called only for add to cart, buy now (both send variant id + qty; the variant must belong to the product) and notify (renderless). The product view write runs after the response. Cart: the over-stock clamp is written once on load, never while rendering; a line over stock in between shows the clamped qty. Checkout: one addresses query per render. Flash sale: one shared read per 30 s, poll only while visible. Live room poll only while visible.

| Request/action | Before | After |
| --- | ---: | ---: |
| PDP variant pick or qty step | 1 request, 14 queries | 0 requests |
| PDP add to cart / buy now | re-render | no re-render |
| Flash sale poll (cold / warm) | 7 | 7 / 0 |
| Checkout addresses queries per render | 2 | 1 |
| Cart render writes | 1 per over-stock line | 0 |

Not done: checkout quote reuse on voucher apply (money path, left alone); checkout toggles and place-order spinner come from PR #231; index candidates: none needed.
