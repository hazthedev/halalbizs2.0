import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/core/api/api_client.dart';

import '../helpers.dart';
import 'seller_helpers.dart';

void main() {
  for (final (name, location, endpoint, marker, data) in [
    ('dashboard', '/seller', 'seller/dashboard', 'seller-kpis', fakeDashboard()),
    ('earnings', '/seller/earnings', 'seller/earnings', 'seller-earnings-request-payout', fakeEarnings()),
    ('order detail', '/seller/orders/5', 'seller/orders/5', 'seller-order-pack', fakeSellerOrder()),
  ]) {
    for (final status in [403, 500]) {
      testWidgets('$name refresh $status: permission loss leaves, server failure keeps data', (tester) async {
        usePhone(tester);
        var fail = false;
        final api = FakeApi(routes: {
          ...sellerBaseRoutes(),
          'GET $endpoint': (_, _) => fail
              ? ApiException(status == 403 ? 'store_suspended' : 'server_error', 'Refresh failed.', status: status)
              : data,
        });
        final h = Harness(api: api, token: 'tok');
        await h.pump(tester, location: location);
        expect(find.byKey(Key(marker)), findsOneWidget);
        fail = true;
        await tester.fling(find.byKey(const Key('souk-page-scroll')), const Offset(0, 400), 1000);
        await tester.pumpAndSettle();
        expect(api.calls.where((c) => c == 'GET $endpoint'), hasLength(2));
        expect(h.router.state.uri.path, status == 403 ? '/account' : location);
        expect(find.byKey(Key(marker)), status == 403 ? findsNothing : findsOneWidget);
      });
    }
  }

  testWidgets('dashboard renders the KPIs with PriceText and the recent orders', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {...sellerBaseRoutes(), 'GET seller/dashboard': (_, _) => fakeDashboard()});
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/seller');

    expect(h.router.state.uri.path, '/seller');
    expect(find.byKey(const Key('seller-kpis')), findsOneWidget);
    expect(find.text('RM 1,234.50'), findsOneWidget); // today's sales from 123450
    expect(find.text('RM 500.00'), findsOneWidget); // available
    expect(find.text('11'), findsOneWidget); // today's orders
    expect(find.byKey(const Key('seller-status-confirmed')), findsOneWidget);
    expect(find.byKey(const Key('seller-status-processing')), findsNothing); // zero counts hidden
    expect(find.byKey(const Key('seller-order-5')), findsOneWidget);
    expect(find.byKey(const Key('seller-back-to-buying')), findsOneWidget);
  });

  testWidgets('a buyer without an approved store never reaches the seller shell', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {...sellerBaseRoutes(), 'GET me': (_, _) => fakeUser()});
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/seller');
    expect(h.router.state.uri.path, '/account');
    expect(api.calls.where((c) => c.startsWith('GET seller/')), isEmpty);
  });

  testWidgets('empty dashboard: both "No orders yet" states are centred (Haze 11-11)', (tester) async {
    usePhone(tester);
    // Wider than the copy, so a shrink-wrapped state would sit left (as on Haze's phone).
    tester.view.physicalSize = const Size(1800, 2700);
    tester.view.devicePixelRatio = 3;
    final empty = {...fakeDashboard(), 'orders_by_status': <String, int>{}, 'recent_orders': <dynamic>[]};
    final api = FakeApi(routes: {...sellerBaseRoutes(), 'GET seller/dashboard': (_, _) => empty});
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/seller');
    await tester.pumpAndSettle();

    final screenMid = tester.view.physicalSize.width / tester.view.devicePixelRatio / 2;
    for (final key in ['seller-dashboard-empty', 'seller-status-empty']) {
      final box = find.byKey(Key(key));
      await tester.ensureVisible(box);
      await tester.pumpAndSettle();
      final text = find.descendant(of: box, matching: find.byType(Text), matchRoot: true).first;
      expect((tester.getCenter(text).dx - screenMid).abs(), lessThan(2), reason: key);
    }
  });
}
