import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';

void main() {
  testWidgets('search waits 300 ms after the last keystroke and needs 2 characters', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET search': (q, _) => [fakeProduct(name: 'Madu Tualang Asli')],
    });
    final h = Harness(api: api);
    await h.pump(tester, location: '/search');
    Iterable<String> searches() => api.calls.where((c) => c.startsWith('GET search'));

    await tester.enterText(find.byKey(const Key('search-input')), 'm');
    await tester.pump(const Duration(milliseconds: 400));
    expect(searches(), isEmpty, reason: 'one character never searches');

    await tester.enterText(find.byKey(const Key('search-input')), 'ma');
    await tester.pump(const Duration(milliseconds: 100));
    expect(searches(), isEmpty, reason: 'debounce not elapsed');
    await tester.enterText(find.byKey(const Key('search-input')), 'mad');
    await tester.pump(const Duration(milliseconds: 200));
    expect(searches(), isEmpty, reason: 'timer restarted by the third keystroke');
    await tester.pump(const Duration(milliseconds: 150));
    expect(searches().length, 1);
    expect(searches().first, 'GET search?q=mad');

    await tester.pumpAndSettle();
    expect(find.byKey(const Key('product-card-madu-tualang')), findsOneWidget);
  });

  testWidgets('offline search shows the connection empty state instead of calling', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {'GET products': (_, _) => [], 'GET categories': (_, _) => []});
    final h = Harness(api: api, offline: true);
    await h.pump(tester, location: '/search');
    await tester.enterText(find.byKey(const Key('search-input')), 'madu');
    await tester.pump(const Duration(milliseconds: 350));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('search-offline')), findsOneWidget);
    expect(find.text('Search needs a connection'), findsOneWidget);
    expect(api.calls.where((c) => c.startsWith('GET search')), isEmpty);
  });

  testWidgets('category chip and sort send the API keys the server accepts', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [
            {'id': 1, 'slug': 'rice-grains', 'name': 'Rice & Grains', 'parent_id': null},
          ],
      'GET search': (q, _) => [fakeProduct()],
    });
    final h = Harness(api: api);
    await h.pump(tester, location: '/search');
    String lastSearch() => api.calls.lastWhere((c) => c.startsWith('GET search'));

    await tester.enterText(find.byKey(const Key('search-input')), 'aut');
    await tester.pump(const Duration(milliseconds: 350));
    await tester.pumpAndSettle();
    expect(lastSearch(), 'GET search?q=aut');

    await tester.tap(find.byKey(const Key('search-category-rice-grains')));
    await tester.pumpAndSettle();
    expect(lastSearch(), contains('category=rice-grains'));

    for (final (label, key) in [
      ('Price: low to high', 'price_asc'),
      ('Popular', 'top'),
      ('Newest', 'latest'),
    ]) {
      await tester.tap(find.byKey(const Key('search-sort')));
      await tester.pumpAndSettle();
      await tester.tap(find.text(label).last);
      await tester.pumpAndSettle();
      expect(lastSearch(), allOf(contains('sort=$key'), contains('category=rice-grains')));
    }
  });
}
