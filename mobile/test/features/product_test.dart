import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/core/widgets/souk_kit.dart';

import '../helpers.dart';

void main() {
  testWidgets('product page formats 1250 sen as RM 12.50 and shows the sale strike-through', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET products/sambal-nyonya': (_, _) =>
          fakeProduct(id: 3, slug: 'sambal-nyonya', name: 'Sambal Nyonya Pedas', priceSen: 1500, salePriceSen: 1250, stock: 3),
    });
    final h = Harness(api: api);
    await h.pump(tester, location: '/p/sambal-nyonya');

    expect(find.byKey(const Key('product-name')), findsOneWidget);
    expect(find.text('Sambal Nyonya Pedas'), findsWidgets);
    expect(find.text('RM 12.50'), findsOneWidget);
    expect(find.text('RM 15.00'), findsOneWidget); // struck-through regular price
    expect(find.byKey(const Key('product-regular-price')), findsOneWidget);
    expect(find.text('Only 3 left'), findsOneWidget);
    expect(find.byKey(const Key('product-store')), findsOneWidget);
    expect(find.text('Kedai Madu'), findsOneWidget);
    expect(find.byKey(const Key('product-add-to-cart')), findsOneWidget);
  });

  testWidgets('guest tapping Add to cart is sent to login with a return path', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET products/madu-tualang': (_, _) => fakeProduct(),
    });
    final h = Harness(api: api);
    await h.pump(tester, location: '/p/madu-tualang');
    await tester.tap(find.byKey(const Key('product-add-to-cart')));
    await tester.pumpAndSettle();
    expect(h.router.state.uri.path, '/login');
    expect(h.router.state.uri.queryParameters['from'], '/p/madu-tualang');
  });

  testWidgets('signed-in Add to cart posts the variant + qty, updates the badge and offers View cart', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET me': (_, _) => fakeUser(),
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET products/madu-tualang': (_, _) => fakeProduct(),
      'GET cart': (_, _) => {'groups': [], 'item_count': 0, 'selected_count': 0, 'selected_subtotal_sen': 0, 'purchasing_enabled': true},
      'POST cart/items': (_, body) {
        expect((body as Map)['variant_id'], 10);
        expect(body['qty'], 2);
        return {'groups': [], 'item_count': 2, 'selected_count': 2, 'selected_subtotal_sen': 2500, 'purchasing_enabled': true};
      },
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/p/madu-tualang');
    await tester.tap(find.byKey(const Key('qty-plus')));
    await tester.pump();
    await tester.tap(find.byKey(const Key('product-add-to-cart')));
    await tester.pumpAndSettle();
    expect(api.calls, contains('POST cart/items'));
    expect(find.text('Added to cart'), findsOneWidget);
    expect(find.byKey(const Key('tab-badge-2')), findsOneWidget);
    await tester.tap(find.byKey(const Key('product-view-cart')));
    await tester.pumpAndSettle();
    expect(h.router.state.uri.path, '/cart');
  });

  testWidgets('Added to cart bar sits above the bottom buttons, hides itself and never follows to Cart', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET me': (_, _) => fakeUser(),
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET products/madu-tualang': (_, _) => fakeProduct(),
      'GET cart': (_, _) => {'groups': [], 'item_count': 0, 'selected_count': 0, 'selected_subtotal_sen': 0, 'purchasing_enabled': true},
      'POST cart/items': (_, _) => {'groups': [], 'item_count': 1, 'selected_count': 1, 'selected_subtotal_sen': 1250, 'purchasing_enabled': true},
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/p/madu-tualang');
    await tester.tap(find.byKey(const Key('product-add-to-cart')));
    await tester.pumpAndSettle();
    final bar = find.byType(SnackBar);
    expect(bar, findsOneWidget);
    expect(tester.getRect(bar).bottom, lessThanOrEqualTo(tester.getRect(find.byKey(const Key('qty-plus'))).top));
    expect(tester.getRect(bar).bottom, lessThanOrEqualTo(tester.getRect(find.byKey(const Key('product-add-to-cart'))).top));

    // Leaving the product page takes the bar with it.
    h.router.go('/cart');
    await tester.pumpAndSettle();
    expect(find.text('Added to cart'), findsNothing);

    // And on the product page it hides by itself.
    h.router.go('/p/madu-tualang');
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('product-add-to-cart')));
    await tester.pumpAndSettle();
    expect(find.text('Added to cart'), findsOneWidget);
    await tester.pump(const Duration(seconds: 5));
    await tester.pumpAndSettle();
    expect(find.text('Added to cart'), findsNothing);
  });

  testWidgets('a multi-variant product shows the price range and a Select row until a full pick', (tester) async {
    usePhone(tester);
    final h = Harness(api: pickerApi());
    await h.pump(tester, location: '/p/baju');

    expect(find.byKey(const Key('product-price')), findsOneWidget);
    expect(find.text('RM 50.00'), findsOneWidget);
    expect(find.text('RM 90.00'), findsOneWidget, reason: 'web PDP: min – max until a variant is picked');
    expect(find.text('Select: Colour, Size'), findsOneWidget);
    expect(find.byKey(const Key('product-stock')), findsNothing);
  });

  testWidgets('the Select row opens the sheet; picking Colour and Size sets the header price and stock', (tester) async {
    usePhone(tester);
    final h = Harness(api: pickerApi());
    await h.pump(tester, location: '/p/baju');
    await tester.tap(find.byKey(const Key('product-select')));
    await tester.pumpAndSettle();
    final sheet = find.byKey(const Key('picker-sheet'));
    expect(sheet, findsOneWidget);
    expect(find.descendant(of: sheet, matching: find.text('Colour')), findsOneWidget);
    expect(find.descendant(of: sheet, matching: find.text('Size')), findsOneWidget);

    await tester.tap(find.byKey(const Key('picker-value-102'))); // Blue
    await tester.tap(find.byKey(const Key('picker-value-202'))); // M
    await tester.pump();
    expect(find.descendant(of: find.byKey(const Key('picker-price')), matching: find.text('RM 77.40')), findsOneWidget);
    expect(find.descendant(of: find.byKey(const Key('picker-regular-price')), matching: find.text('RM 90.00')), findsOneWidget);
    expect(find.descendant(of: sheet, matching: find.text('Stock: 12')), findsOneWidget);

    await tester.tap(find.byKey(const Key('picker-close')));
    await tester.pumpAndSettle();
    expect(find.text('Blue / M'), findsOneWidget, reason: 'the page row shows the pick');
  });

  testWidgets('a value with no in-stock variant for the current picks is greyed out and cannot be picked', (tester) async {
    usePhone(tester);
    final h = Harness(api: pickerApi());
    await h.pump(tester, location: '/p/baju');
    await tester.tap(find.byKey(const Key('product-select')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('picker-value-103'))); // Green
    await tester.pump();

    SoukChip chip(int id) => tester.widget<SoukChip>(find.byKey(Key('picker-value-$id')));
    expect(chip(203).enabled, isFalse, reason: 'Green / L is out of stock');
    expect(chip(201).enabled, isTrue);
    await tester.tap(find.byKey(const Key('picker-value-203')), warnIfMissed: false);
    await tester.pump();
    expect(chip(203).selected, isFalse);
  });

  testWidgets('the sheet button waits for every pick, then Add to cart sends that variant and quantity', (tester) async {
    usePhone(tester);
    Object? added;
    final h = Harness(api: pickerApi(onAdd: (b) => added = b), token: 'tok');
    await h.pump(tester, location: '/p/baju');

    // Add to cart on the page with nothing picked opens the sheet instead of failing.
    await tester.tap(find.byKey(const Key('product-add-to-cart')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('picker-sheet')), findsOneWidget);
    expect(added, isNull);

    SoukButton confirm() => tester.widget<SoukButton>(find.byKey(const Key('picker-confirm')));
    expect(confirm().onPressed, isNull);
    await tester.tap(find.byKey(const Key('picker-value-102')));
    await tester.pump();
    expect(confirm().onPressed, isNull);
    expect(find.text('Choose a size'), findsOneWidget);

    await tester.tap(find.byKey(const Key('picker-value-202')));
    await tester.pump();
    expect(confirm().onPressed, isNotNull);
    await tester.tap(find.descendant(of: find.byKey(const Key('picker-sheet')), matching: find.byKey(const Key('qty-plus'))));
    await tester.pump();
    await tester.tap(find.byKey(const Key('picker-confirm')));
    await tester.pumpAndSettle();

    expect(added, {'variant_id': 1022, 'qty': 2});
    expect(find.byKey(const Key('picker-sheet')), findsNothing);
    expect(find.text('Added to cart'), findsOneWidget);
  });

  testWidgets('a single-variant product has no Select row and is preselected', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET products/madu-tualang': (_, _) => fakeProduct(),
    });
    final h = Harness(api: api);
    await h.pump(tester, location: '/p/madu-tualang');
    expect(find.byKey(const Key('product-select')), findsNothing);
    expect(find.text('12 in stock'), findsOneWidget);
  });

  testWidgets('three photos give three swipeable pages with dots; tapping one opens it full size', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET products/madu-tualang': (_, _) => {
            ...fakeProduct(),
            'images': [
              for (final n in ['one', 'two', 'three']) {'large': 'https://img.test/$n.jpg', 'card': 'https://img.test/$n-card.jpg'},
            ],
          },
    });
    final h = Harness(api: api);
    await h.pump(tester, location: '/p/madu-tualang');

    final gallery = find.byKey(const Key('product-gallery'));
    expect(tester.widget<PageView>(gallery).childrenDelegate.estimatedChildCount, 3);
    expect(find.byKey(const Key('product-gallery-dot-0-on')), findsOneWidget);
    expect(find.byKey(const Key('product-gallery-dot-1')), findsOneWidget);
    expect(find.byKey(const Key('product-gallery-dot-2')), findsOneWidget);
    bool showing(String url) => find.byWidgetPredicate((w) => w is ProductImage && w.url == url).hitTestable().evaluate().isNotEmpty;
    expect(showing('https://img.test/one-card.jpg'), isTrue);

    await tester.drag(gallery, const Offset(-400, 0));
    await tester.pumpAndSettle();
    expect(showing('https://img.test/two-card.jpg'), isTrue);
    expect(find.byKey(const Key('product-gallery-dot-1-on')), findsOneWidget);

    await tester.tap(find.byKey(const Key('product-gallery-1')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('image-viewer')), findsOneWidget);
    expect(showing('https://img.test/two.jpg'), isTrue, reason: 'the zoom view uses the large image');
  });
}

