import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';
import 'seller_helpers.dart';

void main() {
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
}
