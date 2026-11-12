import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';
import 'home_test.dart';

Future<Harness> openBanner(WidgetTester tester, String appUrl, {Map<String, dynamic>? flashSale, FakeApi? api}) async {
  usePhone(tester);
  final fake = api ??
      FakeApi(routes: {
        'GET home': (_, _) => {...fakeHome(banners: [fakeBanner(1, appUrl: appUrl)]), 'flash_sale': flashSale},
        'GET search': (_, _) => [fakeProduct(slug: 'madu-hutan', name: 'Madu Hutan')],
        'GET categories': (_, _) => [],
      });
  final h = Harness(api: fake);
  await h.pump(tester);
  await tester.pump(const Duration(seconds: 1));
  await tester.tap(find.byKey(const Key('home-banner-1')));
  await tester.pump(const Duration(seconds: 1));
  await tester.pump(const Duration(seconds: 1));
  return h;
}

void main() {
  testWidgets('/search?q=<text> opens search with the text typed and results run', (tester) async {
    final h = await openBanner(tester, '/search?q=madu');
    expect(h.router.state.uri.path, '/search');
    expect(tester.widget<TextField>(find.byKey(const Key('search-input'))).controller?.text, 'madu');
    expect(h.api.calls, contains('GET search?q=madu'));
    expect(find.text('Madu Hutan'), findsOneWidget);
  });

  testWidgets('/flash-sale lists the live deals with the product card', (tester) async {
    final h = await openBanner(tester, '/flash-sale', flashSale: {
      'ends_at': DateTime.now().add(const Duration(hours: 2)).toUtc().toIso8601String(),
      'products': [{...fakeProduct(slug: 'kurma-kilat', name: 'Kurma Kilat'), 'flash': {'price_sen': 100}}],
    });
    expect(h.router.state.uri.path, '/flash-sale');
    expect(find.byKey(const Key('product-card-kurma-kilat')), findsOneWidget);
  });

  testWidgets('/flash-sale with no sale shows the empty state', (tester) async {
    await openBanner(tester, '/flash-sale');
    expect(find.byKey(const Key('flash-empty')), findsOneWidget);
  });
}
