import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../features/account/account_api.dart';
import '../features/account/account_screen.dart';
import '../features/auth/auth_screens.dart';
import '../features/cart/cart_api.dart';
import '../features/cart/cart_screen.dart';
import '../features/catalog/category_screen.dart';
import '../features/catalog/product_screen.dart';
import '../features/catalog/reviews.dart';
import '../features/catalog/search_screen.dart';
import '../features/catalog/certificate_record.dart';
import '../features/catalog/store_screen.dart';
import '../features/home/home_screen.dart';
import '../features/messages/messages_api.dart';
import '../features/orders/orders_api.dart';
import '../features/orders/orders_screen.dart';
import '../features/seller_dashboard/seller_dashboard_screen.dart';
import '../features/seller_earnings/seller_earnings_screen.dart';
import '../features/seller_orders/seller_orders_screen.dart';
import '../features/seller_products/seller_products_screen.dart';
import '../features/seller_store/seller_store_screen.dart';
import '../l10n/l10n.dart';
import 'auth/session.dart';
import 'widgets/phone_nav.dart';
import 'widgets/souk_kit.dart';

/// Routes a guest cannot open: they bounce to `/login?from=<path>`.
/// Home, search, product, store and category stay open to guests.
const kAuthPrefixes = ['/cart', '/checkout', '/orders', '/account', '/seller'];

bool needsAuth(String path) => kAuthPrefixes.any((p) => path == p || path.startsWith('$p/'));

/// A `from` return path is honoured only when it is an in-app path (one
/// leading slash — never `//host` or `https://…`) that is not an auth page;
/// anything else lands on `/home`.
String safeFrom(String? from) {
  if (from == null || !from.startsWith('/') || from.startsWith('//')) return '/home';
  final path = Uri.tryParse(from)?.path;
  if (path == null || path == '/login' || path == '/register' || path == '/two-factor') return '/home';
  return from;
}

/// Where a path (or null) should go for the current [session], or null to
/// keep it. Pure, so tests can call it without a router.
String? soukRedirect(SessionState session, Uri uri) {
  // `halalbizs://pay/{order_no}` (the web return page's deep link) → the pay screen.
  if (uri.scheme == 'halalbizs') {
    final orderNo = uri.host == 'pay' ? uri.pathSegments.firstOrNull : null;
    return orderNo == null || orderNo.isEmpty ? '/home' : '/checkout/pay/${Uri.encodeComponent(orderNo)}?returned=1';
  }
  final path = uri.path;
  if (session is SessionLoading) return null;
  if (session is TwoFactorPending) {
    // Keep the query so `from` survives the detour.
    return path == '/two-factor' ? null : '/two-factor${uri.hasQuery ? '?${uri.query}' : ''}';
  }
  if (path == '/two-factor') return '/login';
  if (session is SignedOut) {
    return needsAuth(path) ? '/login?from=${Uri.encodeComponent(uri.toString())}' : null;
  }
  // Signed in: auth pages are done (verify-email stays reachable).
  if (path == '/login' || path == '/register') return safeFrom(uri.queryParameters['from']);
  if (path == '/seller' || path.startsWith('/seller/')) {
    final me = (session as SignedIn).profile;
    if (!me.isApprovedSeller) return '/account';
  }
  return null;
}

/// Top-level navigation. Feature agents register SUB-routes inside their own
/// feature's `routes:` list by editing ONLY their feature file and its
/// `<feature>SubRoutes` getter — never this file.
final routerProvider = Provider<GoRouter>((ref) {
  final notifier = ValueNotifier(0);
  ref.listen(sessionProvider, (prev, next) {
    // A sign-in step from an auth screen (password → 2FA challenge → signed
    // in) is left alone: the screen navigates itself (pops back when it was
    // pushed), so a redirect here would wipe the stack and the back arrow.
    final signedInFromAuth = (next is SignedIn && (prev is SignedOut || prev is TwoFactorPending)) ||
        (next is TwoFactorPending && prev is SignedOut);
    if (!signedInFromAuth) notifier.value++;
    // A different account (or none) is signed in: drop every cached account-scoped read.
    if (_userId(prev) != _userId(next)) _dropAccountData(ref);
  });
  ref.onDispose(notifier.dispose);

  return GoRouter(
    initialLocation: '/home',
    refreshListenable: notifier,
    errorBuilder: (context, state) => SoukNotFoundScreen(location: state.uri.toString()),
    redirect: (context, state) => soukRedirect(ref.read(sessionProvider), state.uri),
    routes: [
      ...authRoutes,
      // Checkout, pay and done sit outside the tab shell: full screen, no tab
      // bar, and leaving them disposes the pay screen so its polling stops and
      // the Cart tab comes back to `/cart` instead of a finished order.
      ...cartSubRoutes,
      buyerShellRoute(),
      sellerShellRoute(),
    ],
  );
});

int? _userId(SessionState? s) => s is SignedIn ? s.profile.id : null;

