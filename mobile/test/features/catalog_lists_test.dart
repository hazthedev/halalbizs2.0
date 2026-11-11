import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/features/catalog/reviews.dart';

import '../helpers.dart';

/// `count` products starting at id [from] (slugs `p<id>`).
List<Map<String, dynamic>> _products(int from, int count) =>
    [for (var i = from; i < from + count; i++) fakeProduct(id: i, slug: 'p$i', name: 'Product $i')];

Map<String, dynamic> _review(int id, {List<String> photos = const []}) => {
      'id': id,
      'rating': 4,
      'body': 'Review body $id',
      'buyer_name': 'Aisyah R.',
      'photos': photos,
      'created_at': '2026-10-15T10:00:00+08:00',
      'seller_reply': null,
    };

/// `page` from the query, `last_page: 2`: every list here has two pages.
Map<String, dynamic> _twoPages(String _, Map<String, dynamic>? q) =>
    {'page': int.parse('${q?['page'] ?? 1}'), 'per_page': 24, 'total': 30, 'last_page': 2};

void main() {
  group('product reviews (gap item 3)', () {
    testWidgets('product page shows the first reviews with rating, name, date, text and photos', (tester) async {
      usePhone(tester);
      final api = FakeApi(routes: {
        'GET products': (_, _) => [],
        'GET categories': (_, _) => [],
        'GET products/madu-tualang': (_, _) => fakeProduct(),
        'GET products/madu-tualang/reviews': (_, _) => [
              _review(1, photos: ['https://img.test/r1.jpg']),
              for (var i = 2; i <= 10; i++) _review(i),
            ],
      }, meta: {'page': 1, 'per_page': 10, 'total': 12, 'last_page': 2});
      await Harness(api: api).pump(tester, location: '/p/madu-tualang');
      await tester.scrollUntilVisible(find.byKey(const Key('product-tab-reviews')), 300, scrollable: find.byType(Scrollable).first);
      await tester.drag(find.byType(Scrollable).first, const Offset(0, -150)); // clear of the sticky buy bar
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('product-tab-reviews')));
      await tester.pumpAndSettle();

      await tester.scrollUntilVisible(find.byKey(const Key('review-1')), 300, scrollable: find.byType(Scrollable).first);
      expect(find.text('12 reviews'), findsWidgets);
      expect(find.byKey(const Key('review-1')), findsOneWidget);
      expect(find.byKey(const Key('review-3')), findsOneWidget);
      expect(find.byKey(const Key('review-4')), findsNothing, reason: 'the product page shows 3, See all shows the rest');
      expect(find.text('Review body 1'), findsOneWidget);
      expect(find.text('Aisyah R.'), findsWidgets);
      expect(find.text('15 Oct 2026'), findsWidgets);
      expect(find.byKey(const Key('review-1-photo-0')), findsOneWidget);
      expect(find.byKey(const Key('review-1-stars-4')), findsOneWidget);
    });

    testWidgets('See all opens the paged list; scrolling to the end loads page 2', (tester) async {
      usePhone(tester);
      final api = FakeApi(routes: {
        'GET products': (_, _) => [],
        'GET categories': (_, _) => [],
        'GET products/madu-tualang': (_, _) => fakeProduct(),
        'GET products/madu-tualang/reviews': (q, _) =>
            '${q?['page'] ?? 1}' == '1' ? [for (var i = 1; i <= 10; i++) _review(i)] : [_review(11), _review(12)],
      }, metaFor: (path, q) => {'page': int.parse('${q?['page'] ?? 1}'), 'per_page': 10, 'total': 12, 'last_page': 2});
      final h = Harness(api: api);
      await h.pump(tester, location: '/p/madu-tualang');
      await tester.scrollUntilVisible(find.byKey(const Key('product-tab-reviews')), 300, scrollable: find.byType(Scrollable).first);
      await tester.drag(find.byType(Scrollable).first, const Offset(0, -150)); // clear of the sticky buy bar
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('product-tab-reviews')));
      await tester.pumpAndSettle();

      final viewAll = find.descendant(of: find.byKey(const Key('product-reviews')), matching: find.text('View all'));
      await tester.scrollUntilVisible(viewAll, 300, scrollable: find.byType(Scrollable).first);
      await tester.drag(find.byType(Scrollable).first, const Offset(0, -150)); // clear of the sticky buy bar
      await tester.pumpAndSettle();
      await tester.tap(viewAll);
      await tester.pumpAndSettle();
      expect(find.byType(ReviewsScreen), findsOneWidget);
      expect(find.byKey(const Key('review-1')), findsOneWidget);

      await tester.drag(find.byKey(const Key('souk-page-scroll')), const Offset(0, -6000));
      await tester.pumpAndSettle();
      expect(api.calls, contains('GET products/madu-tualang/reviews?page=2'));
      await tester.scrollUntilVisible(find.byKey(const Key('review-12')), 300, scrollable: find.byType(Scrollable).first);
      expect(find.byKey(const Key('review-12')), findsOneWidget);
    });

    testWidgets('reviews list shows the seller reply and the filter chips send the web filter key', (tester) async {
      usePhone(tester);
      final api = FakeApi(routes: {
        'GET products': (_, _) => [],
        'GET categories': (_, _) => [],
        'GET products/madu-tualang': (_, _) => fakeProduct(),
        'GET products/madu-tualang/reviews': (q, _) => q?['filter'] == 'photos'
            ? []
            : [
                {..._review(1), 'seller_reply': {'body': 'Thank you!', 'at': null}},
                _review(2),
              ],
      }, meta: {'page': 1, 'per_page': 10, 'total': 2, 'last_page': 1});
      await Harness(api: api).pump(tester, location: '/p/madu-tualang/reviews');
      expect(find.byKey(const Key('review-1-reply')), findsOneWidget);
      expect(find.text('Seller response'), findsOneWidget);
      expect(find.text('Thank you!'), findsOneWidget);
      expect(find.byKey(const Key('review-2-reply')), findsNothing);

      await tester.tap(find.byKey(const Key('reviews-filter-photos')));
      await tester.pumpAndSettle();
      expect(api.calls, contains('GET products/madu-tualang/reviews?filter=photos'));
      expect(find.byKey(const Key('reviews-filter-empty')), findsOneWidget);
    });

    testWidgets('no reviews: no section, and a failed reviews call never breaks the product page', (tester) async {
      usePhone(tester);
      final api = FakeApi(routes: {
        'GET products': (_, _) => [],
        'GET categories': (_, _) => [],
        'GET products/madu-tualang': (_, _) => fakeProduct(),
      });
      await Harness(api: api).pump(tester, location: '/p/madu-tualang');
      expect(find.byKey(const Key('product-name')), findsOneWidget);
      expect(find.byKey(const Key('product-reviews')), findsNothing);
    });
  });

  group('load more, sort and price filter (gap item 4)', () {
    testWidgets('category: scrolling to the end appends page 2', (tester) async {
      usePhone(tester);
      final api = FakeApi(routes: {
        'GET categories': (_, _) => [],
        'GET products': (q, _) => '${q?['page'] ?? 1}' == '1' ? _products(1, 24) : _products(25, 6),
      }, metaFor: _twoPages);
      await Harness(api: api).pump(tester, location: '/c/rice-grains');
      expect(find.byKey(const Key('product-card-p1')), findsOneWidget);

      await tester.drag(find.byKey(const Key('souk-page-scroll')), const Offset(0, -20000));
      await tester.pumpAndSettle();
      expect(api.calls, contains('GET products?category=rice-grains&page=2'));
      await tester.scrollUntilVisible(find.byKey(const Key('product-card-p30')), 300, scrollable: find.byType(Scrollable).first);
      expect(find.byKey(const Key('product-card-p30')), findsOneWidget);
    });

    testWidgets('category: the filter sheet sends sort + price in sen and resets to page 1', (tester) async {
      usePhone(tester);
      final api = FakeApi(routes: {
        'GET categories': (_, _) => [],
        'GET products': (_, _) => _products(1, 2),
      });
      await Harness(api: api).pump(tester, location: '/c/rice-grains');

      await tester.tap(find.byKey(const Key('product-filter')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('filter-sort-price_asc')));
      await tester.enterText(find.byKey(const Key('filter-min')), '10');
      await tester.enterText(find.byKey(const Key('filter-max')), '25.50');
      await tester.tap(find.byKey(const Key('filter-apply')));
      await tester.pumpAndSettle();
      expect(api.calls.last, 'GET products?category=rice-grains&max_sen=2550&min_sen=1000&sort=price_asc');
      expect(find.text('Sort & filter · 2'), findsOneWidget);

      await tester.tap(find.byKey(const Key('product-filter')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('filter-reset')));
      await tester.pumpAndSettle();
      expect(api.calls.last, 'GET products?category=rice-grains');
    });

    testWidgets('filter sheet refuses a min above the max', (tester) async {
      usePhone(tester);
      final api = FakeApi(routes: {
        'GET categories': (_, _) => [],
        'GET products': (_, _) => _products(1, 2),
      });
      await Harness(api: api).pump(tester, location: '/c/rice-grains');
      final before = api.calls.length;

      await tester.tap(find.byKey(const Key('product-filter')));
      await tester.pumpAndSettle();
      await tester.enterText(find.byKey(const Key('filter-min')), '30');
      await tester.enterText(find.byKey(const Key('filter-max')), '20');
      await tester.tap(find.byKey(const Key('filter-apply')));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('filter-apply')), findsOneWidget, reason: 'sheet stays open');
      expect(api.calls.length, before);
    });

    testWidgets('search: sort + price go to GET search with the query and category', (tester) async {
      usePhone(tester);
      final api = FakeApi(routes: {
        'GET products': (_, _) => [],
        'GET categories': (_, _) => [
              {'id': 1, 'slug': 'rice-grains', 'name': 'Rice & Grains', 'parent_id': null},
            ],
        'GET search': (_, _) => [fakeProduct()],
      });
      await Harness(api: api).pump(tester, location: '/search');
      await tester.enterText(find.byKey(const Key('search-input')), 'beras');
      await tester.pump(const Duration(milliseconds: 350));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('search-category-rice-grains')));
      await tester.pumpAndSettle();

      await tester.tap(find.byKey(const Key('product-filter')));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('filter-sort-relevance')), findsOneWidget);
      await tester.tap(find.byKey(const Key('filter-sort-top')));
      await tester.enterText(find.byKey(const Key('filter-min')), '5');
      await tester.tap(find.byKey(const Key('filter-apply')));
      await tester.pumpAndSettle();
      expect(api.calls.last, 'GET search?category=rice-grains&min_sen=500&q=beras&sort=top');
    });

    testWidgets('shop: sort + load more use stores/{slug}/products; a price range uses products?store=', (tester) async {
      usePhone(tester);
      final api = FakeApi(routes: {
        'GET categories': (_, _) => [],
        'GET stores/kedai-madu': (_, _) => {'id': 3, 'name': 'Kedai Madu', 'slug': 'kedai-madu'},
        'GET stores/kedai-madu/products': (q, _) => '${q?['page'] ?? 1}' == '1' ? _products(1, 24) : _products(25, 6),
        'GET products': (_, _) => _products(1, 1),
      }, metaFor: _twoPages);
      await Harness(api: api).pump(tester, location: '/s/kedai-madu');

      await tester.tap(find.byKey(const Key('product-filter')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('filter-sort-top')));
      await tester.tap(find.byKey(const Key('filter-apply')));
      await tester.pumpAndSettle();
      expect(api.calls.last, 'GET stores/kedai-madu/products?sort=top');

      await tester.drag(find.byKey(const Key('souk-page-scroll')), const Offset(0, -20000));
      await tester.pumpAndSettle();
      expect(api.calls, contains('GET stores/kedai-madu/products?page=2&sort=top'));

      await tester.drag(find.byKey(const Key('souk-page-scroll')), const Offset(0, 40000));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('product-filter')));
      await tester.pumpAndSettle();
      await tester.enterText(find.byKey(const Key('filter-max')), '40');
      await tester.tap(find.byKey(const Key('filter-apply')));
      await tester.pumpAndSettle();
      expect(api.calls.last, 'GET products?max_sen=4000&sort=top&store=kedai-madu');
    });
  });

  group('shop header (gap item 8)', () {
    testWidgets('shows banner, joined date, product count and the full About text', (tester) async {
      usePhone(tester);
      final api = FakeApi(routes: {
        'GET categories': (_, _) => [],
        'GET stores/kedai-madu': (_, _) => {
              'id': 3,
              'name': 'Kedai Madu',
              'slug': 'kedai-madu',
              'description': '<p>Honey from Pahang.</p><p>Second paragraph of the full story.</p>',
              'banner': 'https://img.test/banner.jpg',
              'logo': null,
              'rating_avg': 4.5,
              'product_count': 37,
              'joined_at': '2024-03-15T08:00:00+08:00',
            },
        'GET stores/kedai-madu/products': (_, _) => _products(1, 2),
      });
      await Harness(api: api).pump(tester, location: '/s/kedai-madu');

      expect(find.byKey(const Key('store-banner')), findsOneWidget);
      expect(find.text('Joined Mar 2024'), findsOneWidget);
      expect(find.text('37 products'), findsOneWidget);

      await tester.tap(find.byKey(const Key('store-about')));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('store-about-sheet')), findsOneWidget);
      expect(find.textContaining('Second paragraph of the full story.', findRichText: true), findsWidgets);
    });

    testWidgets('no banner, no joined date: neither row renders', (tester) async {
      usePhone(tester);
      final api = FakeApi(routes: {
        'GET categories': (_, _) => [],
        'GET stores/kedai-madu': (_, _) => {'id': 3, 'name': 'Kedai Madu', 'slug': 'kedai-madu'},
        'GET stores/kedai-madu/products': (_, _) => [],
      });
      await Harness(api: api).pump(tester, location: '/s/kedai-madu');
      expect(find.byKey(const Key('store-banner')), findsNothing);
      expect(find.byKey(const Key('store-joined')), findsNothing);
      expect(find.byKey(const Key('store-about')), findsNothing);
    });
  });
}
