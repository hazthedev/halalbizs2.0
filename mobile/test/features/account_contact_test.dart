import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';

Harness _app(Map<String, dynamic> config) => Harness(
      api: FakeApi(routes: {
        'GET me': (_, _) => fakeUser(),
        'GET home': (_, _) => {'featured': [], 'categories': [], 'banners': []},
        'GET config': (_, _) => config,
      }),
      token: 'tok',
    );

Future<void> _open(WidgetTester tester, Map<String, dynamic> config) async {
  usePhone(tester);
  await _app(config).pump(tester, location: '/account');
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('config contact + social show as a Contact us block, only filled rows', (tester) async {
    await _open(tester, {
      'contact': {'email': 'hi@shop.test', 'phone': null, 'whatsapp': '+60 12-345 6789', 'address': null},
      'social': {'facebook': 'https://facebook.com/shop', 'instagram': 'http://insecure.test/x', 'tiktok': null},
    });
    expect(find.text('Contact us'), findsOneWidget);
    for (final k in ['account-contact-email', 'account-contact-whatsapp', 'account-social-facebook']) {
      final f = find.byKey(Key(k));
      expect(f, findsOneWidget, reason: k);
      expect(tester.getSize(f).height, greaterThanOrEqualTo(48));
    }
    expect(find.byKey(const Key('account-contact-phone')), findsNothing);
    expect(find.byKey(const Key('account-social-instagram')), findsNothing, reason: 'http is ignored');
    expect(find.text('hi@shop.test'), findsOneWidget);
  });

  for (final c in {
    'old server (no keys)': <String, dynamic>{},
    'all null': {
      'contact': {'email': null, 'phone': null, 'whatsapp': null, 'address': null},
      'social': {'facebook': null, 'x': ''},
    },
  }.entries) {
    testWidgets('no block: ${c.key}', (tester) async {
      await _open(tester, c.value);
      expect(find.text('Contact us'), findsNothing);
      expect(find.byKey(const Key('account-contact')), findsNothing);
    });
  }
}