/// Kept-alive providers that hold one user's data (the cart keys itself off
/// the user id). Add every new account-scoped provider here.
void _dropAccountData(Ref ref) {
  ref.invalidate(addressesProvider);
  ref.invalidate(wishlistProvider);
  ref.invalidate(coinsProvider);
  ref.invalidate(notificationsProvider);
  ref.invalidate(orderBucketsProvider);
  ref.invalidate(orderDetailProvider);
  ref.invalidate(conversationsProvider);
}

/// Buyer shell: `StatefulShellRoute.indexedStack`, one branch per tab
/// ([buyerTabs]). Pushed pages live under the tab that owns them so deep
/// links land in the right tab and every tab keeps its own stack.
RouteBase buyerShellRoute() => StatefulShellRoute.indexedStack(
      builder: (context, _, shell) => Consumer(
        builder: (context, ref, _) => SoukTabShell(
          shell: shell,
          tabs: buyerTabs,
          labels: [context.l10n.tabHome, context.l10n.tabSearch, context.l10n.tabCart, context.l10n.tabOrders, context.l10n.tabAccount],
          badges: {2: ref.watch(cartCountProvider)},
        ),
      ),
      branches: [
        StatefulShellBranch(routes: [
          GoRoute(path: '/home', builder: (_, _) => const HomeScreen()),
          GoRoute(path: '/featured', builder: (_, _) => const FeaturedScreen()),
          GoRoute(path: '/p/:slug', builder: (_, s) => ProductScreen(slug: s.pathParameters['slug']!)),
          GoRoute(path: '/p/:slug/reviews', builder: (_, s) => ReviewsScreen(slug: s.pathParameters['slug']!)),
          GoRoute(path: '/certificate/:number', builder: (_, s) => CertificateRecordScreen(number: s.pathParameters['number']!)),
          GoRoute(path: '/s/:slug', builder: (_, s) => StoreScreen(slug: s.pathParameters['slug']!)),
          GoRoute(path: '/c/:slug', builder: (_, s) => CategoryScreen(slug: s.pathParameters['slug']!)),
        ]),
        StatefulShellBranch(routes: [
          GoRoute(path: '/search', builder: (_, _) => const SearchScreen()),
        ]),
        StatefulShellBranch(routes: [
          GoRoute(path: '/cart', builder: (_, _) => const CartScreen()),
        ]),
        StatefulShellBranch(routes: [
          GoRoute(path: '/orders', builder: (_, s) => OrdersScreen(initialBucket: s.uri.queryParameters['bucket']), routes: ordersSubRoutes),
        ]),
        StatefulShellBranch(routes: [
          GoRoute(path: '/account', builder: (_, _) => const AccountScreen(), routes: accountSubRoutes),
        ]),
      ],
    );

/// Seller shell (entered from Account when the store is approved).
RouteBase sellerShellRoute() => StatefulShellRoute.indexedStack(
      builder: (context, _, shell) => SoukTabShell(
        shell: shell,
        tabs: sellerTabs,
        labels: [
          context.l10n.sellerTabDashboard,
          context.l10n.sellerTabOrders,
          context.l10n.sellerTabProducts,
          context.l10n.sellerTabEarnings,
          context.l10n.sellerTabStore,
        ],
      ),
      branches: [
        StatefulShellBranch(routes: [
          GoRoute(path: '/seller', builder: (_, _) => const SellerDashboardScreen(), routes: sellerDashboardSubRoutes),
        ]),
        StatefulShellBranch(routes: [
          GoRoute(
            path: '/seller/orders',
            builder: (_, s) => SellerOrdersScreen(initialStatus: s.uri.queryParameters['status']),
            routes: sellerOrdersSubRoutes,
          ),
        ]),
        StatefulShellBranch(routes: [
          GoRoute(path: '/seller/products', builder: (_, _) => const SellerProductsScreen(), routes: sellerProductsSubRoutes),
        ]),
        StatefulShellBranch(routes: [
          GoRoute(path: '/seller/earnings', builder: (_, _) => const SellerEarningsScreen(), routes: sellerEarningsSubRoutes),
        ]),
        StatefulShellBranch(routes: [
          GoRoute(path: '/seller/store', builder: (_, _) => const SellerStoreScreen(), routes: sellerStoreSubRoutes),
        ]),
      ],
    );

/// Unknown / broken deep link: a friendly page instead of GoException text.
class SoukNotFoundScreen extends StatelessWidget {
  const SoukNotFoundScreen({super.key, this.location});

  final String? location;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return SoukPage(
      title: l.pageNotFound,
      showBack: false,
      body: Center(
        child: SoukEmptyState(
          key: const Key('souk-not-found'),
          title: l.pageNotFound,
          message: l.pageNotFoundBody,
          action: SoukButton(
            key: const Key('souk-not-found-home'),
            label: l.backToHome,
            variant: SoukButtonVariant.primary,
            onPressed: () => GoRouter.of(context).go('/home'),
          ),
        ),
      ),
    );
  }
}
