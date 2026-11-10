import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/core/api/api_client.dart';
import 'package:halalbizs/core/auth/session.dart';
import 'package:halalbizs/core/router.dart';
import 'package:halalbizs/features/checkout/pay_screen.dart';

import '../helpers.dart';

Map<String, dynamic> fakeAddress({int id = 1, bool isDefault = true}) => {
      'id': id,
      'label': 'Home',
      'recipient_name': 'Buyer One',
      'phone': '0123456789',
      'line1': '12 Jalan Satu',
      'line2': null,
      'postcode': '50450',
      'city': 'Kuala Lumpur',
      'state': 'Kuala Lumpur',
      'country': 'MY',
      'is_default': isDefault,
    };

/// `POST checkout/quote` sample from the buyer API contract (`CheckoutQuote::toArray()`).
Map<String, dynamic> fakeQuote() => {
      'purchasing_enabled': true,
      'groups': [
        {
          'store': {'id': 3, 'slug': 'kedai-madu', 'name': 'Kedai Madu'},
          'items_subtotal_sen': 10000,
          'shipping_fee_sen': 2345,
          'shipping_subsidy_sen': 0,
          'tax_sen': 0,
          'lines': [
            {'variant_id': 10, 'product_id': 1, 'name': 'Madu Tualang Asli', 'variant_label': '500g', 'qty': 2, 'unit_price_sen': 5000, 'list_price_sen': 5000, 'line_total_sen': 10000, 'tax_sen': 0, 'cod_enabled': true, 'halal_cert_number': 'MY-JKM-2140-118'},
          ],
        },
      ],
      'subtotal_sen': 10000,
      'shipping_total_sen': 2345,
      'shipping_subsidy_sen': 0,
      'tax_total_sen': 0,
      'platform_discount_sen': 0,
      'shop_discount_sen': 0,
      'coin_redemption_sen': 0,
      'grand_total_sen': 12345,
      'vouchers': {'platform': null, 'shop': null, 'shipping': null},
      'voucher_errors': {},
      'voucher_options': {'platform': [], 'shop': [], 'best_platform_voucher_id': null},
      'coins': {'enabled': true, 'balance': 0, 'redeemed': 0, 'redemption_sen': 0},
      'payment_methods': [
        {'method': 'ipay88', 'available': true, 'reason': null},
        {'method': 'cod', 'available': false, 'reason': 'Cash on delivery is capped at RM 100.00 per order.'},
      ],
      'payment_method': 'ipay88',
      'payment_method_available': true,
      'block_reason': null,
    };

/// `GET cart` after a place attempt: [selected] lines still there or gone.
Map<String, dynamic> cartAfter({required int selected}) => {
      'groups': [],
      'item_count': selected,
      'selected_count': selected,
      'selected_subtotal_sen': selected * 5000,
      'purchasing_enabled': true,
    };

/// Routes every checkout test needs; [extra] adds or overrides.
Map<String, FakeHandler> checkoutRoutes([Map<String, FakeHandler> extra = const {}]) => {
      'GET me': (_, _) => fakeUser(),
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET addresses': (_, _) => [fakeAddress()],
      'POST checkout/quote': (_, _) => fakeQuote(),
      'GET orders/buckets': (_, _) => [],
      'GET orders': (_, _) => [],
      ...extra,
    };

