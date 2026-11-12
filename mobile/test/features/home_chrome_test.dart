import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/core/theme/souk_theme.dart';

import '../helpers.dart';
import 'home_test.dart';

Future<Harness> pumpHome(WidgetTester tester, Map<String, dynamic> extra) async {
  usePhone(tester);
  final h = Harness(api: FakeApi(routes: {
    'GET home': (_, _) => {...fakeHome(), ...extra},
    'GET pages/terms': (_, _) => {'slug': 'terms', 'title': 'Terms of use', 'body': '<p>Be kind.</p>', 'updated_at': '2026-11-01'},
  }));
  await h.pump(tester);
  await tester.pumpAndSettle();
  return h;
}

void main() {
  testWidgets('announcement bar shows the text in the admin colours; absent = nothing', (tester) async {
    await pumpHome(tester, {
      'announcement': {'text': 'Raya sale on', 'bg': '#112233', 'text_color': '#FFEEDD'},
    });
    expect(find.text('Raya sale on'), findsOneWidget);
    expect(tester.widget<Container>(find.byKey(const Key('home-announcement'))).color, const Color(0xFF112233));
    expect(tester.widget<Text>(find.text('Raya sale on')).style?.color, const Color(0xFFFFEEDD));
    await pumpHome(tester, {});
    expect(find.byKey(const Key('home-announcement')), findsNothing);
    expect(find.byKey(const Key('home-hero')), findsNothing);
  });

  testWidgets('announcement colours that do not parse fall back to emerald and ivory', (tester) async {
    await pumpHome(tester, {
      'announcement': {'text': 'Hello', 'bg': 'red-ish', 'text_color': ''},
    });
    expect(tester.widget<Container>(find.byKey(const Key('home-announcement'))).color, SoukColors.emerald);
    expect(tester.widget<Text>(find.text('Hello')).style?.color, SoukColors.paper);
  });

  testWidgets('hero image sits under the announcement bar', (tester) async {
    await pumpHome(tester, {
      'announcement': {'text': 'Hello', 'bg': '#112233', 'text_color': '#FFFFFF'},
      'hero': {'image': 'https://halalbizs.test/hero.jpg', 'occasion': 'Hari Raya'},
    });
    expect(find.bySemanticsLabel('Hari Raya'), findsOneWidget);
    expect(tester.getTopLeft(find.byKey(const Key('home-hero'))).dy, greaterThan(tester.getBottomLeft(find.byKey(const Key('home-announcement'))).dy - 1));
  });

  testWidgets('banner shows subtitle and button text; its title stays the accessibility label', (tester) async {
    await pumpHome(tester, {
      'banners': [
        {...fakeBanner(1), 'subtitle': 'Up to 50% off', 'cta_label': 'Shop now'},
      ],
    });
    expect(find.text('Up to 50% off'), findsOneWidget);
    expect(find.text('Shop now'), findsOneWidget);
    expect(find.bySemanticsLabel('Banner 1'), findsOneWidget);
  });

  testWidgets('a banner with app_url /page/terms opens the page screen', (tester) async {
    final h = await pumpHome(tester, {
      'banners': [fakeBanner(1, appUrl: '/page/terms')],
    });
    await tester.tap(find.byKey(const Key('home-banner-1')));
    await tester.pumpAndSettle();
    expect(h.router.state.uri.path, '/page/terms');
    expect(find.text('Terms of use'), findsWidgets);
    expect(find.textContaining('Be kind', findRichText: true), findsOneWidget);
  });
}
