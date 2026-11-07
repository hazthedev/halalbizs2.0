import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/core/api/api_client.dart';

import '../helpers.dart';
import 'seller_helpers.dart';

void main() {
  testWidgets('new product form converts "12.50" to 1250 sen and rejects "12.345"', (tester) async {
    usePhone(tester);
    Object? sent;
    final api = FakeApi(routes: {
      ...sellerBaseRoutes(),
      'POST seller/products': (_, body) {
        sent = body;
        return fakeSellerProduct(id: 9);
      },
      'GET seller/products/9': (_, _) => fakeSellerProduct(id: 9),
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/seller/products/new');

    await tester.enterText(find.byKey(const Key('seller-product-name-en')), 'Madu Tualang Asli');
    await tester.ensureVisible(find.byKey(const Key('seller-product-category')));
    await tester.pumpAndSettle();
    await tester.drag(find.byKey(const Key('souk-page-scroll')), const Offset(0, -300)); // clear the pinned bar
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('seller-product-category')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('seller-category-2')));
    await tester.pumpAndSettle();
    await tester.enterText(find.byKey(const Key('seller-product-price')), '12.345');
    await tester.enterText(find.byKey(const Key('seller-product-stock')), '12');
    await tester.tap(find.byKey(const Key('seller-product-save')));
    await tester.pumpAndSettle();
    expect(find.text('Enter an amount like 12.50.'), findsOneWidget);
    expect(sent, isNull);

    await tester.enterText(find.byKey(const Key('seller-product-price')), '12.50');
    await tester.tap(find.byKey(const Key('seller-product-save')));
    await tester.pumpAndSettle();
    final body = sent! as Map;
    expect(body['price_sen'], 1250);
    expect(body['stock'], 12);
    expect(body['category_id'], 2);
    expect((body['name'] as Map)['en'], 'Madu Tualang Asli');
    expect(h.router.state.uri.path, '/seller/products/9');
  });

  testWidgets('"12" becomes 1200 sen on an edit and a 422 on name.en lands on the English name field', (tester) async {
    usePhone(tester);
    Object? sent;
    final api = FakeApi(routes: {
      ...sellerBaseRoutes(),
      'GET seller/products/7': (_, _) => fakeSellerProduct(),
      'PATCH seller/products/7': (_, body) {
        sent = body;
        return validation({'name.en': ['The product name (English) field is required.']});
      },
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/seller/products/7');

    expect(find.byKey(const Key('seller-product-status')), findsOneWidget);
    await tester.enterText(find.byKey(const Key('seller-product-price')), '12');
    await tester.tap(find.byKey(const Key('seller-product-save')));
    await tester.pumpAndSettle();
    expect((sent! as Map)['price_sen'], 1200);
    final error = find.text('The product name (English) field is required.');
    expect(error, findsOneWidget);
    final nameField = tester.getRect(find.byKey(const Key('seller-product-name-en')));
    final errorRect = tester.getRect(error);
    expect(errorRect.top, greaterThan(nameField.bottom));
    expect(errorRect.top - nameField.bottom, lessThan(24));
  });

  testWidgets('publish 409 halal_blocked shows the banner with the reason', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      ...sellerBaseRoutes(),
      'GET seller/products/7': (_, _) => fakeSellerProduct(),
      'PATCH seller/products/7': (_, _) => fakeSellerProduct(),
      'POST seller/products/7/publish': (_, _) =>
          ApiException('halal_blocked', 'This category needs a halal certificate on file.', status: 409),
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/seller/products/7');
    expect(find.byKey(const Key('seller-product-publish')), findsOneWidget);
    await tester.tap(find.byKey(const Key('seller-product-publish')));
    await tester.pumpAndSettle();
    expect(api.calls, contains('POST seller/products/7/publish'));
    expect(find.byKey(const Key('seller-product-halal-blocked')), findsOneWidget);
    expect(find.textContaining('This category needs a halal certificate on file.'), findsOneWidget);
  });

  testWidgets('products list filters by status chip and searches after 300 ms', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      ...sellerBaseRoutes(),
      'GET seller/products': (q, _) => [fakeSellerProduct(status: q?['status'] ?? 'live')],
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/seller/products');
    expect(find.byKey(const Key('seller-product-7')), findsOneWidget);
    expect(find.byKey(const Key('seller-products-new')), findsOneWidget);

    await tester.tap(find.byKey(const Key('seller-products-tab-draft')));
    await tester.pumpAndSettle();
    expect(api.calls, contains('GET seller/products?status=draft'));

    await tester.enterText(find.byKey(const Key('seller-products-search')), 'madu');
    await tester.pump(const Duration(milliseconds: 100));
    expect(api.calls.where((c) => c.contains('q=madu')), isEmpty);
    await tester.pump(const Duration(milliseconds: 250));
    await tester.pumpAndSettle();
    expect(api.calls, contains('GET seller/products?q=madu&status=draft'));
  });

  testWidgets('delist asks for confirmation before POST and refreshes the row status', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      ...sellerBaseRoutes(),
      'GET seller/products/7': (_, _) => fakeSellerProduct(status: 'live'),
      'POST seller/products/7/delist': (_, _) => fakeSellerProduct(status: 'delisted'),
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/seller/products/7');
    await tester.tap(find.byKey(const Key('seller-product-delist')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('souk-confirm-ok')), findsOneWidget);
    await tester.tap(find.byKey(const Key('souk-confirm-cancel')));
    await tester.pumpAndSettle();
    expect(api.calls, isNot(contains('POST seller/products/7/delist')));

    await tester.tap(find.byKey(const Key('seller-product-delist')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('souk-confirm-ok')));
    await tester.pumpAndSettle();
    expect(api.calls, contains('POST seller/products/7/delist'));
    expect(find.byKey(const Key('seller-product-relist')), findsOneWidget);
  });

  testWidgets('a 422 on images.0 shows the images banner; an unknown field falls back to the snackbar', (tester) async {
    usePhone(tester);
    var attempt = 0;
    final api = FakeApi(routes: {
      ...sellerBaseRoutes(),
      'GET seller/products/7': (_, _) => fakeSellerProduct(),
      'PATCH seller/products/7': (_, _) => fakeSellerProduct(),
      'POST seller/products/7/publish': (_, _) => ++attempt == 1
          ? validation({'images.0': ['The image must not be greater than 4096 kilobytes.']})
          : validation({'condition': ['The selected condition is invalid.']}),
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/seller/products/7');
    await tester.tap(find.byKey(const Key('seller-product-publish')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('seller-product-images-error')), findsOneWidget);
    expect(find.text('The image must not be greater than 4096 kilobytes.'), findsOneWidget);

    await tester.pump(const Duration(seconds: 5)); // let the "Saved" snackbar leave the bottom bar
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('seller-product-publish')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('seller-product-images-error')), findsNothing);
    expect(find.byType(SnackBar), findsOneWidget);
    expect(find.text('The selected condition is invalid.'), findsOneWidget);
  });
}
