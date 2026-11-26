import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';

/// HB-BUY1: config `purchasing_enabled` false hides every purchase control;
/// true leaves the screens as they were.
FakeApi _api({required bool purchasing}) => FakeApi(routes: {
      'GET config': (_, _) => {'purchasing_enabled': purchasing},
      'GET me': (_, _) => fakeUser(),
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET products/madu-tualang': (_, _) => fakeProduct(),
      'GET cart': (_, _) => {'groups': [], 'item_count': 0, 'selected_count': 0, 'selected_subtotal_sen': 0, 'purchasing_enabled': purchasing},
    });

void main() {
  testWidgets('purchasing off: product page has no Add to cart, Buy now or quantity; chat stays', (tester) async {
    usePhone(tester);
    await Harness(api: _api(purchasing: false), token: 'tok').pump(tester, location: '/p/madu-tualang');
    expect(find.byKey(const Key('product-add-to-cart')), findsNothing);
    expect(find.byKey(const Key('product-buy-now')), findsNothing);
    expect(find.byKey(const Key('qty-plus')), findsNothing);
    expect(find.byKey(const Key('product-chat')), findsOneWidget);
  });

  testWidgets('purchasing on: product page keeps Add to cart and Buy now', (tester) async {
    usePhone(tester);
    await Harness(api: _api(purchasing: true), token: 'tok').pump(tester, location: '/p/madu-tualang');
    expect(find.byKey(const Key('product-add-to-cart')), findsOneWidget);
    expect(find.byKey(const Key('product-buy-now')), findsOneWidget);
  });

  testWidgets('purchasing off: no Cart tab; /cart and /checkout show "Buying opens soon"', (tester) async {
    usePhone(tester);
    final h = Harness(api: _api(purchasing: false), token: 'tok');
    await h.pump(tester, location: '/home');
    expect(find.text('Cart'), findsNothing);
    h.router.go('/cart');
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('buying-opens-soon')), findsOneWidget);
    expect(find.byKey(const Key('cart-checkout')), findsNothing);
    h.router.go('/checkout');
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('buying-opens-soon')), findsOneWidget);
    expect(find.byKey(const Key('checkout-place')), findsNothing);
  });

  testWidgets('purchasing on: Cart tab shows and /cart has no soon note', (tester) async {
    usePhone(tester);
    final h = Harness(api: _api(purchasing: true), token: 'tok');
    await h.pump(tester, location: '/home');
    expect(find.text('Cart'), findsWidgets);
    h.router.go('/cart');
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('buying-opens-soon')), findsNothing);
  });
}
