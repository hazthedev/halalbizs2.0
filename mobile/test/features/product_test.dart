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

  testWidgets('signed-in Add to cart posts the variant + qty, updates the badge and offers View cart', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET me': (_, _) => fakeUser(),
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET products/madu-tualang': (_, _) => fakeProduct(),
      'GET cart': (_, _) => {'groups': [], 'item_count': 0, 'selected_count': 0, 'selected_subtotal_sen': 0, 'purchasing_enabled': true},
      'POST cart/items': (_, body) {
        expect((body as Map)['variant_id'], 10);
        expect(body['qty'], 2);
        return {'groups': [], 'item_count': 2, 'selected_count': 2, 'selected_subtotal_sen': 2500, 'purchasing_enabled': true};
      },
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/p/madu-tualang');
    await tester.tap(find.byKey(const Key('qty-plus')));
    await tester.pump();
    await tester.tap(find.byKey(const Key('product-add-to-cart')));
    await tester.pumpAndSettle();
    expect(api.calls, contains('POST cart/items'));
    expect(find.text('Added to cart'), findsOneWidget);
    expect(find.byKey(const Key('tab-badge-2')), findsOneWidget);
    await tester.tap(find.byKey(const Key('product-view-cart')));
    await tester.pumpAndSettle();
    expect(h.router.state.uri.path, '/cart');
  });
}
