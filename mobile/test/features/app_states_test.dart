import 'dart:convert';

import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/core/api/api_client.dart';
import 'package:halalbizs/core/auth/session.dart';
import 'package:halalbizs/core/db/app_db.dart';
import 'package:halalbizs/core/widgets/souk_kit.dart';
import 'package:halalbizs/features/checkout/pay_screen.dart';

import '../helpers.dart';
import '../seller/seller_helpers.dart' show fakeSellerProduct, sellerBaseRoutes;
import 'account_test.dart' show realMe;
import 'orders_test.dart' show fakeSubOrder;
import 'home_test.dart' show fakeHome;
import 'checkout_test.dart' show checkoutRoutes, fakeAddress, fakeQuote;

/// HB-APP-STATES top 10 (audit 2026-11-17): one test per gap.
void main() {
  group('gap 1: a save with no field errors is never silent', () {
    test('fieldsCovered is false when the error carries no field list', () {
      expect(fieldsCovered(ApiException('server_error', 'Boom', status: 500), const ['name']), isFalse);
      expect(fieldsCovered(ApiException.timeout(), const ['name']), isFalse);
      expect(
          fieldsCovered(
              ApiException('validation_failed', 'x', status: 422, fields: {
                'name': ['Required'],
              }),
              const ['name']),
          isTrue);
    });

    testWidgets('delete account shows why the server blocked it (409)', (tester) async {
      usePhone(tester);
      final api = FakeApi(routes: {
        'GET me': (_, _) => realMe(),
        'GET products': (_, _) => [],
        'GET categories': (_, _) => [],
        'DELETE me': (_, _) =>
            ApiException('account_deletion_blocked', 'You have orders still in progress.', status: 409),
      });
      final h = Harness(api: api, token: 'tok');
      await h.pump(tester, location: '/account/delete');

      await tester.enterText(find.byKey(const Key('delete-password')), 'password');
      await tester.tap(find.byKey(const Key('delete-submit')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('souk-confirm-ok')));
      await tester.pumpAndSettle();

      expect(find.text('You have orders still in progress.'), findsOneWidget);
    });
  });

  group('gap 2: a failed payment is shown and polling stops', () {
    Map<String, dynamic> pending(String attempt) =>
        {'order_no': 'HB-5', 'method': 'ipay88', 'status': 'pending', 'paid': false, 'attempt_status': attempt, 'expires_at': null, 'retry_url': 'https://pay.test/again'};

    testWidgets('a failed attempt says so and offers Retry payment', (tester) async {
      usePhone(tester);
      final api = FakeApi(routes: {
        'GET me': (_, _) => fakeUser(),
        'GET products': (_, _) => [],
        'GET categories': (_, _) => [],
        'GET orders/HB-5/payment': (_, _) => pending('failed'),
      });
      final h = Harness(api: api, token: 'tok');
      await h.pump(tester, location: '/checkout/pay/HB-5');

      expect(find.byKey(const Key('pay-failed')), findsOneWidget);
      expect(find.byKey(const Key('pay-waiting')), findsNothing);
      expect(find.text('Retry payment'), findsOneWidget);
      await tester.tap(find.byKey(const Key('pay-retry')));
      await tester.pumpAndSettle();
      expect(h.browser.opened, contains('https://pay.test/again'));
    });

    testWidgets('back from the payment page and still unpaid says it is not confirmed yet', (tester) async {
      usePhone(tester);
      final api = FakeApi(routes: {
        'GET me': (_, _) => fakeUser(),
        'GET products': (_, _) => [],
        'GET categories': (_, _) => [],
        'GET orders/HB-5/payment': (_, _) => pending('pending'),
      });
      final h = Harness(api: api, token: 'tok');
      await h.pump(tester, location: '/checkout/pay/HB-5?returned=1');
      expect(find.byKey(const Key('pay-unconfirmed')), findsOneWidget);
      expect(find.byKey(const Key('pay-retry')), findsOneWidget);
    });

    testWidgets('polling stops after PayScreen.pollFor', (tester) async {
      usePhone(tester);
      var polls = 0;
      final api = FakeApi(routes: {
        'GET me': (_, _) => fakeUser(),
        'GET products': (_, _) => [],
        'GET categories': (_, _) => [],
        'GET orders/HB-5/payment': (_, _) {
          polls++;
          return pending('pending');
        },
      });
      final h = Harness(api: api, token: 'tok');
      await h.pump(tester, location: '/checkout/pay/HB-5');
      await tester.pump(PayScreen.pollFor);
      final atCap = polls;
      await tester.pump(PayScreen.pollEvery * 5);
      expect(polls, atCap, reason: 'no polls after the cap');
      expect(find.byKey(const Key('pay-paid')), findsOneWidget, reason: 'manual check stays');
    });
  });

  group('gap 3: the total on screen is the total charged', () {
    testWidgets('typed coins re-quote before Place works, and Place sends the quoted coins', (tester) async {
      usePhone(tester);
      Object? placeBody;
      final api = FakeApi(routes: checkoutRoutes({
        'POST checkout/quote': (_, body) {
          final coins = ((body as Map?)?['coins'] as int? ?? 0).clamp(0, 100);
          return fakeQuote()
            ..['coins'] = {'enabled': true, 'balance': 100, 'redeemed': coins, 'redemption_sen': coins}
            ..['coin_redemption_sen'] = coins
            ..['grand_total_sen'] = 12345 - coins;
        },
        'POST checkout/place': (_, body) {
          placeBody = body;
          return {'order_id': 9, 'order_no': 'HB-1001', 'payment_method': 'ipay88', 'total_sen': 12305, 'sub_orders': [], 'payment_url': 'https://pay.test/x', 'expires_at': null};
        },
        'GET orders/HB-1001/payment': (_, _) => {'order_no': 'HB-1001', 'method': 'ipay88', 'status': 'pending', 'paid': false, 'expires_at': null, 'retry_url': 'https://pay.test/x'},
      }));
      final h = Harness(api: api, token: 'tok');
      await h.pump(tester, location: '/checkout');

      tester.widget<SwitchListTile>(find.byKey(const Key('checkout-coins-toggle'))).onChanged!(true);
      await tester.pumpAndSettle();
      expect(find.text('RM 122.45'), findsWidgets, reason: 'toggle re-quotes with the whole balance');
      await tester.enterText(find.byKey(const Key('checkout-coins')), '40');
      await tester.pump();

      // Typed, not yet re-quoted: Place must not send a total the screen does not show.
      await tester.tap(find.byKey(const Key('checkout-place')), warnIfMissed: false);
      await tester.pump();
      expect(api.calls, isNot(contains('POST checkout/place')));

      await tester.pumpAndSettle(const Duration(seconds: 1));
      expect(find.text('RM 123.05'), findsWidgets, reason: 'total re-quoted with 40 coins');
      await tester.tap(find.byKey(const Key('checkout-place')));
      await tester.pumpAndSettle();
      expect((placeBody as Map?)?['coins'], 40);
    });
  });

  group('gap 4: a deleted delivery address does not trap checkout', () {
    testWidgets('quote 404 on the address drops it and shows the address picker, not a dead Retry', (tester) async {
      usePhone(tester);
      var addressGets = 0;
      final api = FakeApi(routes: checkoutRoutes({
        'GET addresses': (_, _) => addressGets++ == 0 ? [fakeAddress()] : [],
        'POST checkout/quote': (_, body) => (body as Map?)?['address_id'] == 1
            ? ApiException('not_found', 'No query results for model [App\\Models\\Address].', status: 404)
            : fakeQuote(),
      }));
      final h = Harness(api: api, token: 'tok');
      await h.pump(tester, location: '/checkout');

      expect(find.byKey(const Key('error-retry')), findsNothing);
      expect(find.byKey(const Key('checkout-add-address')), findsOneWidget);
    });
  });

  group('gap 5: the Orders tab is never stale', () {
    testWidgets('coming back to the Orders tab reloads the list (a new order shows)', (tester) async {
      usePhone(tester);
      var orders = <Map<String, dynamic>>[];
      final api = FakeApi(routes: {
        'GET me': (_, _) => fakeUser(),
        'GET products': (_, _) => [],
        'GET categories': (_, _) => [],
        'GET orders/buckets': (_, _) => [],
        'GET orders': (_, _) => orders,
      }, meta: {'page': 1, 'per_page': 10, 'total': 1, 'last_page': 1});
      final h = Harness(api: api, token: 'tok');
      await h.pump(tester, location: '/orders');
      expect(find.byKey(const Key('order-card-5')), findsNothing);

      h.router.go('/home');
      await tester.pumpAndSettle();
      orders = [fakeSubOrder(id: 5)]; // placed meanwhile
      h.router.go('/orders');
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('order-card-5')), findsOneWidget);
    });
  });

  group('gap 6: a slow or failing server at start does not sign the user out', () {
    for (final (name, error) in [
      ('500', ApiException('server_error', '', status: 500)),
      ('timeout', ApiException.timeout()),
    ]) {
      testWidgets('GET me $name with a saved profile keeps the user signed in', (tester) async {
        usePhone(tester);
        final db = AppDb.test(NativeDatabase.memory());
        await db.kvPut('session.profile', jsonEncode(realMe()));
        final api = FakeApi(routes: {
          'GET me': (_, _) => error,
          'GET products': (_, _) => [],
          'GET categories': (_, _) => [],
        });
        final h = Harness(api: api, token: 'tok', db: db);
        await h.pump(tester);
        await tester.pumpAndSettle();
        expect(h.container.read(sessionProvider), isA<SignedIn>());
        expect(await h.container.read(tokenStoreProvider).read(), 'tok');
      });
    }
  });

  group('gap 7: leaving a form with unsaved edits asks first', () {
    testWidgets('seller product form: back with an edited price asks; Cancel stays, Discard leaves', (tester) async {
      usePhone(tester);
      final api = FakeApi(routes: {
        ...sellerBaseRoutes(),
        'GET seller/products/7': (_, _) => fakeSellerProduct(),
      });
      final h = Harness(api: api, token: 'tok');
      await h.pump(tester, location: '/seller/products');
      h.router.push('/seller/products/7');
      await tester.pumpAndSettle();

      // Untouched: back leaves without a question.
      await tester.tap(find.byKey(const Key('souk-back')));
      await tester.pumpAndSettle();
      expect(h.router.state.uri.path, '/seller/products');

      h.router.push('/seller/products/7');
      await tester.pumpAndSettle();
      await tester.enterText(find.byKey(const Key('seller-product-price')), '99');
      await tester.pump();
      await tester.tap(find.byKey(const Key('souk-back')));
      await tester.pumpAndSettle();
      expect(find.text('Discard changes?'), findsOneWidget);
      await tester.tap(find.byKey(const Key('souk-confirm-cancel')));
      await tester.pumpAndSettle();
      expect(h.router.state.uri.path, '/seller/products/7');

      await tester.tap(find.byKey(const Key('souk-back')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('souk-confirm-ok')));
      await tester.pumpAndSettle();
      expect(h.router.state.uri.path, '/seller/products');
    });

    testWidgets('address form: back with typed text asks', (tester) async {
      usePhone(tester);
      final api = FakeApi(routes: {
        'GET me': (_, _) => realMe(),
        'GET products': (_, _) => [],
        'GET categories': (_, _) => [],
        'GET addresses': (_, _) => [],
        'GET config': (_, _) => {'states': ['Selangor']},
      });
      final h = Harness(api: api, token: 'tok');
      await h.pump(tester, location: '/account/addresses');
      h.router.push('/account/addresses/new');
      await tester.pumpAndSettle();
      await tester.enterText(find.byKey(const Key('address-recipient')), 'Buyer One');
      await tester.pump();
      await tester.tap(find.byKey(const Key('souk-back')));
      await tester.pumpAndSettle();
      expect(find.text('Discard changes?'), findsOneWidget);
    });
  });

  group('gap 8: a failed pull-to-refresh keeps the page', () {
    testWidgets('home: refresh answering 500 keeps the loaded products', (tester) async {
      usePhone(tester);
      var fail = false;
      final api = FakeApi(routes: {
        'GET home': (_, _) => fail
            ? ApiException('server_error', '', status: 500)
            : fakeHome(featured: [fakeProduct(name: 'Madu Tualang Asli')]),
      });
      final h = Harness(api: api);
      await h.pump(tester);
      await tester.pumpAndSettle();
      expect(find.text('Madu Tualang Asli'), findsWidgets);

      fail = true;
      await tester.fling(find.byKey(const Key('souk-page-scroll')), const Offset(0, 400), 1000);
      await tester.pumpAndSettle();
      expect(api.calls.where((c) => c == 'GET home').length, 2, reason: 'the pull did refetch');
      expect(find.text('Madu Tualang Asli'), findsWidgets);
      expect(find.byKey(const Key('error-retry')), findsNothing);
    });

    testWidgets('seller products: a failed refresh keeps the rows', (tester) async {
      usePhone(tester);
      var fail = false;
      final api = FakeApi(routes: {
        ...sellerBaseRoutes(),
        'GET seller/products': (_, _) => fail ? ApiException('server_error', '', status: 500) : [fakeSellerProduct()],
      });
      final h = Harness(api: api, token: 'tok');
      await h.pump(tester, location: '/seller/products');
      expect(find.byKey(const Key('seller-product-7')), findsOneWidget);

      fail = true;
      await tester.fling(find.byKey(const Key('souk-page-scroll')), const Offset(0, 400), 1000);
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('seller-product-7')), findsOneWidget);
    });
  });

  group('gap 9: a deleted store or product says so', () {
    ApiException gone() => ApiException('not_found', 'No query results.', status: 404);

    testWidgets('store 404 shows Page not found with a way home, not an empty shop', (tester) async {
      usePhone(tester);
      final api = FakeApi(routes: {
        'GET stores/gone-shop': (_, _) => gone(),
        'GET stores/gone-shop/products': (_, _) => gone(),
        'GET products': (_, _) => [],
      });
      final h = Harness(api: api);
      await h.pump(tester, location: '/s/gone-shop');
      expect(find.byKey(const Key('souk-not-found')), findsOneWidget);
      await tester.tap(find.byKey(const Key('souk-not-found-home')));
      await tester.pumpAndSettle();
      expect(h.router.state.uri.path, '/home');
    });

    testWidgets('product 404 shows Page not found, not a Retry that cannot work', (tester) async {
      usePhone(tester);
      final api = FakeApi(routes: {'GET products/gone': (_, _) => gone()});
      final h = Harness(api: api);
      await h.pump(tester, location: '/p/gone');
      expect(find.byKey(const Key('souk-not-found')), findsOneWidget);
      expect(find.byKey(const Key('error-retry')), findsNothing);
    });
  });
}
