import 'dart:convert';

import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/core/api/api_client.dart';
import 'package:halalbizs/core/db/app_db.dart';

import '../helpers.dart';

const _terms = {'slug': 'terms', 'title': 'Terms of use', 'body': '<h2>Rules</h2><p>Be <strong>kind</strong>.</p>', 'updated_at': '2026-11-01'};
const _pages = [
  {'slug': 'terms', 'title': 'Terms of use'},
  {'slug': 'about', 'title': 'About HalalBizs'},
];
const _help = [
  {'id': 3, 'category': 'orders', 'category_label': 'Orders', 'title': 'Where is my parcel?'},
  {'id': 4, 'category': 'orders', 'category_label': 'Orders', 'title': 'Cancel an order'},
  {'id': 5, 'category': 'pay', 'category_label': 'Payments', 'title': 'Refund times'},
];
const _article = {'id': 3, 'category': 'orders', 'category_label': 'Orders', 'title': 'Where is my parcel?', 'body': '<p>Track it in Orders.</p>', 'updated_at': '2026-11-02'};

Map<String, FakeHandler> routes() => {
      'GET me': (_, _) => fakeUser(),
      'GET pages': (_, _) => _pages,
      'GET pages/terms': (_, _) => _terms,
      'GET help': (_, _) => _help,
      'GET help/3': (_, _) => _article,
      'POST me/web-sign-in-link': (_, _) => {'url': 'https://web.test/app/sign-in?s=1', 'expires_at': '2026-11-10T12:05:00Z'},
    };

void main() {
  testWidgets('Legal lists the API pages and opens one as a native screen', (tester) async {
    usePhone(tester);
    final h = Harness(api: FakeApi(routes: routes()), token: 'tok');
    await h.pump(tester, location: '/account/legal');
    expect(find.text('About HalalBizs'), findsOneWidget);
    await tester.tap(find.byKey(const Key('legal-terms')));
    await tester.pumpAndSettle();
    expect(h.router.state.uri.path, '/page/terms');
    expect(find.textContaining('Rules', findRichText: true), findsOneWidget);
    expect(find.textContaining('Be kind', findRichText: true), findsOneWidget);
    expect(h.browser.opened, isEmpty);
  });

  testWidgets('Help groups articles by category, opens one, and keeps Contact support', (tester) async {
    usePhone(tester);
    final h = Harness(api: FakeApi(routes: routes()), token: 'tok');
    await h.pump(tester, location: '/account');
    await tester.ensureVisible(find.byKey(const Key('account-support')));
    await tester.tap(find.byKey(const Key('account-support')));
    await tester.pumpAndSettle();
    expect(h.router.state.uri.path, '/help');
    expect(find.text('Payments'), findsOneWidget);
    expect(find.text('Cancel an order'), findsOneWidget);

    await tester.tap(find.byKey(const Key('help-3')));
    await tester.pumpAndSettle();
    expect(h.router.state.uri.path, '/help/3');
    expect(find.textContaining('Track it', findRichText: true), findsOneWidget);
    h.router.pop();
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('help-contact')));
    await tester.pumpAndSettle();
    expect(h.browser.opened, ['https://web.test/app/sign-in?s=1']);
  });

  testWidgets('pages and help read from the offline copy with the cached strip', (tester) async {
    usePhone(tester);
    final db = AppDb.test(NativeDatabase.memory());
    await db.putResponse('pages', jsonEncode(_pages), null);
    await db.putResponse('pages/terms', jsonEncode(_terms), null);
    await db.putResponse('help', jsonEncode(_help), null);
    final api = FakeApi(routes: {
      'GET me': (_, _) => fakeUser(),
      'GET pages': (_, _) => ApiException.offline(),
      'GET pages/terms': (_, _) => ApiException.offline(),
      'GET help': (_, _) => ApiException.offline(),
    });
    final h = Harness(api: api, db: db, token: 'tok', offline: true);
    await h.pump(tester, location: '/help');
    expect(find.byKey(const Key('souk-cached-strip')), findsOneWidget);
    expect(find.text('Refund times'), findsOneWidget);

    h.router.go('/page/terms');
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('souk-cached-strip')), findsOneWidget);
    expect(find.textContaining('Rules', findRichText: true), findsOneWidget);
  });
}
