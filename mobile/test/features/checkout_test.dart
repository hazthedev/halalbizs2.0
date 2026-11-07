import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
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
            {'variant_id': 10, 'product_id': 1, 'name': 'Madu Tualang Asli', 'variant_label': '500g', 'qty': 2, 'unit_price_sen': 5000, 'list_price_sen': 5000, 'line_total_sen': 10000, 'tax_sen': 0, 'cod_enabled': true},
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

void main() {
  testWidgets('checkout shows the quote totals verbatim and greys an unavailable payment method', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET me': (_, _) => fakeUser(),
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET addresses': (_, _) => [fakeAddress()],
      'POST checkout/quote': (_, body) {
        expect((body as Map)['address_id'], 1);
        return fakeQuote();
      },
      'POST checkout/place': (_, body) {
        expect((body as Map)['payment_method'], 'ipay88');
        expect(body['address_id'], 1);
        return {'order_id': 9, 'order_no': 'HB-1001', 'payment_method': 'ipay88', 'total_sen': 12345, 'sub_orders': [], 'payment_url': 'https://pay.test/x', 'expires_at': null};
      },
      'GET orders/HB-1001/payment': (_, _) => {'order_no': 'HB-1001', 'method': 'ipay88', 'status': 'pending', 'paid': false, 'expires_at': null, 'retry_url': 'https://pay.test/x'},
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/checkout');

    expect(find.text('RM 123.45'), findsNWidgets(2)); // totals block + sticky bar, never computed
    expect(find.text('RM 100.00'), findsNWidgets(2)); // line total + subtotal
    expect(find.text('RM 23.45'), findsWidgets); // shipping (store row + totals)
    expect(find.text('Buyer One · 0123456789'), findsOneWidget);
    expect(find.byKey(const Key('checkout-method-cod')), findsOneWidget);
    expect(find.byKey(const Key('checkout-method-reason-cod')), findsOneWidget);
    expect(find.text('Cash on delivery is capped at RM 100.00 per order.'), findsOneWidget);
    expect(find.byKey(const Key('checkout-method-reason-ipay88')), findsNothing);

    await tester.tap(find.byKey(const Key('checkout-place')));
    await tester.pumpAndSettle();
    expect(api.calls, contains('POST checkout/place'));
    expect(h.router.state.uri.path, '/checkout/pay/HB-1001');
    expect(find.byKey(const Key('pay-waiting')), findsOneWidget);
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
    expect(polls, 2);
    expect(h.router.state.uri.path, '/checkout/done/HB-7');
    expect(find.byKey(const Key('checkout-done')), findsOneWidget);
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
}