void main() {
  testWidgets('checkout shows the quote totals verbatim and greys an unavailable payment method', (tester) async {
    usePhone(tester);
    Object? quoteBody;
    Object? placeBody;
    final api = FakeApi(routes: checkoutRoutes({
      'POST checkout/quote': (_, body) {
        quoteBody = body;
        return fakeQuote();
      },
      'POST checkout/place': (_, body) {
        placeBody = body;
        return {'order_id': 9, 'order_no': 'HB-1001', 'payment_method': 'ipay88', 'total_sen': 12345, 'sub_orders': [], 'payment_url': 'https://pay.test/x', 'expires_at': null};
      },
      'GET orders/HB-1001/payment': (_, _) => {'order_no': 'HB-1001', 'method': 'ipay88', 'status': 'pending', 'paid': false, 'expires_at': null, 'retry_url': 'https://pay.test/x'},
    }));
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/checkout');
    expect((quoteBody as Map?)?['address_id'], 1);

    expect(find.text('RM 123.45'), findsNWidgets(2)); // totals block + sticky bar, never computed
    expect(find.text('RM 100.00'), findsNWidgets(2)); // line total + subtotal
    expect(find.text('RM 23.45'), findsWidgets); // shipping (store row + totals)
    expect(find.text('Buyer One · 0123456789'), findsOneWidget);
    expect(find.byKey(const Key('checkout-halal-10')), findsOneWidget);
    expect(find.text('MY-JKM-2140-118'), findsOneWidget);
    expect(find.byKey(const Key('checkout-method-cod')), findsOneWidget);
    expect(find.byKey(const Key('checkout-method-reason-cod')), findsOneWidget);
    expect(find.text('Cash on delivery is capped at RM 100.00 per order.'), findsOneWidget);
    expect(find.byKey(const Key('checkout-method-reason-ipay88')), findsNothing);

    await tester.tap(find.byKey(const Key('checkout-place')));
    await tester.pumpAndSettle();
    expect(api.calls, contains('POST checkout/place'));
    expect(api.lastHeaders?['Idempotency-Key'], matches(RegExp(r'^[0-9a-f]{32}$')));
    expect((placeBody as Map?)?['payment_method'], 'ipay88');
    expect((placeBody as Map?)?['address_id'], 1);
    expect(h.router.state.uri.path, '/checkout/pay/HB-1001');
    expect(find.byKey(const Key('pay-waiting')), findsOneWidget);
    expect(h.browser.opened, ['https://pay.test/x'], reason: 'payment opens in the in-app browser sheet, not Chrome');
  });

  testWidgets('the return deep link closes the in-app browser sheet and checks the payment at once', (tester) async {
    usePhone(tester);
    var polls = 0;
    final api = FakeApi(routes: {
      'GET me': (_, _) => fakeUser(),
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET orders/HB-7/payment': (_, _) {
        polls++;
        return {'order_no': 'HB-7', 'method': 'ipay88', 'status': polls >= 2 ? 'paid' : 'pending', 'paid': polls >= 2, 'expires_at': null, 'retry_url': polls >= 2 ? null : 'https://pay.test/y'};
      },
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/checkout/pay/HB-7');
    expect(polls, 1);
    expect(h.browser.closes, 0);

    h.router.go(soukRedirect(SignedIn(Profile(fakeUser())), Uri.parse('halalbizs://pay/HB-7'))!);
    await tester.pumpAndSettle();
    expect(h.browser.closes, 1);
    expect(polls, 3, reason: 'checked on return, not on the next timer tick (2); the done page reads it once (3)');
    expect(h.router.state.uri.path, '/checkout/done/HB-7');
  });

  testWidgets('place that timed out with the selection consumed goes to orders, never a second place', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: checkoutRoutes({
      'POST checkout/place': (_, _) => ApiException.timeout(),
      'GET cart': (_, _) => cartAfter(selected: 0),
    }));
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/checkout');

    await tester.tap(find.byKey(const Key('checkout-place')));
    await tester.pumpAndSettle();
    expect(api.calls.where((c) => c == 'POST checkout/place').length, 1);
    expect(api.calls, contains('GET cart'));
    expect(h.router.state.uri.path, '/orders');
    expect(find.text('Your order was placed. Check your orders.'), findsOneWidget);
  });

  testWidgets('place that timed out with the selection intact re-enables Place', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: checkoutRoutes({
      'POST checkout/place': (_, _) => ApiException.timeout(),
      'GET cart': (_, _) => cartAfter(selected: 2),
    }));
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/checkout');

    await tester.tap(find.byKey(const Key('checkout-place')));
    await tester.pumpAndSettle();
    expect(h.router.state.uri.path, '/checkout');
    final key = api.lastHeaders?['Idempotency-Key'];
    expect(key, matches(RegExp(r'^[0-9a-f]{32}$')));
    await tester.pump(const Duration(seconds: 5)); // let the error snack bar go
    await tester.tap(find.byKey(const Key('checkout-place')));
    await tester.pumpAndSettle();
    expect(api.calls.where((c) => c == 'POST checkout/place').length, 2);
    // The retry is the same attempt: same key, so the server replays rather than re-places.
    expect(api.lastHeaders?['Idempotency-Key'], key);
  });

  testWidgets('409 checkout_failed puts the per-variant message under its line', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: checkoutRoutes({
      'POST checkout/place': (_, _) => ApiException('checkout_failed', 'Some items changed.', status: 409, fields: {
            '10': ['Only 1 left in stock.'],
          }),
    }));
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/checkout');

    await tester.tap(find.byKey(const Key('checkout-place')));
    await tester.pumpAndSettle();
    expect(tester.widget<Text>(find.byKey(const Key('checkout-line-error-10'))).data, 'Only 1 left in stock.');
  });

  testWidgets('a failed re-quote drops the stale totals and Place with them', (tester) async {
    usePhone(tester);
    var quotes = 0;
    final api = FakeApi(routes: checkoutRoutes({
      'POST checkout/quote': (_, _) => ++quotes == 1 ? fakeQuote() : ApiException.timeout(),
    }));
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/checkout');
    expect(find.byKey(const Key('checkout-place')), findsOneWidget);

    await tester.enterText(find.byKey(const Key('checkout-platform-code')), 'RAYA10');
    await tester.tap(find.byKey(const Key('checkout-platform-apply')));
    await tester.pumpAndSettle();
    expect(quotes, 2);
    expect(find.byKey(const Key('checkout-place')), findsNothing);
    expect(find.text('RM 123.45'), findsNothing);
    expect(find.text('Retry'), findsOneWidget);
  });

  testWidgets('a placed order without a payment link goes to orders, not the done page', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: checkoutRoutes({
      'POST checkout/place': (_, _) => {'order_id': 9, 'order_no': 'HB-1002', 'payment_method': 'ipay88', 'total_sen': 12345, 'sub_orders': [], 'payment_url': null, 'expires_at': null},
    }));
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/checkout');

    await tester.tap(find.byKey(const Key('checkout-place')));
    await tester.pumpAndSettle();
    expect(h.router.state.uri.path, '/orders');
    expect(find.byKey(const Key('checkout-done')), findsNothing);
  });

  testWidgets('pay screen polls GET orders/{no}/payment and lands on done when paid', (tester) async {
    usePhone(tester);
    var polls = 0;
    final api = FakeApi(routes: {
      'GET me': (_, _) => fakeUser(),
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET orders/HB-7/payment': (_, _) {
        polls++;
        return {'order_no': 'HB-7', 'method': 'ipay88', 'status': polls >= 2 ? 'paid' : 'pending', 'paid': polls >= 2, 'expires_at': null, 'retry_url': polls >= 2 ? null : 'https://pay.test/y'};
      },
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/checkout/pay/HB-7');
    expect(polls, 1);
    expect(find.byKey(const Key('pay-waiting')), findsOneWidget);
    expect(find.byKey(const Key('pay-paid')), findsOneWidget);

    await tester.pump(PayScreen.pollEvery);
    await tester.pumpAndSettle();
    expect(polls, 3, reason: 'two pay polls + the done page read');
    expect(h.router.state.uri.path, '/checkout/done/HB-7');
    expect(find.byKey(const Key('checkout-done')), findsOneWidget);

    await tester.pump(PayScreen.pollEvery * 2);
    await tester.pumpAndSettle();
    expect(polls, 3, reason: 'no polling after paid');
  });

  testWidgets('leaving the pay screen stops the poll', (tester) async {
    usePhone(tester);
    var polls = 0;
    final api = FakeApi(routes: checkoutRoutes({
      'GET orders/HB-9/payment': (_, _) {
        polls++;
        return {'order_no': 'HB-9', 'method': 'ipay88', 'status': 'pending', 'paid': false, 'expires_at': null, 'retry_url': 'https://pay.test/z'};
      },
    }));
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/checkout/pay/HB-9');
    expect(polls, 1);

    h.router.go('/home');
    await tester.pumpAndSettle();
    await tester.pump(PayScreen.pollEvery * 2);
    await tester.pumpAndSettle();
    expect(polls, 1);
  });

  test('halalbizs://pay/{order_no} deep link lands on the pay screen, encoded', () {
    final signedIn = SignedIn(Profile(fakeUser()));
    expect(soukRedirect(signedIn, Uri.parse('halalbizs://pay/HB-1')), '/checkout/pay/HB-1?returned=1');
    expect(soukRedirect(signedIn, Uri.parse('halalbizs://pay/HB%201')), '/checkout/pay/HB%201?returned=1');
    expect(soukRedirect(signedIn, Uri.parse('halalbizs://pay/')), '/home');
  });

  testWidgets('pay screen shows the expired state when the window has passed and no retry url is left', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET me': (_, _) => fakeUser(),
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET orders/HB-8/payment': (_, _) => {'order_no': 'HB-8', 'method': 'ipay88', 'status': 'expired', 'paid': false, 'expires_at': '2020-01-01T00:00:00Z', 'retry_url': null},
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/checkout/pay/HB-8');
    expect(find.byKey(const Key('pay-expired')), findsOneWidget);
    expect(find.byKey(const Key('pay-paid')), findsNothing);
    expect(find.byKey(const Key('pay-retry')), findsNothing);
  });

  testWidgets('unverified buyer hitting checkout is sent to /verify-email', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET me': (_, _) => fakeUser(verified: false),
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET addresses': (_, _) => [fakeAddress()],
      'POST checkout/quote': (_, _) => unverifiedError(),
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/checkout');
    expect(h.router.state.uri.path, '/verify-email');
  });

  testWidgets('the done page explains the split: amount paid, payment number and one line per shop order', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET me': (_, _) => fakeUser(),
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET cart': (_, _) => {'groups': [], 'item_count': 0, 'selected_count': 0, 'selected_subtotal_sen': 0, 'purchasing_enabled': true},
      'GET orders/MP2611VR4WJ6/payment': (_, _) => {
            'order_no': 'MP2611VR4WJ6', 'method': 'ipay88', 'status': 'paid', 'paid': true, 'expires_at': null, 'retry_url': null,
            'total_sen': 78102,
            'sub_orders': [
              {'id': 31, 'sub_order_no': 'SO2611JELOZH', 'store': {'id': 3, 'name': 'Goodwin Ltd'}, 'status': 'confirmed'},
              {'id': 32, 'sub_order_no': 'SO2611KQ7TXA', 'store': {'id': 4, 'name': 'Parker Ltd'}, 'status': 'confirmed'},
            ],
          },
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/checkout/done/MP2611VR4WJ6');

    expect(find.text('Paid RM 781.02 (payment MP2611VR4WJ6). Your order is split into 2 shipments, one per shop.'), findsOneWidget);
    expect(find.byKey(const Key('done-sub-order-31')), findsOneWidget);
    expect(find.text('SO2611JELOZH'), findsOneWidget);
    expect(find.text('Goodwin Ltd'), findsOneWidget);
    expect(find.text('SO2611KQ7TXA'), findsOneWidget);
    expect(find.text('Parker Ltd'), findsOneWidget);

    await tester.tap(find.byKey(const Key('done-sub-order-32')));
    await tester.pumpAndSettle();
    expect(h.router.state.uri.path, '/orders/32');
  });
}
