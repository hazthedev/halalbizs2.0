# HB-SPEED-2: seller and admin hidden work

Queries per request, measured with the Pest harness in `tests/Feature/Speed2/HbSpeed2Test.php` (before = origin/main 8c2a468, empty DB; fewer queries is the point, not the absolute number).

| Screen | Before | After |
|---|---|---|
| Notification bell, poll tick | 2 queries + full render | 1 query, render skipped when nothing changed; polls only while the tab is visible |
| Seller messages, idle tick | 10 | 1 (render skipped) |
| Buyer messages, idle tick | 13 | 1 (render skipped) |
| Seller dashboard, period click | 16 (18 with orders) | 11 (13 with orders) |
| Seller dashboard, mount | 22 (24 with orders) | 17 (19 with orders) |
| Admin dashboard, period click / mount | 26 / 26 | 13 / 13 (warm all-time cache) |
| Buyer dashboard, period click | 11 | 9 |
| Seller product form, one option add | 5 | 2 |
| Payouts, tick a row / header box | 1 server trip | 0 (selection rides with the export call) |

What changed
- Polls: `wire:poll.visible` on the bell and both message threads. A tick compares a fingerprint (bell) or the newest message id (threads) and skips the render when nothing is new. A thread opens on its latest 100 messages (`Conversation::THREAD_WINDOW`).
- Dashboards: chart payloads are built once per request (dispatch and render shared them before). Revenue, GMV, buyers and spend are grouped in SQL by Malaysia day (`DisplayTime::dayBucketSql`, fixed +08:00 offset). Admin all-time blocks (status donut, top categories, top stores, M2 counts) are cached 3 minutes as plain arrays, per locale.
- Product form: category and brand pick-lists come from `CatalogOptions` (plain cached arrays, flushed when a Category or Brand is saved or deleted). Condition and attribute picks are Alpine/entangled, no trip per click.
- Payouts: selection is browser side (`x-model` + entangle).
- Admin pages and help articles list: `with('draft.media')` removes the media N+1.

Not done, on purpose
- Period click is not Renderless: the period buttons, earnings strip and the empty-chart placeholder are server rendered and must change with it.
- Variations toggle, option groups, bulk price and category cascade still need the server (matrix and category attributes are built in PHP).
- Seller orders 30 s poll: HB-TABLE-STD is rewriting that screen. Follow-up: poll only the count chips, `.visible`.
- Admin moderation and both certificates screens: HB-TABLE-STD part B.
- Admin brands and categories lists show every row, so their `withCount` is needed for the whole list.
- Index candidates (no migration made): `sub_orders (store_id, status, created_at)`, `orders (payment_status, paid_at)`, `messages (conversation_id, id)`.
