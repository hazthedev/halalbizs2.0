import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/core/api/api_client.dart';
import 'package:halalbizs/features/checkout/pay_screen.dart';

import '../helpers.dart';

/// `SubOrderResource` sample (list shape; `detail: true` adds tracking, history, address).
Map<String, dynamic> fakeSubOrder({
  int id = 5,
  String status = 'confirmed',
  String label = 'Confirmed',
  Map<String, bool> can = const {'cancel': true, 'confirm_received': false, 'return': false, 'review': false},
  bool detail = false,
  Map<String, String> payment = const {'method': 'cod', 'status': 'pending'},
}) =>
    {
      'id': id,
      'sub_order_no': 'HB-1001-$id',
      'order_no': 'HB-1001',
      'status': status,
      'status_label': label,
      'store': {'id': 3, 'slug': 'kedai-madu', 'name': 'Kedai Madu', 'logo': null},
      'items': [
        {'id': 51, 'product_id': 1, 'variant_id': 10, 'product_slug': 'madu-tualang', 'name': 'Madu Tualang Asli', 'variant_label': '500g', 'qty': 2, 'unit_price_sen': 1250, 'line_total_sen': 2500, 'product_image': null, 'reviewed': false},
        {'id': 52, 'product_id': 2, 'variant_id': 20, 'product_slug': 'kurma-ajwa', 'name': 'Kurma Ajwa Premium', 'variant_label': null, 'qty': 1, 'unit_price_sen': 3200, 'line_total_sen': 3200, 'product_image': null, 'reviewed': false},
      ],
      'subtotal_sen': 5700,
      'shipping_sen': 800,
      'discount_sen': 0,
      'total_sen': 6500,
      'payment': payment,
      'placed_at': '2026-11-07T06:05:00Z',
      'can': can,
      'return_request': null,
      if (detail) ...{
        'tracking': {'courier': 'J&T', 'no': 'JT123', 'url': 'https://track.test/JT123', 'status': 'in_transit'},
        'history': [
          {'status': 'pending_payment', 'label': 'Pending payment', 'at': '2026-11-07T06:05:00Z', 'note': null},
          {'status': 'confirmed', 'label': 'Confirmed', 'at': '2026-11-07T06:06:00Z', 'note': 'COD'},
        ],
        'address': {'recipient_name': 'Buyer One', 'phone': '0123456789', 'line1': '12 Jalan Satu', 'line2': null, 'postcode': '50450', 'city': 'Kuala Lumpur', 'state': 'Kuala Lumpur'},
      },
    };