/// Colour (Red 101, Blue 102, Green 103) × Size (S 201, M 202, L 203).
/// Variant id = colour id * 10 + size digit (Blue / M = 1022). Green / L has no stock.
FakeApi pickerApi({void Function(Object? body)? onAdd}) {
  const colours = {101: 'Red', 102: 'Blue', 103: 'Green'};
  const sizes = {201: 'S', 202: 'M', 203: 'L'};
  final variants = [
    for (final c in colours.entries)
      for (final z in sizes.entries)
        {
          'id': c.key * 10 + z.key - 200,
          'sku': 'BAJU-${c.value}-${z.value}',
          'options_label': '${c.value} / ${z.value}',
          'price_sen': c.key == 102 && z.key == 202 ? 7740 : (c.key == 101 && z.key == 201 ? 5000 : 9000),
          'regular_price_sen': c.key == 102 && z.key == 202 ? 9000 : null,
          'option_value_ids': [c.key, z.key],
          'stock': c.key == 103 && z.key == 203 ? 0 : (c.key == 102 && z.key == 202 ? 12 : 5),
        },
  ];
  return FakeApi(routes: {
    'GET me': (_, _) => fakeUser(),
    'GET products': (_, _) => [],
    'GET categories': (_, _) => [],
    'GET cart': (_, _) => {'groups': [], 'item_count': 0, 'selected_count': 0, 'selected_subtotal_sen': 0, 'purchasing_enabled': true},
    'POST cart/items': (_, body) {
      onAdd?.call(body);
      return {'groups': [], 'item_count': 2, 'selected_count': 2, 'selected_subtotal_sen': 15480, 'purchasing_enabled': true};
    },
    'GET products/baju': (_, _) => {
          ...fakeProduct(slug: 'baju', name: 'Baju Kurung'),
          'min_price_sen': 5000,
          'variants': variants,
          'options': [
            {'id': 1, 'name': 'Colour', 'values': [for (final c in colours.entries) {'id': c.key, 'value': c.value}]},
            {'id': 2, 'name': 'Size', 'values': [for (final z in sizes.entries) {'id': z.key, 'value': z.value}]},
          ],
        },
  });
}
