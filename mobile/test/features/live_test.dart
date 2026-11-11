import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';

Map<String, dynamic> _session() => {
  'slug': 'eid-sale',
  'title': 'Eid sale',
  'store_name': 'Kedai Madu',
  'store_slug': 'kedai-madu',
  'thumbnail': null,
  'platform': 'youtube',
  'started_at': '2026-11-11T10:00:00+08:00',
};

void main() {
  testWidgets('a product card shows LIVE only when store_live, and tapping it opens the room', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET products': (_, _) => [
        {...fakeProduct(), 'store_live': true, 'live_slug': 'eid-sale'},
        fakeProduct(id: 2, slug: 'kurma', name: 'Kurma Ajwa'),
      ],
      'GET categories': (_, _) => [],
    });
    final h = Harness(api: api);
    await h.pump(tester, location: '/search');
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('product-live-madu-tualang')), findsOneWidget);
    expect(find.byKey(const Key('product-live-kurma')), findsNothing);

    await tester.tap(find.byKey(const Key('product-live-madu-tualang')));
    await tester.pump();
    expect(h.browser.opened.single, endsWith('/live/eid-sale'));
  });

  testWidgets('the store header shows LIVE while the store is live', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET stores/kedai-madu': (_, _) => {'id': 3, 'name': 'Kedai Madu', 'slug': 'kedai-madu', 'store_live': true, 'live_slug': 'eid-sale'},
      'GET stores/kedai-madu/products': (_, _) => [],
    });
    await Harness(api: api).pump(tester, location: '/s/kedai-madu');
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('store-live')), findsOneWidget);
  });

  testWidgets('the Live pill is hidden with 0 live sessions', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET products': (_, _) => [fakeProduct()],
      'GET categories': (_, _) => [],
      'GET live': (_, _) => [],
    });
    await Harness(api: api).pump(tester, location: '/search');
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('live-pill')), findsNothing);
    expect(find.byKey(const Key('product-filter')), findsOneWidget);
  });

  testWidgets('the Live pill lists live sessions and a tap opens the web room in the browser', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET products': (_, _) => [fakeProduct()],
      'GET categories': (_, _) => [],
      'GET live': (_, _) => [_session()],
    });
    final h = Harness(api: api);
    await h.pump(tester, location: '/search');
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('live-pill')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('live-sheet')), findsOneWidget);
    expect(find.text('Eid sale'), findsOneWidget);

    await tester.tap(find.byKey(const Key('live-session-eid-sale')));
    await tester.pumpAndSettle();
    expect(h.browser.opened.single, endsWith('/live/eid-sale'));
    expect(find.byKey(const Key('live-sheet')), findsNothing);
  });
}
