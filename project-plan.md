# Project Plan — Audit Remediation (HALALBIZS-AUDIT-2026-07-27)

> **2026-07-27 ~17:20 — adopted by a second session.** The original orchestrating
> session stalled ~16:43 with all work uncommitted and this file's checkboxes
> never updated. The successor banked the tree on `fix/audit-findings-2026-07-27`,
> rebased onto origin/main (#51–#53 residue deduped), verified every wave against
> the criteria below, and finished the remainder. States below are as verified,
> not as originally left.

**Goal**: fix every finding in `docs/audit/HALALBIZS-AUDIT-2026-07-27.md` — 2 CRITICAL, 4 HIGH, ~22 MEDIUM, ~19 LOW — then verify.
**Base**: `29ee7d1` (main). **Started**: 2026-07-27. **Orchestrator**: Hermes (main loop = planner/advisor/verifier).

## Execution model
Shared tree, **strictly disjoint file surfaces per task** (no worktrees: `/vendor` is gitignored so each worktree would need its own `composer install`; tests use `DB_DATABASE=:memory:` so parallel test runs cannot collide). Executors = `sonnet`, one per task. Maker ≠ checker: I verify centrally with the full suite.

## Decision Log constraints
`D-001`–`D-003` cover the landing page only — **nothing constrains this security work**.

## Wave 1 — parallel (T1–T4)

- [x] **T1 — XSS output + upload hardening** (C1, C2, AL-M2, AL-C17, AL-L6)
  Surface: `resources/views/livewire/storefront/product-detail.blade.php`, `app/Livewire/Seller/Products/Form.php`, new `app/Support/HtmlSanitizer.php`, `app/Livewire/Storefront/Account/ReviewOrder.php`, `app/Livewire/Storefront/Account/OrderDetail.php`, `config/livewire.php`, new `tests/Feature/Security/XssOutputTest.php`
  Accept: a product named `</script><script>alert(1)</script>` is inert in the PDP; `<em onmouseover=x>` does not survive save; uploads cannot keep a client `.html` name; tests prove each and fail when reverted.

- [x] **T2 — Settlement layer atomicity** (H1, H2, H3, M1)
  Surface: `app/Services/SubOrderStatusService.php`, `app/Services/RefundService.php`, `app/Listeners/RecordLedgerOnCompletion.php`, `app/Services/LedgerService.php`, new migration (ledger settlement dedupe), `tests/Feature/Stress/RefundLedgerStressTest.php`, `tests/Feature/Stress/PaymentFsmStockStressTest.php`
  Accept: refund cap recomputed under `lockForUpdate` inside the txn; `transition()` locks + re-reads status and wraps status+history+dispatch in one txn; a ledger throw rolls the status back; DB-level dedupe backstop that does NOT constrain legitimately-repeating entry types.

- [x] **T3 — Config / middleware / infra** *(successor: Turnstile fail-closed override moved from a raw `env()` call — invisible under `config:cache` — to `config/services.php`; ⚠ `trustProxies at:'*'` ships with a needs-verification note on the cPanel topology)* (AL-C1, C2, C4, C6, C10, C11, C12, C13, C14, C15, C16)
  Surface: `bootstrap/app.php`, `app/Http/Middleware/SecurityHeaders.php`, `app/Http/Middleware/SetDisplayCurrency.php`, `config/app.php`, `config/session.php`, `.env.example`, `app/Http/Controllers/AffiliateReferralController.php`, `app/Http/Controllers/EasyParcelWebhookController.php`, `app/Http/Controllers/Ipay88Controller.php`, `routes/web.php`, `tests/Feature/SecurityHeadersTest.php`
  Accept: `//evil.com` rejected by the affiliate redirect; security headers present on 404 + 419; throttles on the unauthenticated write/compute routes; no dev-hostname fallback.

- [x] **T4 — Auth + admin authz + notifications** *(successor: GoogleAuthTest mock now fakes the raw `email_verified` claim + a refusal-path regression test)* (AL-C3, AL-C7, AL-C8, AL-C9, AL-L5, M8)
  Surface: `app/Livewire/Storefront/Auth/Register.php`, `app/Livewire/Storefront/Auth/Login.php`, `app/Livewire/Storefront/Auth/TwoFactorChallenge.php`, `app/Http/Controllers/GoogleAuthController.php`, `app/Services/Turnstile.php`, `app/Observers/AdminAlertObserver.php`, `app/Livewire/Admin/System/Staff.php`, `tests/Feature/Auth/TwoFactorTest.php`, new `tests/Feature/Admin/AdminAlertScopingTest.php`
  Accept: registration rate-limited; Turnstile fails closed outside local; per-IP + per-email login limiters; TOTP code not replayable; finance alerts only reach admins holding the permission; an admin cannot grant themselves permissions.

## Wave 2 — parallel (T5–T7)

- [x] **T5 — Payments / jobs / webhooks** *(successor: X-Webhook-Id now stable across queue retries instead of a fresh uuid per attempt)* (M2, M3, M4, M5)
  Surface: `app/Jobs/ConfirmIpay88PaymentJob.php`, `app/Jobs/SendWebhookJob.php`, `app/Services/WebhookDispatcher.php`, `app/Models/WebhookSubscription.php`, `app/Services/Ipay88Service.php`, `tests/Feature/Payments/Ipay88Test.php`
  Accept: confirm job locks the payment row; retry/backoff/timeout on requery + webhook POST; a failed webhook can be redelivered; SSRF-unsafe URLs rejected.

- [x] **T6 — Storefront Livewire hardening** *(AL-M6's Profile.php cap had already shipped as #51 — deduped by the rebase)* (H4/AL-M1, AL-M5, AL-M6, AL-L3, AL-L4, L2)
  Surface: `app/Livewire/Storefront/ShopAssistant.php`, `ProductReviews.php`, `ProductQuestions.php`, `Listing.php`, `StorePage.php`, `RecommendedProducts.php`, `Account/Messages.php`, `Account/Profile.php`, new `tests/Feature/Security/LivewireHardeningTest.php`
  Accept: assistant rate-limited + history capped; every pagination prop `#[Locked]` and clamped; SMS daily cap; `markHelpful` atomic; chat context product scoped.

- [~] **T7 — Checkout, group-buy, DB indexes** *(all done EXCEPT L3 `$fillable` privilege columns — parked: factories/seeders mass-assign those columns, so the change ripples beyond a safe same-day diff; LOW severity, protected today by explicit-array call sites)* (AL-M3, AL-M4, AL-L1, AL-L2, M6, M7, L6, L3)
  Surface: `app/Livewire/Storefront/Checkout.php`, `app/Services/CheckoutService.php`, `app/Services/GroupBuyService.php`, `app/Models/Store.php`, `app/Models/User.php`, new migrations (products composites, group_buy_teams index, voucher platform-code uniqueness), `tests/Feature/Stress/AuthzIdorStressTest.php` or matching existing checkout tests
  Accept: group-buy price dies with the campaign window; duplicate submit cannot create two orders; one code cannot burn quota twice; privilege columns out of `$fillable`; indexes present.

## Main-loop tasks (not delegated — risk of lockout / needs judgement)
- [x] **AL-C5 — admin route `verified`**: verified Staff::sendInvite() marks verified on create; existing admin rows backfilled by migration `2026_07_27_000010`; both directions covered by `tests/Feature/Admin/AdminVerifiedGateTest.php`.
- [~] **M10** — SKIPPED: negative-on-GROSS is by-design (the panel copy already warns), and the debt state is already surfaced in red in the seller UI. An admin alert would fire on every routine COD completion.

## Cutover note (deploy gate)
`TURNSTILE_ALLOW_UNCONFIGURED=true` must be in the **server `.env` BEFORE this
deploys** — Turnstile now fails closed outside local/testing, so without the
flag every preview login/registration breaks the moment the deploy lands.

## Verify
Full `php artisan test` (PowerShell) + Pint + three-dot diff review, then `ship`.
