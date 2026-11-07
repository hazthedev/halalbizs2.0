import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';

void main() {
  testWidgets('product page formats 1250 sen as RM 12.50 and shows the sale strike-through', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET products/sambal-nyonya': (_, _) =>
          fakeProduct(id: 3, slug: 'sambal-nyonya', name: 'Sambal Nyonya Pedas', priceSen: 1500, salePriceSen: 1250, stock: 3),
    });
    final h = Harness(api: api);
    await h.pump(tester, location: '/p/sambal-nyonya');

    expect(find.byKey(const Key('product-name')), findsOneWidget);
    expect(find.text('Sambal Nyonya Pedas'), findsWidgets);
    expect(find.text('RM 12.50'), findsOneWidget);
    expect(find.text('RM 15.00'), findsOneWidget); // struck-through regular price
    expect(find.byKey(const Key('product-regular-price')), findsOneWidget);
    expect(find.text('Only 3 left'), findsOneWidget);
    expect(find.byKey(const Key('product-store')), findsOneWidget);
    expect(find.text('Kedai Madu'), findsOneWidget);
    expect(find.byKey(const Key('product-add-to-cart')), findsOneWidget);
  });

  testWidgets('guest tapping Add to cart is sent to login with a return path', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET products/madu-tualang': (_, _) => fakeProduct(),
    });
    final h = Harness(api: api);
    await h.pump(tester, location: '/p/madu-tualang');
    await tester.tap(find.byKey(const Key('product-add-to-cart')));
    await tester.pumpAndSettle();
    expect(h.router.state.uri.path, '/login');
    expect(h.router.state.uri.queryParameters['from'], '/p/madu-tualang');
  });

  testWidgets('signed-in Add to cart hits POST cart/items and reports the slice-2 stub on 404', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET me': (_, _) => fakeUser(),
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET products/madu-tualang': (_, _) => fakeProduct(),
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/p/madu-tualang');
    await tester.tap(find.byKey(const Key('qty-plus')));
    await tester.pump();
    await tester.tap(find.byKey(const Key('product-add-to-cart')));
    await tester.pumpAndSettle();
    expect(api.calls, contains('POST cart/items'));
    expect(find.text('Cart arrives in the next slice'), findsOneWidget);
  });
}
