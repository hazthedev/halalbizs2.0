import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/core/api/api_client.dart';

import '../helpers.dart';
import 'seller_helpers.dart';

void main() {
  testWidgets('order detail shows Pack only for a confirmed sub-order', (tester) async {
    usePhone(tester);
    var status = 'confirmed';
    final api = FakeApi(routes: {
      ...sellerBaseRoutes(),
      'GET seller/orders/5': (_, _) => fakeSellerOrder(status: status),
      'GET seller/orders/6': (_, _) => fakeSellerOrder(id: 6, status: 'shipped'),
      'POST seller/orders/5/pack': (_, _) => fakeSellerOrder(status: status = 'processing'),
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/seller/orders/5');

    expect(find.byKey(const Key('seller-order-pack')), findsOneWidget);
    expect(find.byKey(const Key('seller-order-ship')), findsNothing);
    expect(find.byKey(const Key('seller-order-delivered')), findsNothing);
    expect(find.text('RM 30.00'), findsOneWidget); // total
    expect(find.text('Lim Mei Ling'), findsOneWidget);

    await tester.tap(find.byKey(const Key('seller-order-pack')));
    await tester.pumpAndSettle();
    expect(api.calls, contains('POST seller/orders/5/pack'));
    expect(find.byKey(const Key('seller-order-pack')), findsNothing);
    expect(find.byKey(const Key('seller-order-ship')), findsOneWidget);

    h.router.go('/seller/orders/6');
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('seller-order-pack')), findsNothing);
    expect(find.byKey(const Key('seller-order-delivered')), findsOneWidget);
  });

  testWidgets('ship sheet validates the tracking length and sends courier + tracking_no', (tester) async {
    usePhone(tester);
    Object? sent;
    var status = 'processing';
    final api = FakeApi(routes: {
      ...sellerBaseRoutes(),
      'GET seller/orders/5': (_, _) => fakeSellerOrder(status: status),
      'POST seller/orders/5/ship': (_, body) {
        sent = body;
        return fakeSellerOrder(status: status = 'shipped');
      },
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/seller/orders/5');

    await tester.tap(find.byKey(const Key('seller-order-ship')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('seller-ship-submit')), findsOneWidget);

    await tester.enterText(find.byKey(const Key('seller-ship-tracking')), 'ABC');
    await tester.tap(find.byKey(const Key('seller-ship-submit')));
    await tester.pumpAndSettle();
    expect(find.text('Enter 6 to 64 characters.'), findsWidgets);
    expect(find.text('This field is required.'), findsOneWidget); // courier
    expect(sent, isNull);

    await tester.tap(find.byKey(const Key('seller-ship-courier')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Pos Laju').last);
    await tester.pumpAndSettle();
    await tester.enterText(find.byKey(const Key('seller-ship-tracking')), 'MY123456789');
    await tester.tap(find.byKey(const Key('seller-ship-submit')));
    await tester.pumpAndSettle();

    expect(sent, {'courier': 'Pos Laju', 'tracking_no': 'MY123456789'});
    expect(find.byKey(const Key('seller-ship-submit')), findsNothing);
    expect(find.byKey(const Key('seller-order-delivered')), findsOneWidget);
  });

  testWidgets('409 invalid_transition shows the message and reloads the order', (tester) async {
    usePhone(tester);
    var status = 'confirmed';
    final api = FakeApi(routes: {
      ...sellerBaseRoutes(),
      'GET seller/orders/5': (_, _) => fakeSellerOrder(status: status),
      'POST seller/orders/5/pack': (_, _) {
        status = 'cancelled';
        return ApiException('invalid_transition', 'Cannot move from cancelled to processing.', status: 409);
      },
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/seller/orders/5');
    await tester.tap(find.byKey(const Key('seller-order-pack')));
    await tester.pumpAndSettle();
    expect(find.text('Cannot move from cancelled to processing.'), findsOneWidget);
    expect(api.calls.where((c) => c == 'GET seller/orders/5').length, 2);
    expect(find.byKey(const Key('seller-order-pack')), findsNothing);
  });

  testWidgets('return block offers Accept / Dispute, then Confirm received once accepted', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      ...sellerBaseRoutes(),
      'GET seller/orders/5': (_, _) => fakeSellerOrder(
            status: 'return_requested',
            returnRequest: {'id': 1, 'status': 'requested', 'status_label': 'Requested', 'reason': 'Damaged', 'description': null, 'respond_by': null},
          ),
      'GET seller/orders/6': (_, _) => fakeSellerOrder(
            id: 6,
            status: 'return_requested',
            returnRequest: {'id': 2, 'status': 'accepted', 'status_label': 'Accepted', 'reason': 'Damaged', 'description': null, 'respond_by': null},
          ),
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/seller/orders/5');
    expect(find.byKey(const Key('seller-order-accept')), findsOneWidget);
    expect(find.byKey(const Key('seller-order-dispute')), findsOneWidget);
    expect(find.byKey(const Key('seller-order-return')), findsOneWidget);
    h.router.go('/seller/orders/6');
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('seller-order-received')), findsOneWidget);
    expect(find.byKey(const Key('seller-order-accept')), findsNothing);
  });
}
