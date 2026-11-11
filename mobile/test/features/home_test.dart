import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';

/// `GET home` payload (HomeController).
Map<String, dynamic> fakeHome({
  List<Map<String, dynamic>> featured = const [],
  List<Map<String, dynamic>> categories = const [],
  List<Map<String, dynamic>> banners = const [],
  Map<String, dynamic>? flashSale,
}) =>
    {'featured': featured, 'categories': categories, 'flash_sale': flashSale, 'banners': banners};

Map<String, dynamic> fakeBanner(int id, {String? appUrl}) => {
      'id': id,
      'title': 'Banner $id',
      'subtitle': null,
      'cta_label': null,
      'link_url': null,
      'app_url': appUrl,
      'image': 'https://halalbizs.test/banner-$id.jpg',
    };

void main() {
  testWidgets('home renders the featured grid (no category pills) from the API payload', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET home': (_, _) => fakeHome(
            featured: [
              fakeProduct(id: 1, slug: 'madu-tualang', name: 'Madu Tualang Asli', priceSen: 4590),
              fakeProduct(id: 2, slug: 'kurma-ajwa', name: 'Kurma Ajwa Premium', priceSen: 3200),
            ],
            categories: [
              {'id': 1, 'slug': 'makanan', 'name': 'Makanan', 'parent_id': null},
              {'id': 2, 'slug': 'kurma', 'name': 'Kurma', 'parent_id': null},
            ],
          ),
    });
    final h = Harness(api: api);
    await h.pump(tester);
    await tester.pumpAndSettle();

    expect(h.router.state.uri.path, '/home');
    expect(api.calls, contains('GET home'));
    expect(api.calls, isNot(contains('GET products?sort=top')));
    expect(find.byKey(const Key('home-categories')), findsNothing); // Haze 11-11: no category pills on Home
    expect(find.text('Makanan'), findsNothing);
    expect(find.byKey(const Key('product-card-madu-tualang')), findsOneWidget);
    expect(find.byKey(const Key('product-card-kurma-ajwa')), findsOneWidget);
    expect(find.text('Madu Tualang Asli'), findsOneWidget);
    expect(find.text('RM 45.90'), findsOneWidget);
    expect(find.text('RM 32.00'), findsOneWidget);
    expect(find.byKey(const Key('souk-cached-strip')), findsNothing);
  });

  testWidgets('home shows the khatam empty state when the catalogue is empty', (tester) async {
    usePhone(tester);
    final h = Harness(api: FakeApi(routes: {'GET home': (_, _) => fakeHome()}));
    await h.pump(tester);
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('home-empty')), findsOneWidget);
    expect(find.text('No products yet'), findsOneWidget);
    expect(find.byKey(const Key('home-banners')), findsNothing);
    expect(find.byKey(const Key('home-flash')), findsNothing);
  });

  testWidgets('Featured "View all" opens the featured list and loads its next page', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET products': (q, _) => (q?['page'] ?? 1) == 1
          ? [fakeProduct(id: 1, slug: 'madu-tualang', name: 'Madu Tualang Asli')]
          : [fakeProduct(id: 2, slug: 'kurma-ajwa', name: 'Kurma Ajwa Premium')],
      'GET home': (_, _) => fakeHome(featured: [fakeProduct(id: 1, slug: 'madu-tualang', name: 'Madu Tualang Asli')]),
    }, meta: {'current_page': 1, 'last_page': 2, 'total': 2});
    final h = Harness(api: api);
    await h.pump(tester);
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('souk-section-link')));
    await tester.pumpAndSettle();
    expect(h.router.state.uri.path, '/featured');
    expect(find.text('Featured'), findsWidgets);
    expect(find.byKey(const Key('souk-back')), findsOneWidget);
    expect(find.byKey(const Key('product-card-madu-tualang')), findsOneWidget);

    await tester.tap(find.byKey(const Key('featured-load-more')));
    await tester.pumpAndSettle();
    expect(api.calls, contains('GET products?page=2&sort=top'));
    expect(find.byKey(const Key('product-card-kurma-ajwa')), findsOneWidget);
  });

  testWidgets('banners render as a carousel with dots; swipe, auto-advance and tap go to the in-app link', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET home': (_, _) => fakeHome(
            featured: [fakeProduct()],
            banners: [
              fakeBanner(1, appUrl: '/c/makanan'),
              fakeBanner(2, appUrl: '/p/madu-tualang'),
              fakeBanner(3),
              {...fakeBanner(4), 'image': null}, // no artwork = not shown
            ],
          ),
      'GET products': (_, _) => [],
      'GET products/madu-tualang': (_, _) => fakeProduct(),
    });
    final h = Harness(api: api);
    await h.pump(tester);
    await tester.pump();
    await tester.pump();

    expect(find.byKey(const Key('home-banners')), findsOneWidget);
    expect(find.byKey(const Key('home-banner-dot-0')), findsOneWidget);
    expect(find.byKey(const Key('home-banner-dot-2')), findsOneWidget);
    expect(find.byKey(const Key('home-banner-dot-3')), findsNothing);
    expect(find.bySemanticsLabel('Banner 1'), findsOneWidget);

    // swipe left = next banner
    await tester.drag(find.byKey(const Key('home-banners')), const Offset(-300, 0));
    await tester.pump(const Duration(milliseconds: 500));
    expect(find.bySemanticsLabel('Banner 2'), findsOneWidget);

    // auto-advance after 6 s
    await tester.pump(const Duration(seconds: 6));
    await tester.pump(const Duration(milliseconds: 500));
    expect(find.bySemanticsLabel('Banner 3'), findsOneWidget);
    await tester.pump(const Duration(seconds: 6));
    await tester.pump(const Duration(milliseconds: 500));
    expect(find.bySemanticsLabel('Banner 1'), findsOneWidget); // wraps

    await tester.tap(find.byKey(const Key('home-banner-1')));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 500));
    expect(h.router.state.uri.path, '/c/makanan');
  });

  testWidgets('flash sale strip shows the countdown, promo and struck price; tap opens the product', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET home': (_, _) => fakeHome(
            featured: [fakeProduct(id: 2, slug: 'kurma-ajwa')],
            flashSale: {
              'ends_at': DateTime.now().toUtc().add(const Duration(hours: 1, minutes: 2, seconds: 30)).toIso8601String(),
              'products': [
                {
                  ...fakeProduct(id: 1, slug: 'madu-tualang', name: 'Madu Tualang Asli', priceSen: 4590),
                  'flash': {'variant_id': 10, 'price_sen': 2990, 'remaining': 7, 'percent_claimed': 30},
                },
              ],
            },
          ),
      'GET products/madu-tualang': (_, _) => fakeProduct(),
    });
    final h = Harness(api: api);
    await h.pump(tester);
    await tester.pump();
    await tester.pump();

    expect(find.byKey(const Key('home-flash')), findsOneWidget);
    expect(find.text('Flash Sale'), findsOneWidget);
    expect(find.textContaining(RegExp(r'^01:02:[23]\d$')), findsOneWidget);
    expect(find.text('RM 29.90'), findsOneWidget);
    final struck = tester.widget<Text>(find.text('RM 45.90'));
    expect(struck.style?.decoration, TextDecoration.lineThrough);

    await tester.tap(find.byKey(const Key('flash-item-madu-tualang')));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 500));
    expect(h.router.state.uri.path, '/p/madu-tualang');
  });
}
