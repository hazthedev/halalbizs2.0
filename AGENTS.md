## Hard rules (violations = rejected PR)

1. **Money is integers in sen.** Columns end `_sen`. Arithmetic via brick/money or plain int. Floats are banned for money — in PHP, JS, and Blade.
2. **Sub-order status never changes by assignment.** Only `SubOrderStatusService::transition()` — it validates the transition, writes `order_status_histories`, fires events.
3. **Stock and voucher mutations are atomic.** `lockForUpdate()` on variants and vouchers inside the checkout transaction. Validate + consume in the same lock.
4. **iPay88 fulfilment trusts only the BackendURL callback + a successful requery.** The browser ResponseURL is UX-only. Callbacks are idempotent.
5. **Snapshots are sacred.** `order_items` carry name/variant/price at purchase; `orders` carry the address json; `sub_orders` carry commission_rate. Never read live product data for historical orders.
6. **Every product has ≥1 variant.** No-variation products get a default variant. Cart/checkout/order code only touches variants.
7. **Translatable writes include `en` at minimum** (fallback locale). UI strings go in `lang/` files, never hardcoded.
8. **Emerald = money/action; brass = premium/ornament.** "Souk (Emerald & Brass)" design language per `marketplace-docs/docs/03-design-system.md`: warm ivory canvas, Fraunces + Plus Jakarta Sans, soft elevation (`shadow-soft/card/pop`), and Islamic geometric ornament (khatam/girih) are sanctioned. Never recolour money/action elements brass, or decorate with emerald.
9. **Tests accompany features.** Checkout, vouchers, ledger, status transitions, and iPay88 callback paths require Pest coverage including the race-condition cases.
10. **No new packages** without noting why in the PR description.

Master copy: `/Users/Apple/Desktop/Hermes/projects/halal-ecosystem/hard-rules.md`.

1. **Main is live.** Never push to main. One PR per change; Hermes reviews, merges and deploys.
2. **Deploy only through the repo's `bin/ship`** (HalalBizs: Hermes's deploy step). Never while the shared server's 1-minute load is above 13. Every server command gets a line in Hermes's ledger (`main/records/server-command-ledger.md`). [bin/ship enforces the load gate]
3. **No live data writes** (a live database, a real user's account, a live customer's record) without the owner's written OK. Never run migrations, seeds, tinker or SQL against a live database by hand. Silence means nothing is written.
4. **No secrets anywhere visible.** Never print, commit or log a password, token or key; never put one in a URL or chat. Read `.env` by key name only. Deploy tokens live in `~/.hermes-secrets`. [TEST: no `env()` outside `config/`]
5. **Commit explicit file paths only.** Never `git add -A`, `git add .`, a directory or a glob; never `commit -a`, `stash`, `reset --hard` or a force push.
6. **Bugs are fixed test-first:** a red test that shows the bug, then the fix. Run targeted Pest per change. Full suite once before a PR, and never while ATP release gates are waiting (the Mac is shared).
7. **End-to-end tests run locally or on a demo site** with fresh `e2e-<timestamp>` accounts, cleaned up afterwards. Never on a site with real customers (HalalBizs: local only).
8. **The builder never audits its own work.** Security audits are source-only and run by a separate session.
9. **No crontab edits.** Schedules go in `routes/console.php`.
10. **No new package, paid service or external API** without the owner's OK, written in the PR. Demo sites use fake gateways guarded by `config('app.demo')`.
11. **Rate limiters are defined in `AppServiceProvider`, never in a routes file**: production caches routes, so a limiter in a routes file never runs (SertuCert live /verify 500, fixed #19). [TEST]
12. **No debug leftovers in app code** (`dd`, `dump`, `ray`, `var_dump`). [TEST]
13. **A changed screen or feature updates its docs in the same PR** (README, coverage notes, feature map).
14. **This Mac has 8 GB:** at most 2 subagents, one browser at a time, kill what you start by pid, background commands get `</dev/null`.
15. **Settled decisions are not re-argued.** Read `projects/<slug>/<slug>-decisions.md` in Hermes before changing behaviour; a change to a ruling needs Haze's word.

- Keep the 10 existing hard rules in `CLAUDE.md` (money in sen, status via service, atomic stock, iPay88 callback, snapshots, variants, translations, design colours, tests, packages).
- **WHIP is read-only:** SELECT only, through the `whip` connection; no WHIP file, table or screen changes. Tests fake WHIP; the real `whip` connection is never used in tests. [TEST: no write call on the `whip` connection in `app/`]
- **halalbizs.com and the old preview share ONE database:** a write on the preview is a write on live.
- WHIP calls run outside database transactions; locks stay short (#215).
