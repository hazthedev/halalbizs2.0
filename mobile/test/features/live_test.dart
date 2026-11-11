import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:halalbizs/core/api/api_client.dart';
import 'package:halalbizs/features/catalog/live_room_screen.dart';

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
  testWidgets('a product card shows LIVE only when store_live, and tapping it opens the in-app room', (tester) async {
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
    await tester.pumpAndSettle();
    expect(h.router.state.uri.path, '/live/eid-sale');
    expect(h.browser.opened, isEmpty);
  });

  testWidgets('the store header shows LIVE while the store is live', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET stores/kedai-madu': (_, _) => {'id': 3, 'name': 'Kedai Madu', 'slug': 'kedai-madu', 'store_live': true, 'live_slug': 'eid-sale'},
      'GET stores/kedai-madu/products': (_, _) => [],
    });
    final h = Harness(api: api);
    await h.pump(tester, location: '/s/kedai-madu');
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('store-live')), findsOneWidget);
    await tester.tap(find.byKey(const Key('store-live')));
    await tester.pumpAndSettle();
    expect(h.router.state.uri.path, '/live/eid-sale');
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

  testWidgets('the Live pill lists live sessions and a tap opens the in-app room', (tester) async {
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
    expect(h.router.state.uri.path, '/live/eid-sale');
    expect(h.browser.opened, isEmpty);
    expect(find.byKey(const Key('live-sheet')), findsNothing);
  });

  group('room', () {
    Map<String, dynamic> room({String? embed = 'https://www.youtube.com/embed/dQw4w9WgXcQ', String? external, bool live = true, String platform = 'youtube'}) => {
      'slug': 'eid-sale',
      'title': 'Eid sale',
      'store': {'name': 'Kedai Madu', 'slug': 'kedai-madu'},
      'platform': platform,
      'embed_url': embed,
      'external_url': external,
      'is_live': live,
      'started_at': '2026-11-11T10:00:00+08:00',
      'products': [fakeProduct()],
    };

    Harness harness(Object? Function() body) => Harness(
      api: FakeApi(routes: {'GET live/eid-sale': (_, _) => body()}),
      overrides: [liveVideoBuilderProvider.overrideWith((ref) => (_, url) => Text('player $url', key: const Key('fake-player')))],
    );

    testWidgets('renders player, title, store and the stream products', (tester) async {
      usePhone(tester);
      final h = harness(room);
      await h.pump(tester, location: '/live/eid-sale');
      expect(find.byKey(const Key('fake-player')), findsOneWidget);
      expect(find.text('Eid sale'), findsWidgets);
      expect(find.text('Kedai Madu'), findsOneWidget);
      expect(find.byKey(const Key('product-card-madu-tualang')), findsOneWidget);

      await tester.tap(find.byKey(const Key('live-store')));
      await tester.pumpAndSettle();
      expect(h.router.state.uri.path, '/s/kedai-madu');
    });

    testWidgets('an external platform shows Watch on and no player', (tester) async {
      usePhone(tester);
      await harness(() => room(embed: null, external: 'https://live.shopee.com.my/x', platform: 'shopee')).pump(tester, location: '/live/eid-sale');
      expect(find.byKey(const Key('live-watch-on')), findsOneWidget);
      expect(find.text('Watch on Shopee'), findsOneWidget);
      expect(find.byKey(const Key('fake-player')), findsNothing);
    });

    testWidgets('an ended session and an unknown one show the empty state', (tester) async {
      usePhone(tester);
      await harness(() => room(live: false)).pump(tester, location: '/live/eid-sale');
      expect(find.byKey(const Key('live-ended')), findsOneWidget);
      expect(find.byKey(const Key('fake-player')), findsNothing);
    });

    testWidgets('a 404 shows the empty state, no crash', (tester) async {
      usePhone(tester);
      await harness(() => ApiException('not_found', 'nope', status: 404)).pump(tester, location: '/live/eid-sale');
      expect(find.byKey(const Key('live-ended')), findsOneWidget);
    });
  });
}
