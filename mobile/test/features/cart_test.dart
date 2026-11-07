import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';

/// `GET cart` sample from the buyer API contract.
Map<String, dynamic> fakeCart({bool selected = true, bool purchasing = true}) => {
      'groups': [
        {
          'store': {'id': 3, 'slug': 'kedai-madu', 'name': 'Kedai Madu', 'logo': null},
          'lines': [
            {
              'variant_id': 10,
              'product': {'id': 1, 'slug': 'madu-tualang', 'name': 'Madu Tualang Asli', 'thumb': null},
              'variant_label': '500g',
              'qty': 2,
              'unit_price_sen': 1250,
              'list_price_sen': 1500,
              'stock': 12,
              'selected': selected,
              'available': true,
              'cod_enabled': true,
            },
          ],
          'subtotal_sen': selected ? 2500 : 0,
        },
        {
          'store': {'id': 4, 'slug': 'kedai-kurma', 'name': 'Kedai Kurma', 'logo': null},
          'lines': [
            {
              'variant_id': 20,
              'product': {'id': 2, 'slug': 'kurma-ajwa', 'name': 'Kurma Ajwa Premium', 'thumb': null},
              'variant_label': null,
              'qty': 1,
              'unit_price_sen': 3200,
              'list_price_sen': 3200,
              'stock': 5,
              'selected': false,
              'available': true,
              'cod_enabled': false,
            },
          ],
          'subtotal_sen': 0,
        },
      ],
      'item_count': 3,
      'selected_count': selected ? 2 : 0,
      'selected_subtotal_sen': selected ? 2500 : 0,
      'purchasing_enabled': purchasing,
    };

void main() {
  testWidgets('cart groups lines by store, shows the selected subtotal and the tab badge', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET me': (_, _) => fakeUser(),
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET cart': (_, _) => fakeCart(),
      'PATCH cart/items/10': (_, body) {
        expect((body as Map)['qty'], 3);
        return fakeCart();
      },
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/cart');

    expect(find.byKey(const Key('cart-store-3')), findsOneWidget);
    expect(find.byKey(const Key('cart-store-4')), findsOneWidget);
    expect(find.text('Kedai Madu'), findsOneWidget);
    expect(find.text('Madu Tualang Asli'), findsOneWidget);
    expect(find.text('RM 12.50'), findsOneWidget);
    expect(find.text('RM 15.00'), findsOneWidget); // list price struck through
    expect(find.text('RM 25.00'), findsOneWidget); // selected subtotal, verbatim from the payload
    expect(find.text('3 items'), findsOneWidget);
    expect(find.byKey(const Key('tab-badge-2')), findsOneWidget);

    // Checkout is enabled: 2 selected.
    await tester.tap(find.byKey(const Key('cart-checkout')));
    await tester.pump();
    expect(h.router.state.uri.path, '/checkout');
    h.router.go('/cart');
    await tester.pumpAndSettle();

    // Qty stepper writes PATCH cart/items/{variant}.
    await tester.tap(find.descendant(of: find.byKey(const Key('cart-line-10')), matching: find.byKey(const Key('qty-plus'))));
    await tester.pumpAndSettle();
    expect(api.calls, contains('PATCH cart/items/10'));
  });

  testWidgets('checkout is disabled when nothing is selected and when purchasing is paused', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET me': (_, _) => fakeUser(),
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET cart': (_, _) => fakeCart(selected: false),
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/cart');

    expect(find.text('RM 0.00'), findsOneWidget);
    await tester.tap(find.byKey(const Key('cart-checkout')));
    await tester.pumpAndSettle();
    expect(h.router.state.uri.path, '/cart', reason: 'disabled button must not navigate');
    expect(find.text('Select at least one item to check out.'), findsOneWidget);

    await tester.pump(const Duration(seconds: 5)); // let the SnackBar go
    api.routes['GET cart'] = (_, _) => fakeCart(purchasing: false);
    await h.container.read(cartProviderForTest.notifier).refresh();
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('cart-purchasing-paused')), findsOneWidget);
    await tester.tap(find.byKey(const Key('cart-checkout')));
    await tester.pumpAndSettle();
    expect(h.router.state.uri.path, '/cart');
  });

  testWidgets('empty cart shows the khatam empty state', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET me': (_, _) => fakeUser(),
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET cart': (_, _) => {'groups': [], 'item_count': 0, 'selected_count': 0, 'selected_subtotal_sen': 0, 'purchasing_enabled': true},
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/cart');
    expect(find.byKey(const Key('cart-empty')), findsOneWidget);
    expect(find.byKey(const Key('cart-checkout')), findsNothing);
    expect(find.byKey(const Key('tab-badge-2')), findsNothing);
  });
}
