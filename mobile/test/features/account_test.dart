import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/core/api/api_client.dart';
import 'package:halalbizs/core/auth/session.dart';

import '../helpers.dart';

/// `GET me` exactly as `UserResource` serialises it.
Map<String, dynamic> realMe() => {
      'id': 2,
      'name': 'Demo Buyer',
      'email': 'buyer@halalbizs.test',
      'phone': null,
      'email_verified': true,
      'preferred_locale': 'ms',
      'roles': ['buyer', 'seller'],
      'store': {'id': 3, 'slug': 'kedai-madu', 'name': 'Kedai Madu', 'status': 'approved'},
      'unread_notifications': 3,
      'coins': 42,
    };

void main() {
  test('Profile parses the real GET me payload', () {
    final me = Profile(realMe());
    expect(me.id, 2);
    expect(me.name, 'Demo Buyer');
    expect(me.phone, isNull);
    expect(me.emailVerified, isTrue);
    expect(me.preferredLocale, 'ms');
    expect(me.coins, 42);
    expect(me.unreadNotifications, 3);
    expect(me.storeId, 3);
    expect(me.storeName, 'Kedai Madu');
    expect(me.isApprovedSeller, isTrue);
    expect(me.unreadMessages, 0);
    // Lenient fallbacks for the older shape.
    expect(Profile(fakeUser(verified: false)).emailVerified, isFalse);
    expect(Profile({'coins_balance': 7, 'unread': {'notifications': 1}}).coins, 7);
    expect(Profile({'unread': {'notifications': 1}}).unreadNotifications, 1);
  });

  testWidgets('address form maps a 422 `fields` error onto the postcode input', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET me': (_, _) => realMe(),
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET config': (_, _) => {'states': ['Johor', 'Kuala Lumpur', 'Selangor']},
      'POST addresses': (_, body) {
        expect((body as Map)['postcode'], '1234');
        expect(body['recipient_name'], 'Buyer One');
        return ApiException('validation_failed', 'The given data was invalid.', status: 422, fields: {
          'postcode': ['Postcode must be exactly 5 digits, like 50450.'],
        });
      },
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/account/addresses/new');

    await tester.enterText(find.byKey(const Key('address-recipient')), 'Buyer One');
    await tester.enterText(find.byKey(const Key('address-phone')), '0123456789');
    await tester.enterText(find.byKey(const Key('address-line1')), '12 Jalan Satu');
    await tester.enterText(find.byKey(const Key('address-postcode')), '1234');
    await tester.enterText(find.byKey(const Key('address-city')), 'Kuala Lumpur');
    await tester.tap(find.byKey(const Key('address-save')));
    await tester.pumpAndSettle();

    expect(api.calls, contains('POST addresses'));
    expect(find.text('Postcode must be exactly 5 digits, like 50450.'), findsOneWidget);
    expect(h.router.state.uri.path, '/account/addresses/new', reason: 'stays on the form');
  });

  testWidgets('delete account calls DELETE me, signs out and leaves the account area', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET me': (_, _) => realMe(),
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'DELETE me': (_, body) {
        expect((body as Map)['password'], 'password');
        return null;
      },
      'POST auth/logout': (_, _) => null,
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/account/delete');

    await tester.enterText(find.byKey(const Key('delete-password')), 'password');
    await tester.tap(find.byKey(const Key('delete-submit')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('souk-confirm-ok')));
    await tester.pumpAndSettle();

    expect(api.calls, contains('DELETE me'));
    expect(h.container.read(sessionProvider), isA<SignedOut>());
    expect(h.router.state.uri.path, '/home');
  });

  testWidgets('coins page: check-in posts and a 409 already_checked_in is surfaced', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET me': (_, _) => realMe(),
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET coins': (_, _) => {
            'balance': 42,
            'lifetime_earned': 60,
            'streak': 3,
            'can_check_in': true,
            'expiring_at': null,
            'ledger': [
              {'id': 1, 'type': 'checkin', 'type_label': 'Daily check-in', 'amount': 5, 'note': 'Daily check-in — day 3', 'created_at': '2026-11-07T01:00:00Z'},
            ],
          },
      'POST coins/check-in': (_, _) => ApiException('already_checked_in', 'You have already checked in today.', status: 409),
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/account/coins');
    expect(find.byKey(const Key('coins-balance')), findsOneWidget);
    expect(find.text('42'), findsOneWidget);
    expect(find.byKey(const Key('coins-txn-1')), findsOneWidget);
    await tester.tap(find.byKey(const Key('coins-check-in')));
    await tester.pumpAndSettle();
    expect(api.calls, contains('POST coins/check-in'));
    expect(find.text('You have already checked in today.'), findsOneWidget);
  });
}
