import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/core/locale.dart';

import '../helpers.dart';
import 'orders_test.dart' show fakeSubOrder;

void main() {
  testWidgets('switching to Bahasa Melayu reloads API names (categories, order chips and labels) in BM', (tester) async {
    usePhone(tester);
    late Harness h;
    bool ms() => h.container.read(localeProvider).languageCode == 'ms';
    final api = FakeApi(routes: {
      'GET me': (_, _) => fakeUser(),
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [
            {'id': 1, 'slug': 'rice', 'name': ms() ? 'Beras & Bijirin' : 'Rice & Grains', 'parent_id': null},
          ],
      'GET orders/buckets': (_, _) => [
            {'key': 'to-pay', 'label': ms() ? 'Untuk Dibayar' : 'To Pay', 'count': 1},
          ],
      'GET orders': (_, _) => [fakeSubOrder(label: ms() ? 'Disahkan' : 'Confirmed')],
      'PATCH me': (_, _) => fakeUser(),
    });
    h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/orders');
    expect(find.text('To Pay · 1'), findsOneWidget);
    expect(find.text('CONFIRMED'), findsOneWidget);
    h.router.go('/search');
    await tester.pumpAndSettle();
    expect(find.text('Rice & Grains'), findsOneWidget);

    h.router.go('/account/language');
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('language-ms')));
    await tester.pumpAndSettle();

    h.router.go('/search');
    await tester.pumpAndSettle();
    expect(find.text('Beras & Bijirin'), findsOneWidget);
    h.router.go('/orders');
    await tester.pumpAndSettle();
    expect(find.text('Untuk Dibayar · 1'), findsOneWidget);
    expect(find.text('DISAHKAN'), findsOneWidget);
  });

  testWidgets('the language page saves to the account and Profile shows the language the app is in', (tester) async {
    usePhone(tester);
    Object? patched;
    final api = FakeApi(routes: {
      'GET me': (_, _) => {...fakeUser(), 'preferred_locale': 'en'},
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'PATCH me': (_, body) {
        patched = body;
        return fakeUser();
      },
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/account/language');
    await tester.tap(find.byKey(const Key('language-ms')));
    await tester.pumpAndSettle();
    expect((patched as Map?)?['preferred_locale'], 'ms');

    h.router.go('/account/profile');
    await tester.pumpAndSettle();
    expect(find.descendant(of: find.byKey(const Key('profile-locale')), matching: find.text('Bahasa Melayu')), findsOneWidget);
    expect(find.descendant(of: find.byKey(const Key('profile-locale')), matching: find.text('English')), findsNothing);
  });
}
