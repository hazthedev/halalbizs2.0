# Security lead validation — 16 November 2026

Base: `a3a04a5`. Result: **0 CLOSED / 1 FIXED / 6 OPEN**. This is local regression work on the seven existing audit leads, not a new security audit or a claim about live exploitation. Release is PR-only, pending an independent Codex gate and Hermes review.

## Payment callback change

A failure callback can load Pending, pause while confirmation settles, then overwrite Success using its stale model. The BackendURL handler now locks and rereads the payment before changing callback metadata or status. It preserves terminal Success. Confirmation dispatch and its gateway requery run after this short transaction. Signatures and successful requery remain required for fulfilment.

The regression commits a real confirmation job while a correctly signed failure callback is paused at signature verification. It checks terminal Success, successful callback metadata, unchanged paid timestamps, one confirmation history, and one `OrderPaid` event after another success notification. This is a deterministic interleaving on SQLite, not a concurrent MariaDB stress run or proof of provider delivery ordering.

## Lead evidence

Tests are in `tests/Feature/Security/AuditLeadValidationTest.php`. Each test name starts with the exact lead fingerprint below. RED means the first valid scenario reached and failed its proposed protection assertion; setup failures are described below.

| Lead fingerprint / test-name prefix | Result | First valid result and observed behavior |
|---|---|---|
| `payment-callback-failure-overwrites-success` | FIXED | RED: expected Success, got Failed after overlapping settlement. GREEN after the row-lock fix. |
| `payment-expiry-late-success` | OPEN | RED: expiry cancelled/restocked; a later fake signed success and `00` requery settled funds while the cancelled suborder's `refunded_sen` stayed 0 instead of 20500. |
| `seller-product-form-banned-status` | OPEN | RED: draft saves changed Banned to Draft with approval on/off; publication changed it to PendingReview or Live. Four cases. |
| `seller-product-form-publish-halal-category` | OPEN | RED: both a new listing and an ungated-to-gated edit persisted Live without a certificate, with enforcement active and approval disabled. |
| `password-reset-existing-web-sessions` | OPEN | RED: two independent database browser sessions both returned 200 from `account.orders` after successful broker recovery; proposed result was 302 for both. |
| `staff-save-permissions-unbounded-grant` | OPEN | RED against the proposed subset policy: another admin gained `finance.manage`; the settings-only editor did not. Existing QA9 calls this grant legitimate, so authorization policy remains unresolved. |
| `whip-directory-profile-owner-binding` | OPEN | RED in fake data: discovery omitted the mismatched profile, but direct linking approved its certificate and import created its product. Matching-owner discovery/link/import passed as a control. This establishes neither real WHIP data nor company ownership. |

Initial execution issues: sandbox loader access and an incorrectly sized dummy encryption key preceded the payment assertion; the first expiry fake accidentally returned success before expiry; publication initially hit the subprocess cap for image conversion; the first browser-session fixture used PHP serialization instead of the configured JSON. These were corrected before drawing the above conclusions. Media conversion jobs alone are faked in the new tests.

The six OPEN tests retain the proposed protection assertions and have explicit `skip` reasons pending decisions. They are **not green closure evidence**. Their datasets account for ten skipped test cases. After approval, remove the relevant skip and rerun by fingerprint before implementing the agreed fix; do not change assertions to accept the observed gap.

## Exact questions and recommended defaults

1. **Late funds — iPay88/provider owner:** Can one reference return non-success at final expiry requery, later settle, deliver a signed success callback and requery `00`, without automatic reversal? **Default:** assume yes until the provider guarantees otherwise. **Haze:** Should verified late funds for cancelled fulfilment create an idempotent full refund/reconciliation obligation while cancellation and restored stock remain? **Default:** yes; do not revive cancelled fulfilment. The test expresses the refund default; the final accounting treatment requires approval.
2. **Moderator ban — Haze:** Must a moderator ban survive every seller save, including a form opened before the ban? **Default:** yes; reject seller saves/transitions out of Banned and require an admin to lift it.
3. **Final category — Haze:** May the seller form reject publication based on the final submitted category and certificate inside its transaction? **Default:** yes; use the existing publication policy, enforcement date and 14-day grace. Do not move the rollout date or inspect/change live category flags.
4. **Recovery sessions — deployment owner/Haze:** Which `SESSION_DRIVER` is deployed (name only), and should recovery revoke every pre-existing authenticated browser session across supported drivers? **Default:** yes; cover database and supported alternative drivers while keeping API-token/trusted-device revocation. The current fixture proves the database case only.
5. **Permission delegation — Haze:** May a non-superadmin `settings.manage` editor grant another admin permissions the editor does not hold? **Default:** no; apply the invitation subset rule to edits, preserving the superadmin exception. This requires resolving the contrary QA9 expectation first.
6. **WHIP ownership — WHIP owner:** Can the lowest-ID application profile have another `user_id`, and can its registration belong to a different company from the application/certificate owner? Which enforced schema constraints/write paths guarantee the association for historical and new rows? **Default:** treat disagreement as unverified and require manual review; do not auto-approve/import. No WHIP implementation or screen change is included.

These are held under the brief's STOP rule for seller/buyer behavior changes or missing owner/provider facts. Defaults are recommendations, not approval.

## Verification

Required command, against a dummy testing environment only:

```sh
php vendor/bin/pest tests/Feature/Security tests/Feature/Payments tests/Unit/HardRulesTest.php --compact
```

Local execution uses an empty allowlisted environment, dummy app key, in-memory SQLite, fake iPay88 responses and a wholly replaced in-memory WHIP connection. OS sandbox rules deny network access and home access outside the worktree/toolchain, deny reading the copied `.env`, and restrict writes to local test scratch. PHP memory is capped at 512 MiB, CPU at 180 seconds, files at 64 MiB each, open files at 256, and process headroom at 32 above the shared user's current process count. No live database, real WHIP/iPay88, browser, deployment, merge, migration of an existing database, package installation, or full-suite run is part of this work.

Required verification: **44 passed, 10 explicitly skipped, 204 assertions** (5.28 seconds). This includes all four `HardRulesTest` cases. The ten skips are the six OPEN leads' datasets; no additional test was skipped in this command.

Additional callback callers: **53 passed, 260 assertions** (4.57 seconds) across `CheckoutPageTest`, `CheckoutApiTest`, `AuthorizationMatrixTest`, and `QueueWorkerCoversEveryQueueTest`, excluding only `sweeps every GET route against every persona and writes the matrix`. That pre-existing test tried writing `/Users/Apple/Desktop/Hermes/projects/halalbizs2.0/qa-2026-08-06/AUTHORIZATION-MATRIX.md`; the sandbox blocked the out-of-scope write. The first additional run therefore had 53 pass / 1 sandbox failure; the bounded rerun passed. No outside report was written.

Pint: both changed PHP files pass. Larastan level 1: the entire configured `app/` path passes with no errors, in one process with a 512 MiB limit. `git diff --check` passes. The brief explicitly excludes the full suite.

One thing improved: a stale signed failure callback can no longer undo terminal settlement or cause a later success to repeat `OrderPaid`.
