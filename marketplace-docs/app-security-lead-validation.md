# App security lead validation — 17 November 2026

Source: the 17 November mobile app and mobile API source audit (16 needs-validation leads, `c69b573`). Each lead below ends as confirmed and fixed (red test first, then green), owner ruling, or owner question. Tests run locally against SQLite and fakes only; no live host was called. This is a builder's validation, not a new audit: independent review stays with the Codex gate and Hermes.

## Results

| # | Lead (fingerprint) | Verdict | Fix | Test |
|---|---|---|---|---|
| 1 | `google-signin-links-preregistered-account-keeps-api-token` | Confirmed, fixed | Google sign-in that links onto a never-verified account rotates the password, clears 2FA, and revokes API tokens, push registrations, trusted devices and database sessions (`User::revokeAppAccess()`). A verified account keeps its sign-ins. | `tests/Feature/Auth/GoogleAuthTest.php` (2 new) |
| 2 | `app-web-sign-in:no-initiating-browser-binding` | Confirmed, fixed | Opening the one-time link shows "Continue as <name>" and signs nobody in; the CSRF POST to the same signed URL consumes the nonce. A forwarded link or a chat link preview no longer signs a browser in. Adds one tap in the app hand-off. | `tests/Feature/Api/WebSignInLinkApiTest.php` (1 new, 3 moved to POST) |
| 3 | `seller-api-delivered-endpoint-no-shipped-guard-voids-open-return` | Confirmed, fixed | `OrderService::markDelivered` refuses unless the locked sub-order is shipped (web, AfterShip and EasyParcel already checked). The FSM edge return_requested to delivered stays for admin side-with-seller. | `tests/Feature/Api/SellerOrdersApiTest.php` |
| 4 | `api-return-photos-on-public-media-disk` | Confirmed, fixed (name) | Return photos get a 40-character random file name, so `/storage/{id}/{name}` cannot be guessed. Existing files keep their names (no live data rewrite). A private disk with signed URLs is the stronger follow-up if wanted. | `tests/Feature/Api/OrdersApiTest.php`, `tests/Feature/Returns/ReturnServiceTest.php` |
| 5 | `hb-app.account-switch.kept-providers-not-dropped` | Confirmed, fixed | `orderQuickStatusProvider` and `storeProvider` watch the profile id, so the next account re-reads its own counts and follow state. | `mobile/test/features/account_switch_test.dart` |
| 6 | `api-cart-add-hidden-variant-stock-oracle` | Confirmed, fixed | The "Only N left" check runs only for live products; a hidden variant answers "not available". | `tests/Feature/Api/CartApiTest.php` |
| 7 | `api-order-action-status-gate-weaker-than-web` | Confirmed, fixed | API buyer cancel is Confirmed only, like the web, checked on the locked row. | `tests/Feature/Api/OrdersApiTest.php` |
| 8 | `api.search.unbounded-id-list-and-result-set` | Confirmed, fixed | `/search` pages 24 at a time like `/products`, `q` max 100, id query capped at 500 before IDs are loaded into PHP (reviewer fix). The app already reads paging meta. | `tests/Feature/Api/PublicApiTest.php` (3 new, including reviewer query-bound check) |
| 9 | `api.store-description.raw-html-in-app-htmlwidget` | Confirmed, fixed (app) | The store About sheet shows the plain text the seller typed, as the web does; markup never renders and remote images never load. | `mobile/test/features/catalog_lists_test.dart` |
| 10 | `apppay-mock-simulator-settles-order-free-when-env-allows-mock` | Owner ruling, unchanged | Haze 2026-08-18 "i want mock, no real creds for now", ecosystem hold HED-D53. Guard re-checked: off outside local unless `IPAY88_ALLOW_MOCK`, a configured merchant code always wins, production logs a warning. | `tests/Feature/Payments/MockPaymentGuardTest.php`, `tests/Feature/M1/PaymentGatewayTest.php` (pass, unchanged) |
| 11 | `checkout-voucher-usage-and-groupbuy-burn-never-released-on-unpaid-cancel` | Owner question | Code fact: `CheckoutService` increments `used_count` and records usage; no cancel or expiry path gives it back (web and app share this). Purchasing is off on live today. | none (question below) |
| 12 | `device-token-fanout-unbounded-per-user` | Confirmed, fixed | At most 10 device tokens per account, newest kept. | `tests/Feature/Api/DeviceApiTest.php` |
| 13 | `draft-product-chip-disclosure-via-chat-send` | Confirmed, fixed | The chat context chip resolves only live products of the store. | `tests/Feature/Api/AccountExtrasApiTest.php` |
| 14 | `push-registration-outlives-session-revocation` | Confirmed, fixed | App and web password change, password reset and admin suspension delete push registrations with the API tokens. | `tests/Feature/Security/PushRevocationTest.php` (4) |
| 15 | `seller-upload-client-filename-extension-stored-on-public-disk` | Confirmed, fixed (API) | Seller API logo, banner and product images use `UploadName::safe()` (server-detected extension). PNG bytes named `x.html` were stored as `.html`. Web seller screens (Settings logo/banner, product form images and video, seller application documents) still keep client names: follow-up, web scope. | `tests/Feature/Api/SellerStoreApiTest.php`, `tests/Feature/Api/SellerProductsApiTest.php` |
| 16 | `suspended-store-keeps-chat-access` | Confirmed, fixed (parity default) | Only an approved store keeps its side of the chat, as the web seller area (EnsureSeller) already does; a suspended owner still sees threads they began as a buyer. Applied under the app parity rule (2026-11-11 "mirror only what web does"); Haze can reverse it. | `tests/Feature/Api/AccountExtrasApiTest.php` |

## Owner question

**Q1 (lead 11).** When an order is cancelled before payment, or expires unpaid, should the buyer get the voucher use back (voucher quota, their per-user use, and a group-buy seat)? **Default if no answer: yes, give all three back in the same cancel transaction.** No live data is written either way, and purchasing stays off until the cutover.

## Not in scope

Android release signing (debug key) stays known. Web-only seller upload sites and the web chat send share patterns fixed here on the API side; they belong to the web scope.
