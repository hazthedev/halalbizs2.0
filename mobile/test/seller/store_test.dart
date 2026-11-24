import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/core/api/api_client.dart';

import '../helpers.dart';
import 'seller_helpers.dart';

void main() {
  for (final exit in ['seller-back-to-buying', 'seller-store-back-to-buying']) {
    testWidgets('$exit asks before discarding store edits', (tester) async {
      usePhone(tester);
      final api = FakeApi(routes: {
        ...sellerBaseRoutes(),
        'GET seller/store': (_, _) => fakeStore(),
        'GET seller/reviews': (_, _) => [],
      });
      final h = Harness(api: api, token: 'tok');
      await h.pump(tester, location: '/seller/store');
      await tester.enterText(find.byKey(const Key('seller-store-description')), 'Unsaved shop description');
      await tester.pump();
      final button = find.byKey(Key(exit));
      await tester.ensureVisible(button);
      await tester.pumpAndSettle();
      await tester.tap(button);
      await tester.pumpAndSettle();
      expect(find.text('Discard changes?'), findsOneWidget);
      await tester.tap(find.byKey(const Key('souk-confirm-cancel')));
      await tester.pumpAndSettle();
      expect(h.router.state.uri.path, '/seller/store');
      expect(find.text('Unsaved shop description'), findsOneWidget);
      await tester.ensureVisible(button);
      await tester.pumpAndSettle();
      await tester.tap(button);
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('souk-confirm-ok')));
      await tester.pumpAndSettle();
      expect(h.router.state.uri.path, '/home');
    });
  }


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

  testWidgets('a bank change asks for the current password, shows the refusal and keeps every edit', (tester) async {
    usePhone(tester);
    final sent = <Map>[];
    final api = FakeApi(routes: {
      ...sellerBaseRoutes(),
      'GET seller/store': (_, _) => fakeStore(),
      'GET seller/reviews': (_, _) => [],
      'PATCH seller/store': (_, body) {
        sent.add(body! as Map);
        if (sent.last['current_password'] != 'right-pass') {
          return ApiException('validation_failed', 'The given data was invalid.', status: 422, fields: {
            'current_password': ['That doesn\'t match your current password — try again.'],
          });
        }
        return fakeStore();
      },
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/seller/store');

    final password = find.byKey(const Key('seller-store-current-password'));
    expect(password, findsNothing); // no bank change, no password asked

    await tester.enterText(find.byKey(const Key('seller-store-description')), 'New description');
    await tester.enterText(find.byKey(const Key('seller-store-account-number')), '123456789012');
    await tester.pumpAndSettle();
    expect(password, findsOneWidget);

    await tester.tap(find.byKey(const Key('seller-store-save')));
    await tester.pumpAndSettle();
    expect(sent.single.containsKey('current_password'), isFalse); // blank = not sent; the server decides
    expect(find.text('That doesn\'t match your current password — try again.'), findsOneWidget);
    expect(find.text('New description'), findsOneWidget);
    expect(find.text('123456789012'), findsOneWidget);

    await tester.ensureVisible(password);
    await tester.pumpAndSettle();
    await tester.enterText(password, 'right-pass');
    await tester.tap(find.byKey(const Key('seller-store-save')));
    await tester.pumpAndSettle();
    expect(sent.last['current_password'], 'right-pass');
    expect(sent.last['description'], 'New description');
    expect(sent.last['bank'], {'bank_name': 'Bank Islam', 'account_name': 'Goodwin Ltd', 'account_number': '123456789012'});
    expect(find.text('Store saved'), findsOneWidget);
    expect(password, findsNothing); // saved: number cleared, password gone
  });

  testWidgets('a store 422 with no field on screen is still shown', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      ...sellerBaseRoutes(),
      'GET seller/store': (_, _) => fakeStore(),
      'GET seller/reviews': (_, _) => [],
      'PATCH seller/store': (_, _) => ApiException('validation_failed', 'The given data was invalid.', status: 422, fields: {
            'holiday_mode': ['The holiday mode field must be true or false.'],
          }),
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/seller/store');
    await tester.tap(find.byKey(const Key('seller-store-save')));
    await tester.pumpAndSettle();
    expect(find.textContaining('The holiday mode field must be true or false.'), findsOneWidget);
  });

  testWidgets('a Google-only seller sees the set-a-password hint on the bank form and it opens Forgot password', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      ...sellerBaseRoutes(),
      'GET me': (_, _) => {...fakeUser(storeStatus: 'approved'), 'password_set': false},
      'GET seller/store': (_, _) => fakeStore(),
      'GET seller/reviews': (_, _) => [],
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/seller/store');

    expect(
      find.text('You signed up with Google and have not set a password yet. Use Forgot password to set one, then change your bank details.'),
      findsOneWidget,
    );
    final link = find.byKey(const Key('seller-store-forgot-password'));
    await tester.ensureVisible(link);
    await tester.pumpAndSettle();
    await tester.tap(link);
    await tester.pumpAndSettle();
    expect(h.router.state.uri.path, '/forgot-password');
  });

  for (final passwordSet in [true, null]) {
    testWidgets('a seller with a password (password_set: $passwordSet) never sees the Google hint', (tester) async {
      usePhone(tester);
      final api = FakeApi(routes: {
        ...sellerBaseRoutes(),
        'GET me': (_, _) => {...fakeUser(storeStatus: 'approved'), 'password_set': ?passwordSet},
        'GET seller/store': (_, _) => fakeStore(),
        'GET seller/reviews': (_, _) => [],
      });
      final h = Harness(api: api, token: 'tok');
      await h.pump(tester, location: '/seller/store');

      expect(find.byKey(const Key('seller-store-google-hint')), findsNothing);
      expect(find.byKey(const Key('seller-store-forgot-password')), findsNothing);
    });
  }
}
