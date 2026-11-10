import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';

/// Signed-in fake with a server-side wishlist: `POST wishlist/{id}` toggles,
/// `GET wishlist` lists — the API's WishlistController contract.
FakeApi wishlistApi(Set<int> saved) {
  final products = {1: fakeProduct(), 2: fakeProduct(id: 2, slug: 'kurma-ajwa', name: 'Kurma Ajwa Premium')};
  return FakeApi(routes: {
    'GET me': (_, _) => fakeUser(),
    'GET products': (_, _) => products.values.toList(),
    'GET categories': (_, _) => [],
    'GET products/madu-tualang': (_, _) => products[1],
    'GET cart': (_, _) => {'groups': [], 'item_count': 0, 'selected_count': 0, 'selected_subtotal_sen': 0, 'purchasing_enabled': true},
    'GET wishlist': (_, _) => [for (final id in saved) products[id]],
    'POST wishlist/1': (_, _) => {'wishlisted': saved.add(1) || !saved.remove(1)},
    'POST wishlist/2': (_, _) => {'wishlisted': saved.add(2) || !saved.remove(2)},
  });
}

void main() {
  testWidgets('product page heart adds to the wishlist and the wishlist screen lists it', (tester) async {
    usePhone(tester);
    final saved = <int>{};
    final api = wishlistApi(saved);
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/p/madu-tualang');

    await tester.tap(find.byKey(const Key('product-wishlist')));
    await tester.pumpAndSettle();
    expect(api.calls, contains('POST wishlist/1'));
    expect(saved, {1});
    expect(find.byTooltip('Remove from wishlist'), findsOneWidget);

    h.router.go('/account/wishlist');
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('wishlist-grid')), findsOneWidget);
    expect(find.byKey(const Key('product-card-madu-tualang')), findsOneWidget);
  });

  testWidgets('product page heart removes a saved product', (tester) async {
    usePhone(tester);
    final saved = {1};
    final h = Harness(api: wishlistApi(saved), token: 'tok');
    await h.pump(tester, location: '/p/madu-tualang');
    expect(find.byTooltip('Remove from wishlist'), findsOneWidget);

    await tester.tap(find.byKey(const Key('product-wishlist')));
    await tester.pumpAndSettle();
    expect(saved, isEmpty);
    expect(find.byTooltip('Add to wishlist'), findsOneWidget);
  });

  testWidgets('product card heart on home toggles that product only', (tester) async {
    usePhone(tester);
    final saved = <int>{};
    final api = wishlistApi(saved);
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester);
    await tester.pumpAndSettle();

    final heart = find.descendant(of: find.byKey(const Key('product-card-kurma-ajwa')), matching: find.byKey(const Key('product-wishlist')));
    await tester.tap(heart);
    await tester.pumpAndSettle();
    expect(api.calls, contains('POST wishlist/2'));
    expect(saved, {2});
  });

  testWidgets('guest tapping the heart is sent to login with a return path', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET products/madu-tualang': (_, _) => fakeProduct(),
    });
    final h = Harness(api: api);
    await h.pump(tester, location: '/p/madu-tualang');
    await tester.tap(find.byKey(const Key('product-wishlist')));
    await tester.pumpAndSettle();
    expect(h.router.state.uri.path, '/login');
    expect(h.router.state.uri.queryParameters['from'], '/p/madu-tualang');
    expect(api.calls.where((c) => c.contains('wishlist')), isEmpty);
  });

  testWidgets('guest heart → sign in → back on the product with a back arrow and the heart saved', (tester) async {
    usePhone(tester);
    final saved = <int>{};
    final api = wishlistApi(saved);
    api.routes['POST auth/login'] = (_, _) => {'token': 'tok', 'user': fakeUser()};
    final h = Harness(api: api);
    await h.pump(tester, location: '/home');
    h.router.push('/p/madu-tualang');
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('product-wishlist')));
    await tester.pumpAndSettle();
    expect(h.router.state.uri.path, '/login');

    await tester.enterText(find.byKey(const Key('login-email')), 'buyer@halalbizs.test');
    await tester.enterText(find.byKey(const Key('login-password')), 'password');
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();

    expect(h.router.state.uri.path, '/p/madu-tualang');
    expect(find.byKey(const Key('souk-back')), findsOneWidget);
    expect(api.calls, contains('POST wishlist/1'));
    expect(saved, {1});
    expect(find.byTooltip('Remove from wishlist'), findsOneWidget);
  });

  testWidgets('guest heart on an already-saved product does not unsave it after sign in', (tester) async {
    usePhone(tester);
    final saved = <int>{1};
    final api = wishlistApi(saved);
    api.routes['POST auth/login'] = (_, _) => {'token': 'tok', 'user': fakeUser()};
    final h = Harness(api: api);
    await h.pump(tester, location: '/home');
    h.router.push('/p/madu-tualang');
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('product-wishlist')));
    await tester.pumpAndSettle();
    await tester.enterText(find.byKey(const Key('login-email')), 'buyer@halalbizs.test');
    await tester.enterText(find.byKey(const Key('login-password')), 'password');
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();
    expect(api.calls, isNot(contains('POST wishlist/1')));
    expect(saved, {1});
  });
}
