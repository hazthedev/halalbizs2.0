# Security lead validation — 16 November 2026

PR #222 (`d8e0a2f`) fixed the stale payment callback and retained six OPEN owner questions. Haze answered **“all default”** on 16 November; the rulings are HB-SEC-D1..D6 in the Hermes project decision log. All six protection tests are now active and pass. This is implementation and local regression evidence, not a new security audit or evidence of live exploitation. Independent review and release remain with the Codex gate and Hermes.

## Rulings and regression evidence

Tests: `tests/Feature/Security/AuditLeadValidationTest.php`. Each lead retains its original fingerprint. Every ruling was unskipped and failed before its implementation. Expanded cases add stale forms, file sessions, registration mismatches, prelinked certificates and allowed controls.

| Rule | Test fingerprint | RED before fix | GREEN behavior |
|---|---|---|---|
| D1 | `payment-expiry-late-success` | Expiry cancelled/restocked; verified late funds left the refund obligation at 0 instead of 20500 sen. | Records the full refund obligation once through `RefundService`; payment remains settled, fulfilment stays cancelled and stock stays restored. No `OrderPaid` event for fully cancelled fulfilment. No gateway refund call. Admin Payments → **Needs portal refund** shows the obligation for manual portal handling. |
| D2 | `seller-product-form-banned-status` | Draft, review and publish saves cleared Banned, including forms opened before the ban. | Form save locks and rereads the product, then rejects all saves of a banned product. Only admin moderation can lift the ban. EN/MS inline remedy. Six cases. |
| D3 | `seller-product-form-publish-halal-category` | A new listing and an ungated-to-gated edit became Live without a certificate. | Form applies the final category, checks the bound certificate through `ProductPublishPolicy` inside its transaction, and rejects publication before persistence. Existing enforcement date and 14-day review/renewal grace stay unchanged. No live category flags changed. |
| D4 | `password-reset-existing-web-sessions` | Two database browser sessions still returned 200 after broker recovery. | Both pre-existing browsers redirect to login after reset, tested with database and file sessions. API token and trusted-device revocation remain. Fresh login and legacy hashless-session controls also pass. |
| D5 | `staff-save-permissions-unbounded-grant` | A settings-only editor granted another admin `finance.manage`. | A non-superadmin edit requesting any permission outside the editor's direct grants is rejected in full, retaining the target's previous permissions. Held subsets and superadmin grants succeed. **QA9's contrary “legitimately granted” expectation was rewritten to this ruling.** |
| D6 | `whip-directory-profile-owner-binding` | Discovery hid a mismatched profile, but direct link approved its certificate and import created its product. | Shared local WHIP lookup treats profile/application owner disagreement as mismatch. Registration mismatch also fails verification. Direct link and existing-row import fail closed; manual upload remains Pending for admin review. Matching-owner link/import remains allowed. All WHIP data is fake in tests. |

The original `payment-callback-failure-overwrites-success` regression remains green: an overlapping signed failure cannot undo settlement, replace successful metadata, or repeat `OrderPaid`.

## Recovery session drivers and rollout

Database sessions are deleted immediately by `user_id`, using the configured session connection and table. Laravel's password-hash session middleware also rejects a surviving stale session on its next request, including a request that was in flight during deletion.

File, Redis, Memcached, DynamoDB and cookie sessions cannot be enumerated safely by user through the standard session interface. They instead lose authentication on their next web request when their stored password proof differs from the current password hash. Database and file integrations were exercised; the other backends share that middleware but were not integration-tested here. Array sessions have no persistence across normal requests.

A web `Login` event seeds password proof for every fresh login, including remembered login and completion of 2FA. Pre-upgrade authenticated sessions without that proof require a fresh login on first access. This deliberately causes a one-time re-login for legacy sessions across drivers; a reset cannot silently bless a legacy session with the new password hash. The remember token rotates on reset as before. No schema or live-row migration is needed.

## WHIP boundary

Only HalalBizs's read adapter and its consumers changed. No external WHIP files, tables, screens or data changed. The canonical company profile's owner must match the application owner, and its normalized registration must match the verified store. WHIP reads for seller link/import/submission and admin verification run before local transactions; the seller's final locked store registration is checked against that snapshot before link/import writes. Sync already reads WHIP before local locks.

The policy does not claim WHIP guarantees ownership consistency. It assumes disagreement is unverified and offers the existing document upload/manual review path. Likewise, D1 assumes iPay88 can settle late until the provider guarantees otherwise; no real provider calls were made.

## Browser evidence

MCP Playwright, one browser, disposable local SQLite database and fresh `e2e-<timestamp>` accounts only. Desktop 1280×800 and mobile 390×844:

- Seller ban save/publication refusal, stale form after a moderator ban, gated final category refusal, and a successful ungated publication.
- Admin permission overreach refusal, cancel/reopen, allowed subset save and the superadmin exception.
- Both changed screens fit mobile without horizontal overflow. Screenshots retained with the session's local evidence.

A real browser click also exposed the existing Staff action/property name collision: `editPermissions` resolved to an array in JavaScript, so the editor never opened. The action is now `startEditPermissions`; a rendered-button regression and real browser clicks cover it. All PHP callers were updated. An initial test-only method invocation could not detect this browser failure.

Observed local timings: seller form cold load 126 ms, successful publish 213 ms, reopen 114 ms; stale-ban refusal 205 ms; admin staff load 389 ms and rejected save 79 ms. Mobile ban refusal 262 ms included screenshot overhead. These are local observations, not production performance claims.

## Verification

Required targeted check, plus both touched admin certificate/alert consumers:

```sh
vendor/bin/pest tests/Feature/Security tests/Feature/Payments tests/Feature/Seller \
  tests/Feature/Admin/SystemTest.php tests/Feature/Admin/SuperadminTest.php \
  tests/Feature/Admin/AdminAlertScopingTest.php tests/Feature/Admin/CertificateReviewTest.php \
  tests/Feature/Qa/AdminPrivilegeTest.php tests/Feature/Whip tests/Unit/HardRulesTest.php
```

**307 passed, 1,221 assertions, zero skips, 20.02 seconds.** Supplemental Returns/Certificates/WHIP/lead/admin-certificate coverage: **141 passed, 632 assertions**. Authentication and API authentication coverage: **116 passed, 657 assertions**. Counts overlap and must not be added.

Pest uses in-memory SQLite and fake gateway/WHIP boundaries. Race evidence uses deterministic interleavings and row-lock guards, not a concurrent MariaDB stress run. No full suite per the brief. Pint and full configured Larastan level 1 pass; analysis runs in one process at 512 MiB. No new dependencies, migrations, deployments, merges or live writes.

Local evidence and red/green logs: `/tmp/hb-rules-evidence`; session record: `/Users/Apple/Desktop/Hermes/main/sessions/hb-rules.md`. Setup errors were kept separate from assertion evidence: the additional WHIP fixture initially requested a nonexistent factory, and changing the local test encryption key during one active run caused MAC errors. Stable-configuration reruns passed. The initial admin browser selector timeout was traced to the actual name collision and fixed.

One thing better: the staff permission editor now opens from a real click and enforces the same grant boundary as invitations.
