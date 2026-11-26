import 'dart:async';
import 'dart:convert';

import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/core/db/app_db.dart';
import 'package:halalbizs/core/theme/souk_theme.dart';
import 'package:halalbizs/core/version_gate.dart';

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

  testWidgets('banner = picture only; the button sits below it; its title stays the accessibility label', (tester) async {
    await pumpHome(tester, {
      'banners': [
        {...fakeBanner(1), 'subtitle': 'Up to 50% off', 'cta_label': 'Shop now'},
      ],
    });
    expect(find.text('Up to 50% off'), findsNothing, reason: 'art carries its own headline');
    final cta = find.text('Shop now');
    expect(cta, findsOneWidget);
    expect(tester.getTopLeft(cta).dy, greaterThan(tester.getBottomLeft(find.byKey(const Key('home-banners'))).dy - 1));
    // no dark gradient over the art
    expect(find.descendant(of: find.byKey(const Key('home-banners')), matching: find.byType(DecoratedBox)).evaluate().where((e) => (e.widget as DecoratedBox).decoration is BoxDecoration && ((e.widget as DecoratedBox).decoration as BoxDecoration).gradient != null), isEmpty);
    expect(find.bySemanticsLabel('Banner 1'), findsOneWidget);
  });

  testWidgets('banner button and picture both open app_url', (tester) async {
    final h = await pumpHome(tester, {
      'banners': [
        {...fakeBanner(1, appUrl: '/page/terms'), 'cta_label': 'Terms'},
      ],
    });
    await tester.tap(find.byKey(const Key('home-banner-cta-1')));
    await tester.pumpAndSettle();
    expect(h.router.state.uri.path, '/page/terms');
  });

  testWidgets('banner with no app_url: register link opens the Search tab (picture and button)', (tester) async {
    final h = await pumpHome(tester, {
      'banners': [
        {...fakeBanner(4), 'link_url': 'https://halalbizs.com/certificate-register', 'cta_label': 'Search the register'},
      ],
    });
    await tester.tap(find.byKey(const Key('home-banner-cta-4')));
    await tester.pumpAndSettle();
    expect(h.router.state.uri.path, '/search');
    h.router.go('/home');
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('home-banner-4')));
    await tester.pumpAndSettle();
    expect(h.router.state.uri.path, '/search');
  });

  testWidgets('home paints the last saved home before the fresh one answers', (tester) async {
    usePhone(tester);
    final db = AppDb.test(NativeDatabase.memory());
    await db.putResponse('home', jsonEncode(fakeHome(featured: [fakeProduct(name: 'Saved Honey')])), null);
    final fresh = Completer<Map<String, dynamic>>();
    final api = _SlowApi({'GET home': fresh.future});
    final h = Harness(api: api, db: db, overrides: [installedVersionProvider.overrideWith((ref) => '1.0.0')]);
    await h.pump(tester);
    await tester.pump();
    expect(find.text('Saved Honey'), findsOneWidget);
    fresh.complete(fakeHome(featured: [fakeProduct(name: 'Fresh Honey')]));
    await tester.pumpAndSettle();
    expect(find.text('Fresh Honey'), findsOneWidget);
    expect(find.text('Saved Honey'), findsNothing);
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

/// A FakeApi where chosen GETs wait on a future (a slow server).
class _SlowApi extends FakeApi {
  _SlowApi(this.slow) : super(routes: {'GET config': (_, _) => {'min_app_version': '1.0.0'}});

  final Map<String, Future<Map<String, dynamic>>> slow;

  @override
  Future<dynamic> get(String path, {Map<String, dynamic>? query, void Function(Map<String, dynamic>)? meta}) async =>
      slow['GET $path'] ?? super.get(path, query: query, meta: meta);
}
