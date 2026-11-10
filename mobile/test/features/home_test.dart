import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';

void main() {
  testWidgets('home renders categories and the featured grid from the API payload', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET products': (_, _) {
        return [
          fakeProduct(id: 1, slug: 'madu-tualang', name: 'Madu Tualang Asli', priceSen: 4590),
          fakeProduct(id: 2, slug: 'kurma-ajwa', name: 'Kurma Ajwa Premium', priceSen: 3200),
        ];
      },
      'GET categories': (_, _) => [
            {'id': 1, 'slug': 'makanan', 'name': 'Makanan', 'parent_id': null},
            {'id': 2, 'slug': 'kurma', 'name': 'Kurma', 'parent_id': null},
          ],
    }, meta: {'current_page': 1, 'last_page': 1, 'total': 2});
    final h = Harness(api: api);
    await h.pump(tester);
    await tester.pumpAndSettle();

    expect(h.router.state.uri.path, '/home');
    expect(api.calls, contains('GET products?sort=top'));
    expect(find.byKey(const Key('home-categories')), findsOneWidget);
    expect(find.text('Makanan'), findsOneWidget);
    expect(find.byKey(const Key('product-card-madu-tualang')), findsOneWidget);
    expect(find.byKey(const Key('product-card-kurma-ajwa')), findsOneWidget);
    expect(find.text('Madu Tualang Asli'), findsOneWidget);
    expect(find.text('RM 45.90'), findsOneWidget);
    expect(find.text('RM 32.00'), findsOneWidget);
    expect(find.byKey(const Key('souk-cached-strip')), findsNothing);
  });

  testWidgets('home shows the khatam empty state when the catalogue is empty', (tester) async {
    usePhone(tester);
    final h = Harness(api: FakeApi(routes: {'GET products': (_, _) => [], 'GET categories': (_, _) => []}));
    await h.pump(tester);
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('home-empty')), findsOneWidget);
    expect(find.text('No products yet'), findsOneWidget);
  });

  testWidgets('Featured "View all" opens the featured list and loads its next page', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET products': (q, _) => (q?['page'] ?? 1) == 1
          ? [fakeProduct(id: 1, slug: 'madu-tualang', name: 'Madu Tualang Asli')]
          : [fakeProduct(id: 2, slug: 'kurma-ajwa', name: 'Kurma Ajwa Premium')],
      'GET categories': (_, _) => [],
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
}
