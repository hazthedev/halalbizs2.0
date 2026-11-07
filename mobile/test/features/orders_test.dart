import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/core/api/api_client.dart';

import '../helpers.dart';

/// `SubOrderResource` sample (list shape; `detail: true` adds tracking, history, address).
Map<String, dynamic> fakeSubOrder({
  int id = 5,
  String status = 'confirmed',
  String label = 'Confirmed',
  Map<String, bool> can = const {'cancel': true, 'confirm_received': false, 'return': false, 'review': false},
  bool detail = false,
}) =>
    {
      'id': id,
      'sub_order_no': 'HB-1001-$id',
      'order_no': 'HB-1001',
      'status': status,
      'status_label': label,
      'store': {'id': 3, 'slug': 'kedai-madu', 'name': 'Kedai Madu', 'logo': null},
      'items': [
        {'id': 51, 'product_id': 1, 'product_slug': 'madu-tualang', 'name': 'Madu Tualang Asli', 'variant_label': '500g', 'qty': 2, 'unit_price_sen': 1250, 'line_total_sen': 2500, 'product_image': null, 'reviewed': false},
        {'id': 52, 'product_id': 2, 'product_slug': 'kurma-ajwa', 'name': 'Kurma Ajwa Premium', 'variant_label': null, 'qty': 1, 'unit_price_sen': 3200, 'line_total_sen': 3200, 'product_image': null, 'reviewed': false},
      ],
      'subtotal_sen': 5700,
      'shipping_sen': 800,
      'discount_sen': 0,
      'total_sen': 6500,
      'payment': {'method': 'cod', 'status': 'pending'},
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
}
