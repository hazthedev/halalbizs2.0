import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';
import 'home_test.dart';

Map<String, dynamic> grid(int id, String title, String slug) => {
      'id': id,
      'type': 'product_grid',
      'title': title,
      'source': 'top',
      'products': [fakeProduct(id: id, slug: slug, name: 'P $slug')],
    };

Map<String, dynamic> home({List<Map<String, dynamic>>? blocks, Map<String, dynamic>? announcement, Map<String, dynamic>? hero}) =>
    {...fakeHome(), 'blocks': ?blocks, 'announcement': announcement, 'hero': hero};

Future<Harness> pumpHome(WidgetTester tester, Map<String, dynamic> payload) async {
  usePhone(tester);
  final h = Harness(api: FakeApi(routes: {'GET home': (_, _) => payload}));
  await h.pump(tester);
  await tester.pumpAndSettle();
  return h;
}

void main() {
  testWidgets('blocks set the order of the home sections', (tester) async {
    await pumpHome(tester, home(blocks: [grid(1, 'Second one', 'b'), grid(2, 'First one', 'a')]));
    expect(tester.getTopLeft(find.text('Second one')).dy, lessThan(tester.getTopLeft(find.text('First one')).dy));
    await pumpHome(tester, home(blocks: [grid(2, 'First one', 'a'), grid(1, 'Second one', 'b')]));
    expect(tester.getTopLeft(find.text('First one')).dy, lessThan(tester.getTopLeft(find.text('Second one')).dy));
  });

  testWidgets('an unknown block type is skipped without error', (tester) async {
    await pumpHome(tester, home(blocks: [
      {'id': 9, 'type': 'hologram', 'title': 'Alien', 'products': []},
      {'id': 8, 'type': 'recently_viewed', 'title': 'Seen'},
      grid(1, 'Known one', 'a'),
    ]));
    expect(tester.takeException(), isNull);
    expect(find.text('Alien'), findsNothing);
    expect(find.text('Seen'), findsNothing);
    expect(find.text('Known one'), findsOneWidget);
  });

  testWidgets('missing blocks keeps the old fixed layout', (tester) async {
    await pumpHome(tester, home());
    expect(find.byKey(const Key('home-empty')), findsOneWidget);
    expect(find.text('Featured'), findsOneWidget);
  });

  testWidgets('category grid, carousel and flash sale (after the first banner block)', (tester) async {
    usePhone(tester);
    final payload = {
      ...home(blocks: [
        {'id': 1, 'type': 'category_grid', 'title': 'Shop by category', 'categories': [{'id': 1, 'slug': 'kurma', 'name': 'Kurma', 'parent_id': null, 'image': null}]},
        {'id': 2, 'type': 'banner', 'title': '', 'banners': [fakeBanner(5)]},
        {'id': 3, 'type': 'product_carousel', 'title': 'Hot now', 'source': 'top', 'products': [fakeProduct(id: 3, slug: 'z', name: 'Zed')]},
      ]),
      'flash_sale': {
        'ends_at': DateTime.now().add(const Duration(hours: 2)).toUtc().toIso8601String(),
        'products': [{...fakeProduct(id: 4, slug: 'f'), 'flash': {'price_sen': 100}}],
      },
    };
    final h = Harness(api: FakeApi(routes: {'GET home': (_, _) => payload}));
    await h.pump(tester);
    await tester.pump(const Duration(seconds: 1)); // the flash countdown ticks forever: no pumpAndSettle
    final flashY = tester.getTopLeft(find.byKey(const Key('home-flash'))).dy;
    expect(find.byKey(const Key('home-category-kurma')), findsOneWidget);
    expect(flashY, greaterThan(tester.getTopLeft(find.byKey(const Key('home-banners'))).dy));
    expect(flashY, lessThan(tester.getTopLeft(find.text('Hot now')).dy));
    await tester.tap(find.byKey(const Key('home-category-kurma')));
    await tester.pump(const Duration(seconds: 1));
    expect(h.router.state.uri.path, '/c/kurma');
  });

  testWidgets('new block types: notice, picked products, picked stores, picture tiles', (tester) async {
    final h = await pumpHome(tester, home(blocks: [
      {'id': 11, 'type': 'notice', 'title': '', 'text': 'Closed for Raya'},
      {'id': 12, 'type': 'notice', 'title': '', 'text': 'Second notice'},
      {'id': 13, 'type': 'picked_products', 'title': 'Our picks', 'products': [fakeProduct(id: 5, slug: 'pk', name: 'Picked Dates')]},
      {'id': 14, 'type': 'picked_stores', 'title': 'Stores', 'stores': [
        {'id': 1, 'slug': 'kedai-a', 'name': 'Kedai Alpha', 'logo': null},
      ]},
      {'id': 15, 'type': 'picture_tiles', 'title': '', 'tiles': [
        {'image': 'https://x.test/a.jpg', 'label': 'Tile One', 'link_url': null, 'app_url': '/c/kurma'},
        {'image': 'https://x.test/b.jpg', 'label': 'Tile Two', 'link_url': null, 'app_url': null},
      ]},
    ]));
    expect(tester.takeException(), isNull);
    for (final t in ['Closed for Raya', 'Second notice', 'Our picks', 'Picked Dates', 'Kedai Alpha', 'Tile One', 'Tile Two']) {
      expect(find.text(t), findsOneWidget, reason: t);
    }
    await tester.tap(find.byKey(const Key('home-store-kedai-a')));
    await tester.pump(const Duration(seconds: 1));
    expect(h.router.state.uri.path, '/s/kedai-a');
  });

  testWidgets('empty notice, stores and tiles draw nothing', (tester) async {
    await pumpHome(tester, home(blocks: [
      {'id': 21, 'type': 'notice', 'title': '', 'text': ''},
      {'id': 22, 'type': 'picked_stores', 'title': 'No stores here', 'stores': []},
      {'id': 23, 'type': 'picture_tiles', 'title': 'No tiles here', 'tiles': []},
      grid(1, 'Known one', 'a'),
    ]));
    expect(tester.takeException(), isNull);
    expect(find.byKey(const Key('home-block-21')), findsNothing);
    expect(find.byKey(const Key('home-block-22')), findsNothing);
    expect(find.byKey(const Key('home-block-23')), findsNothing);
    expect(find.text('Known one'), findsOneWidget);
  });
}
