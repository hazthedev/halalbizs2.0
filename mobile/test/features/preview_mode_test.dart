import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/core/auth/session.dart';
import 'package:halalbizs/features/preview/preview_mode.dart';

import '../helpers.dart';

Harness _app({required bool staff, FakeApi? api}) {
  final fake = api ??
      FakeApi(routes: {
        'GET me': (_, _) => {...fakeUser(), 'can_preview_content': staff},
        'GET home': (_, _) => {'featured': [], 'categories': [], 'banners': []},
        'GET pages': (_, _) => [
              {'slug': 'terms', 'title': 'Terms of use'}
            ],
        'GET pages/terms': (_, _) => {'slug': 'terms', 'title': 'Terms of use', 'body': '<p>Hi</p>'},
        'GET help': (_, _) => [
              {'id': 3, 'category_label': 'Orders', 'title': 'Where is my parcel?'}
            ],
        'GET help/3': (_, _) => {'id': 3, 'title': 'Where is my parcel?', 'body': '<p>Track it.</p>'},
      });
  return Harness(api: fake, token: 'tok');
}

final _row = find.byKey(const Key('account-preview-drafts'));
final _strip = find.byKey(const Key('preview-strip'));

Future<void> _switchOn(WidgetTester tester, Harness h) async {
  await h.pump(tester, location: '/account');
  await tester.ensureVisible(_row);
  await tester.tap(_row);
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('staff: row shows; switch on sends preview=1 on home, pages and help, strip shows', (tester) async {
    usePhone(tester);
    final h = _app(staff: true);
    await _switchOn(tester, h);
    expect(h.container.read(previewModeProvider), isTrue);

    h.router.go('/home');
    await tester.pumpAndSettle();
    expect(h.api.calls, contains('GET home?preview=1'));
    expect(_strip, findsOneWidget);
    expect(find.text('Preview: drafts shown'), findsOneWidget);

    h.router.go('/account/legal');
    await tester.pumpAndSettle();
    expect(h.api.calls, contains('GET pages?preview=1'));
    expect(_strip, findsOneWidget);
    h.router.go('/page/terms');
    await tester.pumpAndSettle();
    expect(h.api.calls, contains('GET pages/terms?preview=1'));
    expect(_strip, findsOneWidget);

    h.router.go('/help');
    await tester.pumpAndSettle();
    expect(h.api.calls, contains('GET help?preview=1'));
    expect(_strip, findsOneWidget);
    h.router.go('/help/3');
    await tester.pumpAndSettle();
    expect(h.api.calls, contains('GET help/3?preview=1'));
    expect(_strip, findsOneWidget);
  });

  testWidgets('switching off refetches live content and hides the strip', (tester) async {
    usePhone(tester);
    final h = _app(staff: true);
    await _switchOn(tester, h);
    h.router.go('/help');
    await tester.pumpAndSettle();
    h.api.calls.clear();
    h.container.read(previewModeProvider.notifier).set(false);
    await tester.pumpAndSettle();
    expect(h.api.calls, ['GET help']);
    expect(_strip, findsNothing);
  });

  testWidgets('staff with the switch off: no preview param, no strip', (tester) async {
    usePhone(tester);
    final h = _app(staff: true);
    await h.pump(tester, location: '/account');
    expect(_row, findsOneWidget);
    for (final p in ['/home', '/help', '/account/legal']) {
      h.router.go(p);
      await tester.pumpAndSettle();
    }
    expect(h.api.calls.where((c) => c.contains('preview')), isEmpty);
    expect(_strip, findsNothing);
  });

  testWidgets('normal user: no row, no preview param, no strip', (tester) async {
    usePhone(tester);
    final h = _app(staff: false);
    await h.pump(tester, location: '/account');
    expect(_row, findsNothing);
    h.router.go('/home');
    await tester.pumpAndSettle();
    expect(h.api.calls.where((c) => c.contains('preview')), isEmpty);
    expect(_strip, findsNothing);
  });

  testWidgets('older server (no can_preview_content): no row', (tester) async {
    usePhone(tester);
    final h = _app(staff: false, api: FakeApi(routes: {'GET me': (_, _) => fakeUser()}));
    await h.pump(tester, location: '/account');
    expect(_row, findsNothing);
  });

  testWidgets('sign-out switches preview off', (tester) async {
    usePhone(tester);
    final h = _app(staff: true);
    await _switchOn(tester, h);
    h.api.routes['POST auth/logout'] = (_, _) => {};
    await h.container.read(sessionProvider.notifier).logout();
    await tester.pumpAndSettle();
    expect(h.container.read(previewModeProvider), isFalse);
  });
}
