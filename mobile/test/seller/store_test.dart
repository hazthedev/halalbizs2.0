import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';
import 'seller_helpers.dart';

void main() {
  testWidgets('store form shows the masked bank number and only sends bank when a full number is typed', (tester) async {
    usePhone(tester);
    Object? sent;
    final api = FakeApi(routes: {
      ...sellerBaseRoutes(),
      'GET seller/store': (_, _) => fakeStore(),
      'GET seller/reviews': (_, _) => [
            {'id': 11, 'rating': 4, 'body': 'Great honey.', 'buyer_name': 'Lim L.', 'product': {'id': 7, 'name': 'Madu', 'thumb': null}, 'photos': [], 'is_hidden': false, 'created_at': '2026-11-07T07:52:17+00:00', 'reply': null, 'can_reply': true},
          ],
      'PATCH seller/store': (_, body) {
        sent = body;
        return fakeStore();
      },
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/seller/store');

    expect(find.text('Goodwin Ltd'), findsWidgets);
    expect(find.text('******8705'), findsOneWidget); // hint = the server-masked number
    expect(find.byKey(const Key('seller-review-11')), findsOneWidget);
    expect(find.byKey(const Key('seller-store-back-to-buying')), findsOneWidget);

    await tester.tap(find.byKey(const Key('seller-store-save'))); // pinned: no scrolling needed
    await tester.pumpAndSettle();
    var body = sent! as Map;
    expect(body.containsKey('bank'), isFalse);
    expect(body['shipping_flat_fee_sen'], 500);
    expect(body['state'], 'Pahang');

    await tester.enterText(find.byKey(const Key('seller-store-account-number')), '123456789012');
    await tester.pump(const Duration(seconds: 5)); // let the "Store saved" snackbar leave the pinned save
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('seller-store-save')));
    await tester.pumpAndSettle();
    body = sent! as Map;
    expect(body['bank'], {'bank_name': 'Bank Islam', 'account_name': 'Goodwin Ltd', 'account_number': '123456789012'});
    expect(find.text('Store saved'), findsOneWidget);
  });

  testWidgets('Halal certificates and Vouchers open the one-time seller web links', (tester) async {
    usePhone(tester);
    final tos = <Object?>[];
    final api = FakeApi(routes: {
      ...sellerBaseRoutes(),
      'GET seller/store': (_, _) => fakeStore(),
      'GET seller/reviews': (_, _) => [],
      'POST me/web-sign-in-link': (_, body) {
        tos.add((body as Map)['to']);
        return {'url': 'https://web.test/app/sign-in?to=${body['to']}', 'expires_at': '2026-11-10T12:05:00Z'};
      },
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/seller/store');

    for (final key in ['seller-store-certificates', 'seller-store-vouchers']) {
      await tester.scrollUntilVisible(find.byKey(Key(key)), 300, scrollable: find.descendant(of: find.byKey(const Key('souk-page-scroll')), matching: find.byType(Scrollable)).first);
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(Key(key)));
      await tester.pumpAndSettle();
    }
    expect(tos, ['seller-certificates', 'seller-vouchers']);
    expect(h.browser.opened.length, 2);
  });
}