void main() {
  testWidgets('orders list renders the buckets with counts and one card per sub-order', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET me': (_, _) => fakeUser(),
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET orders/buckets': (_, _) => [
            {'key': 'to-pay', 'label': 'To Pay', 'count': 0},
            {'key': 'to-ship', 'label': 'To Ship', 'count': 2},
            {'key': 'completed', 'label': 'Completed', 'count': 0},
          ],
      'GET orders': (q, _) => [fakeSubOrder(id: 5), fakeSubOrder(id: 6, status: 'shipped', label: 'Shipped')],
    }, meta: {'page': 1, 'per_page': 10, 'total': 2, 'last_page': 1});
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/orders');

    expect(find.byKey(const Key('orders-bucket-to-ship')), findsOneWidget);
    expect(find.text('To Ship · 2'), findsOneWidget);
    expect(find.byKey(const Key('order-card-5')), findsOneWidget);
    expect(find.byKey(const Key('order-card-6')), findsOneWidget);
    expect(find.text('+1 more'), findsNWidgets(2));
    expect(find.text('RM 65.00'), findsNWidgets(2));
    expect(find.byKey(const Key('orders-load-more')), findsNothing);

    await tester.tap(find.byKey(const Key('orders-bucket-to-ship')));
    await tester.pumpAndSettle();
    expect(api.calls, contains('GET orders?bucket=to-ship'));
  });

  testWidgets('order detail shows only the actions can{} allows, and cancel posts the reason', (tester) async {
    usePhone(tester);
    var detail = fakeSubOrder(detail: true);
    Object? cancelBody;
    final api = FakeApi(routes: {
      'GET me': (_, _) => fakeUser(),
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET orders/buckets': (_, _) => [],
      'GET orders/5': (_, _) => detail,
      'POST orders/5/cancel': (_, body) {
        cancelBody = body;
        return detail = fakeSubOrder(status: 'cancelled', label: 'Cancelled', can: const {'cancel': false, 'confirm_received': false, 'return': false, 'review': false}, detail: true);
      },
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/orders/5');

    expect(find.byKey(const Key('order-status')), findsOneWidget);
    expect(find.byKey(const Key('order-timeline')), findsOneWidget);
    expect(find.byKey(const Key('order-tracking-no')), findsOneWidget);
    expect(find.text('JT123'), findsOneWidget);
    expect(find.byKey(const Key('order-cancel')), findsOneWidget);
    expect(find.byKey(const Key('order-confirm-received')), findsNothing);
    expect(find.byKey(const Key('order-return')), findsNothing);
    expect(find.byKey(const Key('order-review')), findsNothing);

    await tester.tap(find.byKey(const Key('order-cancel')));
    await tester.pumpAndSettle();
    await tester.enterText(find.byKey(const Key('orders-cancel-reason')), 'Changed my mind');
    await tester.tap(find.byKey(const Key('souk-confirm-ok')));
    await tester.pumpAndSettle();
    expect(api.calls, contains('POST orders/5/cancel'));
    expect((cancelBody as Map?)?['reason'], 'Changed my mind');
    expect(find.byKey(const Key('order-cancel')), findsNothing);
    expect(find.text('CANCELLED'), findsOneWidget);
  });

  testWidgets('delivered order offers confirm received; a 409 invalid_transition is surfaced', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET me': (_, _) => fakeUser(),
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET orders/buckets': (_, _) => [],
      'GET orders/7': (_, _) => fakeSubOrder(id: 7, status: 'delivered', label: 'Delivered', can: const {'cancel': false, 'confirm_received': true, 'return': true, 'review': false}, detail: true),
      'POST orders/7/confirm-received': (_, _) => ApiException('invalid_transition', 'This order can no longer be changed that way.', status: 409),
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/orders/7');
    expect(find.byKey(const Key('order-confirm-received')), findsOneWidget);
    expect(find.byKey(const Key('order-return')), findsOneWidget);
    expect(find.byKey(const Key('order-cancel')), findsNothing);

    await tester.tap(find.byKey(const Key('order-confirm-received')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('souk-confirm-ok')));
    await tester.pumpAndSettle();
    expect(find.text('This order can no longer be changed that way.'), findsOneWidget);
  });

  testWidgets('a shop order is named by its SO number and shop on the card, and by Order SO / Payment MP on the detail', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET me': (_, _) => fakeUser(),
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET orders/buckets': (_, _) => [],
      'GET orders': (_, _) => [fakeSubOrder(id: 5)],
      'GET orders/5': (_, _) => fakeSubOrder(id: 5, detail: true),
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/orders');

    final card = find.byKey(const Key('order-card-5'));
    expect(find.descendant(of: card, matching: find.text('Kedai Madu')), findsOneWidget);
    expect(find.descendant(of: card, matching: find.text('HB-1001-5')), findsOneWidget);

    await tester.tap(card);
    await tester.pumpAndSettle();
    expect(find.text('Order HB-1001-5'), findsOneWidget);
    expect(find.text('Payment HB-1001'), findsOneWidget);
  });

  testWidgets('an empty filter tab says "No orders in this status", not "No orders yet"', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET me': (_, _) => fakeUser(),
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET orders/buckets': (_, _) => [
            {'key': 'to-pay', 'label': 'To Pay', 'count': 0},
          ],
      'GET orders': (q, _) => q?['bucket'] == null ? [fakeSubOrder(id: 5)] : [],
    }, meta: {'page': 1, 'per_page': 10, 'total': 1, 'last_page': 1});
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/orders');
    await tester.tap(find.byKey(const Key('orders-bucket-to-pay')));
    await tester.pumpAndSettle();
    expect(find.text('No orders in this status'), findsOneWidget);
    expect(find.text('No orders yet'), findsNothing);
  });

  testWidgets('no orders at all still says "No orders yet"', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET me': (_, _) => fakeUser(),
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET orders/buckets': (_, _) => [],
      'GET orders': (_, _) => [],
    }, meta: {'page': 1, 'per_page': 10, 'total': 0, 'last_page': 1});
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/orders');
    expect(find.text('No orders yet'), findsOneWidget);
  });

  Map<String, FakeHandler> detailRoutes(Map<String, FakeHandler> extra) => {
        'GET me': (_, _) => fakeUser(),
        'GET products': (_, _) => [],
        'GET categories': (_, _) => [],
        'GET orders/buckets': (_, _) => [],
        'GET orders': (_, _) => [],
        ...extra,
      };

  testWidgets('Pay now shows only on an unpaid online order and opens retry_url, then back to the order', (tester) async {
    usePhone(tester);
    final poll = PayScreen.pollEvery;
    PayScreen.pollEvery = const Duration(hours: 1);
    addTearDown(() => PayScreen.pollEvery = poll);
    final unpaid = fakeSubOrder(
        id: 8, status: 'pending_payment', label: 'Pending payment', detail: true, payment: const {'method': 'ipay88', 'status': 'pending'});
    final api = FakeApi(routes: detailRoutes({
      'GET orders/8': (_, _) => unpaid,
      'GET orders/9': (_, _) => fakeSubOrder(id: 9, status: 'confirmed', label: 'Confirmed', detail: true),
      'GET orders/HB-1001/payment': (_, _) =>
          {'order_no': 'HB-1001', 'method': 'ipay88', 'status': 'pending', 'paid': false, 'expires_at': null, 'retry_url': 'https://pay.test/retry'},
    }));
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/orders/9');
    expect(find.byKey(const Key('order-pay-now')), findsNothing, reason: 'a confirmed (COD) order has nothing to pay');

    h.router.go('/orders/8');
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('order-pay-now')), findsOneWidget);
    await tester.tap(find.byKey(const Key('order-pay-now')));
    await tester.pumpAndSettle();
    expect(api.calls, contains('GET orders/HB-1001/payment'));
    expect(h.browser.opened, ['https://pay.test/retry'], reason: 'same in-app browser sheet as checkout');
    expect(h.router.state.uri.path, '/checkout/pay/HB-1001');
    expect(api.calls.where((c) => c.startsWith('POST checkout/quote')), isEmpty, reason: 'pushing pay must not build the checkout under it');

    await tester.tap(find.byKey(const Key('pay-close')));
    await tester.pumpAndSettle();
    expect(h.router.state.uri.path, '/orders/8', reason: 'closing the pay screen goes back to the order');
  });

  testWidgets('Pay now on a lapsed order says it expired and opens nothing', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: detailRoutes({
      'GET orders/8': (_, _) => fakeSubOrder(
          id: 8, status: 'pending_payment', label: 'Payment expired', detail: true, payment: const {'method': 'ipay88', 'status': 'pending'}),
      'GET orders/HB-1001/payment': (_, _) =>
          {'order_no': 'HB-1001', 'method': 'ipay88', 'status': 'pending', 'paid': false, 'expires_at': '2026-01-01T00:00:00Z', 'retry_url': null},
    }));
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/orders/8');
    await tester.tap(find.byKey(const Key('order-pay-now')));
    await tester.pumpAndSettle();
    expect(h.browser.opened, isEmpty);
    expect(h.router.state.uri.path, '/orders/8');
    expect(find.text('This order was not paid in time. Nothing was charged.'), findsOneWidget);
  });

  testWidgets('Buy again shows only on a completed order, adds each item, skips the unavailable and opens Cart', (tester) async {
    usePhone(tester);
    final completed = fakeSubOrder(
        id: 6, status: 'completed', label: 'Completed', detail: true, can: const {'cancel': false, 'confirm_received': false, 'return': false, 'review': false});
    (completed['items'] as List).add(
        {'id': 53, 'product_id': 3, 'variant_id': null, 'product_slug': null, 'name': 'Gone Product', 'variant_label': null, 'qty': 1, 'unit_price_sen': 100, 'line_total_sen': 100, 'product_image': null, 'reviewed': false});
    final added = <Object?>[];
    final cart = {'groups': [], 'item_count': 2, 'selected_count': 0, 'selected_subtotal_sen': 0, 'purchasing_enabled': true};
    final api = FakeApi(routes: detailRoutes({
      'GET orders/6': (_, _) => completed,
      'GET orders/5': (_, _) => fakeSubOrder(id: 5, detail: true),
      'GET cart': (_, _) => cart,
      'POST cart/items': (_, body) {
        if ((body as Map)['variant_id'] == 20) return ApiException('validation_failed', 'This product is sold out.', status: 422);
        added.add(body);
        return cart;
      },
    }));
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/orders/5');
    expect(find.byKey(const Key('order-buy-again')), findsNothing, reason: 'only completed orders offer Buy again');

    h.router.go('/orders/6');
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('order-buy-again')));
    await tester.pumpAndSettle();
    expect(added, [
      {'variant_id': 10, 'qty': 2},
    ]);
    expect(api.calls.where((c) => c == 'POST cart/items').length, 2, reason: 'the deleted variant is never sent');
    expect(h.router.state.uri.path, '/cart');
    expect(find.text('1 item added to cart · 2 items skipped (not available)'), findsOneWidget);
  });

  testWidgets('Account shows the four order shortcuts with counts; tap opens Orders on that tab', (tester) async {
    usePhone(tester);
    String? bucket;
    final api = FakeApi(routes: {
      'GET me': (_, _) => fakeUser(),
      'GET conversations': (_, _) => [],
      'GET orders/quick-status': (_, _) => [
            {'key': 'to-pay', 'label': 'To Pay', 'count': 2},
            {'key': 'to-ship', 'label': 'To Ship', 'count': 0},
            {'key': 'to-receive', 'label': 'To Receive', 'count': 1},
            {'key': 'completed', 'label': 'Completed', 'count': 7},
          ],
      'GET orders/buckets': (_, _) => [
            {'key': 'to-pay', 'label': 'To Pay', 'count': 2},
            {'key': 'to-ship', 'label': 'To Ship', 'count': 0},
          ],
      'GET orders': (q, _) {
        bucket = q?['bucket'] as String?;
        return [fakeSubOrder()];
      },
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/account');
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('account-order-shortcuts')), findsOneWidget);
    expect(find.text('To Pay'), findsOneWidget);
    expect(find.text('Completed'), findsOneWidget);
    expect(find.descendant(of: find.byKey(const Key('order-shortcut-to-pay-count')), matching: find.text('2')), findsOneWidget);
    expect(find.byKey(const Key('order-shortcut-to-ship-count')), findsNothing); // zero hides

    await tester.tap(find.byKey(const Key('order-shortcut-to-receive')));
    await tester.pumpAndSettle();
    expect(h.router.state.uri.toString(), '/orders?bucket=to-receive');
    expect(bucket, 'to-receive');
  });
}
