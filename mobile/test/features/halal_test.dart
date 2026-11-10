import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';

/// `ProductResource.halal` (list keys; [detail] adds the PDP keys).
Map<String, dynamic> fakeHalal({String verdict = 'verified', bool detail = true, int days = 365}) => {
      'verdict': verdict,
      'number': 'MY-JKM-2140-118',
      'issuing_body': 'JAKIM',
      'valid_from': '2025-01-07',
      'valid_to': verdict == 'lapsed' ? '2026-11-01' : '2029-01-07',
      'days_remaining': days,
      if (detail) ...{
        'issuing_body_name': 'Department of Islamic Development Malaysia',
        'scheme': 'MS 1500:2019',
        'scope_note': 'raw honey, honey blends',
        'facility': 'Kuala Terengganu plant',
        'dedicated_facility': true,
        'export_paperwork': false,
        'register_url': 'https://halalbizs2.0.test/certificate-register?no=MY-JKM-2140-118',
      },
    };

Map<String, dynamic> halalProduct({Map<String, dynamic>? halal, String slug = 'madu-tualang', int id = 1}) => {
      ...fakeProduct(id: id, slug: slug),
      'halal': halal,
      'halal_batch_code': 'B-2211',
      'halal_packed_on': '2026-10-01',
      'metafields': [
        {
          'key': 'halal',
          'label': 'Halal certification',
          'rows': [
            {'label': 'Halal certification body', 'value': 'JAKIM'},
          ],
        },
        {
          'key': 'details',
          'label': 'Product details',
          'rows': [
            {'label': 'Country of origin', 'value': 'Malaysia'},
          ],
        },
      ],
    };

