import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/core/locale.dart';

import '../helpers.dart';
import 'seller_helpers.dart';

/// Real-phone sweep 2026-11-10 (projects/halalbizs2.0/reports/phone-visual-sweep-2026-11-10.md), seller rows.
void main() {
  testWidgets('row 14: Back to buying is a labelled button in EN and BM', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {...sellerBaseRoutes(), 'GET seller/dashboard': (_, _) => fakeDashboard()});
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/seller');
    final button = find.byKey(const Key('seller-back-to-buying'));
    expect(find.descendant(of: button, matching: find.text('Back to buying')), findsOneWidget);

    await h.container.read(localeProvider.notifier).set(const Locale('ms'));
    await tester.pumpAndSettle();
    expect(find.descendant(of: button, matching: find.text('Kembali membeli')), findsOneWidget);
  });

  testWidgets('row 15: an unsettled commission shows its amount, marked as an estimate', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      ...sellerBaseRoutes(),
      'GET seller/orders/5': (_, _) => {...fakeSellerOrder(), 'commission_sen': 125, 'commission_settled': false},
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/seller/orders/5');
    final totals = find.byKey(const Key('seller-order-totals'));
    expect(find.descendant(of: totals, matching: find.text('—')), findsNothing);
    expect(find.descendant(of: totals, matching: find.text('-RM 1.25')), findsOneWidget);
    expect(find.descendant(of: totals, matching: find.text('Estimated until the order completes.')), findsOneWidget);
  });

  testWidgets('row 18: Buyer shows the account name, Ship to the address name, both full width', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      ...sellerBaseRoutes(),
      'GET seller/orders/5': (_, _) {
        final o = fakeSellerOrder();
        return {
          ...o,
          'buyer': {'name': 'Demo Buyer', 'phone': '+60123456789'},
          'address': {...(o['address'] as Map), 'recipient_name': 'Jerod Price', 'postcode': '56457', 'city': 'Perryville', 'state': 'Johor'},
        };
      },
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/seller/orders/5');

    final buyer = find.byKey(const Key('seller-order-buyer'));
    final shipTo = find.byKey(const Key('seller-order-ship-to'));
    expect(find.descendant(of: buyer, matching: find.text('Demo Buyer')), findsOneWidget);
    expect(find.descendant(of: buyer, matching: find.text('Jerod Price')), findsNothing);
    expect(find.descendant(of: shipTo, matching: find.text('Jerod Price')), findsOneWidget);
    expect(find.descendant(of: shipTo, matching: find.text('56457 Perryville, Johor')), findsOneWidget);
    expect(find.text('Ship to'), findsOneWidget);

    final page = tester.getRect(find.byKey(const Key('seller-order-totals'))).width;
    expect(tester.getRect(buyer).width, page);
    expect(tester.getRect(shipTo).width, page);
  });

  group('row 16: store settings has one labelled save for the whole form', () {
    Future<(FakeApi, List<Map>)> open(WidgetTester tester) async {
      usePhone(tester);
      final sent = <Map>[];
      final api = FakeApi(routes: {
        ...sellerBaseRoutes(),
        'GET seller/store': (_, _) => fakeStore(),
        'GET seller/reviews': (_, _) => [],
        'PATCH seller/store': (_, body) {
          sent.add(body! as Map);
          return fakeStore();
        },
      });
      await Harness(api: api, token: 'tok').pump(tester, location: '/seller/store');
      return (api, sent);
    }

    Future<void> save(WidgetTester tester) async {
      await tester.tap(find.byKey(const Key('seller-store-save')));
      await tester.pumpAndSettle();
    }

    testWidgets('the pinned save says what it saves and sends every field above the bank', (tester) async {
      final (_, sent) = await open(tester);
      final button = find.byKey(const Key('seller-store-save'));
      expect(find.descendant(of: button, matching: find.text('Save store settings')), findsOneWidget);
      // Pinned: on screen at the top of the page, before any scrolling.
      expect(tester.getRect(button).bottom, lessThanOrEqualTo(844));

      await tester.enterText(find.byKey(const Key('seller-store-description')), 'New blurb.');
      await tester.tap(find.byKey(const Key('seller-store-holiday')));
      await tester.pumpAndSettle();
      await tester.enterText(find.byKey(const Key('seller-store-flat-fee')), '7.50');
      await tester.enterText(find.byKey(const Key('seller-store-free-above')), '100');
      await tester.ensureVisible(find.byKey(const Key('seller-store-state')));
      await tester.pumpAndSettle();
      await tester.tap(find.descendant(of: find.byKey(const Key('seller-store-state')), matching: find.text('Pahang')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Perak').last);
      await tester.pumpAndSettle();
      await save(tester);

      expect(sent.single, {
        'holiday_mode': true,
        'description': 'New blurb.',
        'state': 'Perak',
        'shipping_mode': 'flat',
        'shipping_flat_fee_sen': 750,
        'free_shipping_over_sen': 10000,
      });
    });

    testWidgets('a changed account name without the full number is refused, not dropped', (tester) async {
      final (_, sent) = await open(tester);
      await tester.enterText(find.byKey(const Key('seller-store-account-name')), 'Goodwin Sdn Bhd');
      await save(tester);
      expect(sent, isEmpty);
      expect(find.text('Re-enter the full account number to change bank details.'), findsOneWidget);
    });

    testWidgets('a cleared description is refused, not dropped', (tester) async {
      final (_, sent) = await open(tester);
      await tester.enterText(find.byKey(const Key('seller-store-description')), '');
      await save(tester);
      expect(sent, isEmpty);
      expect(find.text('This field is required.'), findsOneWidget);
    });
  });
}
