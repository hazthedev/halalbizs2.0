# HalalBizs mobile app

Flutter app for iOS and Android (phone first) in one binary: buyer tabs plus the
seller centre for approved stores. It talks to the Laravel JSON API at
`/api/v1` (Sanctum bearer tokens). See `marketplace-docs` and the mobile plan
for the full contract.

## Lineage

The architecture is a port of ASTRA (Weststar's Flutter client): a thin
envelope `ApiClient` that throws `ApiException` (`isOffline` / `isValidation`
/ `fields`), a `SessionController` (restore / login / two-factor / logout,
401 → signed out) over a `TokenStore` on `flutter_secure_storage`, a drift
`AppDb` with `response_cache` + `kv`, an `OfflineRepo` for network-first reads
with snapshot fallback, a `go_router` `StatefulShellRoute.indexedStack` with
one branch per bottom tab, and a UI kit (`SoukPage`, `SoukButton` with
`requiresOnline`, `ErrorPane`, `SoukEmptyState`, form fields with
`fieldError`). Removed from ASTRA: RBAC permissions, forced password change,
the master-data SyncEngine, the desktop sidebar shell and data tables. Renamed:
Astra → Souk for the kit and theme, HalalBizs for the app.

## Run

```bash
flutter pub get                       # also runs gen-l10n (lib/l10n/app_localizations*.dart)
dart run build_runner build -d        # drift: lib/core/db/app_db.g.dart
flutter run -d chrome                 # or an iOS / Android device
flutter run --dart-define=API_BASE_URL=https://halalbizs.com/api/v1
```

Build defines:

- `API_BASE_URL` — API root, default `http://halalbizs2.0-mobile.test/api/v1`
  (Herd). Production: `https://halalbizs.com/api/v1`.
- `PUSH_ENABLED=true` — registers the device for push (slice 5; no-op until
  the Firebase config files exist).

Release builds: `flutter build apk --release --dart-define=API_BASE_URL=…`,
`flutter build ipa --release --dart-define=API_BASE_URL=…` (needs Xcode),
`flutter build web --release` (used for the Playwright smoke).

## Gates

```bash
flutter analyze        # 0 issues in lib/ and test/
flutter test           # widget tests with a fake ApiClient, never the live API
flutter build web --release
```

Tests override `apiClientProvider` (`FakeApi` in `test/helpers.dart`),
`appDbProvider` (`AppDb.test(NativeDatabase.memory())`), `tokenStoreProvider`
(`MemoryTokenStore`) and `isOfflineProvider`.

Icons and splash: `dart run flutter_launcher_icons` and
`dart run flutter_native_splash:create` from the khatam mark
(`assets/icon/khatam-1024.png`, paper `#FAF7F1`). Both are configured in
`pubspec.yaml`; the iOS outputs need the `ios/` project, so regenerate on a
machine with Xcode before the first iOS build.

## Design

Souk (Emerald & Brass) tokens from `resources/css/app.css` live in
`lib/core/theme/souk_theme.dart`. Emerald = money and action (one emerald CTA
per region); brass = ornament only; warm ivory canvas; no dark mode. Fonts
bundled under `assets/fonts` (OFL): Newsreader (display), Instrument Sans
(body), IBM Plex Mono (prices, SKUs, counts). Money arrives as integer sen and
is formatted for display only (`PriceText` / `formatSen`), never computed in
the app.

## Built so far

Slice 4: theme + fonts, the kit, api / auth / db / offline core, the router
with both shells, auth screens (`/login`, `/register`, `/two-factor`,
`/forgot-password`, `/verify-email`), home, search, category, product and
store pages, l10n en + ms, the Account tab root and the Language page.

Slice 5 (buyer): cart (`features/cart`, `AsyncNotifier` over `GET cart`; the
tab badge is `cartCountProvider`), checkout + the iPay88 hand-off
(`features/checkout`: quote → place → `/checkout/pay/:orderNo` opens
`payment_url` externally and polls `GET orders/{no}/payment`; the
`halalbizs://pay/{order_no}` deep link lands on the same screen via
`soukRedirect`), orders (`features/orders`: buckets, detail, cancel / confirm
received / return with photos / review), account sub-pages (profile,
addresses, wishlist, coins, notifications, delete account), messages
(`features/messages`, thread polled every 10 s), "Chat with seller" on the
product page, and the push stub (`core/push/push_service.dart`:
`registerDevice` → `POST devices`, `unregister` on sign-out, behind
`PUSH_ENABLED`).

## Stubbed for later slices

- Seller centre tabs (slice 6): placeholders under `/seller/*`.
- Push: `PushService` has no token source until `firebase_messaging` and the
  Firebase config files exist; `registerDevice` is wired but never called.
- Review photos (`items[].photos[]`) and the "Become a seller" flow stay on
  the web.
- `GET home`, `GET stores/{slug}`, `GET stores/{slug}/products`, product
  filters and `sort` (API slice 2): the app calls them per the plan and falls
  back to `GET products` where the route is missing.
- Web: `web/sqlite3.wasm` and `web/drift_worker.js` give the drift cache on
  Flutter web; the app also runs without them (cache disabled).