void main() {
  testWidgets('a product card says Halal certified only when the verdict is verified', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET products': (_, _) => [
            halalProduct(id: 1, slug: 'madu-tualang', halal: fakeHalal(detail: false)),
            halalProduct(id: 2, slug: 'kurma-ajwa', halal: fakeHalal(verdict: 'lapsed', detail: false)),
            halalProduct(id: 3, slug: 'sambal', halal: null),
          ],
      'GET categories': (_, _) => [],
    });
    await Harness(api: api).pump(tester, location: '/c/honey');

    expect(find.byKey(const Key('product-halal-madu-tualang')), findsOneWidget);
    expect(find.byKey(const Key('product-halal-kurma-ajwa')), findsNothing);
    expect(find.byKey(const Key('product-halal-sambal')), findsNothing);
    expect(find.text('HALAL CERTIFIED'), findsOneWidget);
  });

  testWidgets('product page shows the certificate panel, scope, traceability and opens the register', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET products/madu-tualang': (_, _) => halalProduct(halal: fakeHalal()),
    });
    final h = Harness(api: api);
    await h.pump(tester, location: '/p/madu-tualang');

    expect(find.byKey(const Key('halal-panel-verified')), findsOneWidget);
    expect(find.text('HALAL CERTIFICATE VERIFIED'), findsOneWidget);
    expect(find.text('JAKIM · MY-JKM-2140-118 · valid to 7 Jan 2029'), findsOneWidget);

    await tester.scrollUntilVisible(find.byKey(const Key('halal-traceability')), 200);
    expect(find.text('Ingredients & scope'), findsOneWidget);
    expect(find.textContaining('Certified scope: raw honey, honey blends.'), findsOneWidget);
    expect(find.text('Kuala Terengganu plant'), findsOneWidget);
    expect(find.text('B-2211 · packed 1 Oct 2026'), findsOneWidget);
    expect(find.text('JAKIM · MY-JKM-2140-118'), findsOneWidget);
    expect(find.text('7 Jan 2029 · 365 days remaining'), findsOneWidget);

    await tester.scrollUntilVisible(find.byKey(const Key('product-metafields')), 200);
    expect(find.text('Halal certification'), findsOneWidget);
    expect(find.text('Halal certification body'), findsOneWidget);
    expect(find.text('Malaysia'), findsOneWidget);

    await tester.scrollUntilVisible(find.byKey(const Key('halal-read-record')), -200);
    await tester.tap(find.byKey(const Key('halal-read-record')));
    await tester.pump();
    expect(h.browser.opened, ['https://halalbizs2.0.test/certificate-register?no=MY-JKM-2140-118']);
  });

  testWidgets('a lapsed certificate reads lapsed in the panel and the expiry row', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET products/madu-tualang': (_, _) => halalProduct(halal: fakeHalal(verdict: 'lapsed', days: -9)),
    });
    await Harness(api: api).pump(tester, location: '/p/madu-tualang');

    expect(find.byKey(const Key('halal-panel-lapsed')), findsOneWidget);
    expect(find.text('CERTIFICATE LAPSED'), findsOneWidget);
    expect(find.text('JAKIM · MY-JKM-2140-118 · expired 1 Nov 2026'), findsOneWidget);
    await tester.scrollUntilVisible(find.byKey(const Key('halal-traceability')), 200);
    expect(find.text('1 Nov 2026 · lapsed'), findsOneWidget);
  });

  testWidgets('no certificate record means no panel, as on the web', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET products/madu-tualang': (_, _) => fakeProduct(),
    });
    await Harness(api: api).pump(tester, location: '/p/madu-tualang');

    expect(find.byKey(const Key('product-name')), findsOneWidget);
    expect(find.byKey(const Key('halal-panel-verified')), findsNothing);
    expect(find.byKey(const Key('halal-traceability')), findsNothing);
    expect(find.byKey(const Key('product-metafields')), findsNothing);
  });

  testWidgets('search filters by certifying body and assurance', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET search': (_, _) => [fakeProduct()],
    });
    await Harness(api: api).pump(tester, location: '/search');
    await tester.enterText(find.byKey(const Key('search-input')), 'madu');
    await tester.pump(const Duration(milliseconds: 350));
    await tester.pumpAndSettle();

    // The facets live in the sort/filter sheet (one sheet for search, category and shop).
    Future<void> pick(String key) async {
      await tester.tap(find.byKey(const Key('product-filter')));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.byKey(Key(key)));
      await tester.tap(find.byKey(Key(key)));
      await tester.tap(find.byKey(const Key('filter-apply')));
      await tester.pumpAndSettle();
    }

    await pick('filter-certifier-JAKIM');
    expect(api.calls.last, 'GET search?certifier%5B%5D=%5BJAKIM%5D&q=madu');

    await pick('filter-assure-dedicated');
    expect(api.calls.last, 'GET search?assure%5B%5D=%5Bdedicated%5D&certifier%5B%5D=%5BJAKIM%5D&q=madu');

    await pick('filter-certifier-JAKIM');
    expect(api.calls.last, 'GET search?assure%5B%5D=%5Bdedicated%5D&q=madu');
  });

  testWidgets('category and shop lists take the same halal facets', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET products': (_, _) => [fakeProduct()],
      'GET categories': (_, _) => [],
      'GET stores/kedai-madu': (_, _) => {'id': 3, 'name': 'Kedai Madu', 'slug': 'kedai-madu'},
      'GET stores/kedai-madu/products': (_, _) => [fakeProduct()],
    });
    final h = Harness(api: api);
    await h.pump(tester, location: '/c/honey');
    Future<void> pick(String key) async {
      await tester.tap(find.byKey(const Key('product-filter')));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.byKey(Key(key)));
      await tester.tap(find.byKey(Key(key)));
      await tester.tap(find.byKey(const Key('filter-apply')));
      await tester.pumpAndSettle();
    }

    await pick('filter-certifier-MUIS');
    expect(api.calls.last, 'GET products?category=honey&certifier%5B%5D=%5BMUIS%5D');

    h.router.go('/s/kedai-madu');
    await tester.pumpAndSettle();
    await pick('filter-assure-export');
    // stores/{slug}/products takes sort only, so a facet goes through products?store=.
    expect(api.calls.last, 'GET products?assure%5B%5D=%5Bexport%5D&store=kedai-madu');
  });
}
