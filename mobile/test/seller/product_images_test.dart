import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/features/seller_shared/seller_kit.dart';
import 'package:image_picker/image_picker.dart';

import '../helpers.dart';
import 'seller_helpers.dart';

/// Hands back [files] from the gallery and records the limit asked for.
class _FakePicker extends ImagePicker {
  _FakePicker(this.files);

  final List<XFile> files;
  int? limit;

  @override
  Future<List<XFile>> pickMultiImage({double? maxWidth, double? maxHeight, int? imageQuality, int? limit, bool requestFullMetadata = true}) async {
    this.limit = limit;
    return files;
  }
}

XFile _photo(int i) => XFile.fromData(Uint8List.fromList([1, 2, 3]), name: 'p$i.jpg', mimeType: 'image/jpeg');

Map<String, dynamic> _productWith(int images) => {
      ...fakeSellerProduct(),
      'images': [for (var i = 0; i < images; i++) {'id': 100 + i, 'url': 'http://x/$i.jpg', 'thumb': 'http://x/$i.jpg'}],
    };

void main() {
  Future<(FakeApi, List<FormData>)> open(WidgetTester tester, {required int existing, required int picked}) async {
    usePhone(tester);
    final uploads = <FormData>[];
    var images = existing;
    final api = FakeApi(routes: {
      ...sellerBaseRoutes(),
      'GET seller/products/7': (_, _) => _productWith(images),
      'POST seller/products/7/images': (_, body) {
        uploads.add(body! as FormData);
        images += (body as FormData).files.length;
        return _productWith(images);
      },
    });
    final picker = _FakePicker([for (var i = 0; i < picked; i++) _photo(i)]);
    sellerImagePicker = picker;
    addTearDown(() => sellerImagePicker = ImagePicker());
    await Harness(api: api, token: 'tok').pump(tester, location: '/seller/products/7');
    await tester.ensureVisible(find.byKey(const Key('seller-product-add-image')));
    await tester.pumpAndSettle();
    await tester.drag(find.byKey(const Key('souk-page-scroll')), const Offset(0, -300)); // clear the pinned bar
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('seller-product-add-image')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('seller-pick-gallery')));
    await tester.pumpAndSettle();
    return (api, uploads);
  }

  testWidgets('picking 3 photos uploads all 3 in one request and the count reads 4 / 9', (tester) async {
    final (_, uploads) = await open(tester, existing: 1, picked: 3);
    expect((sellerImagePicker as _FakePicker).limit, 8);
    expect(uploads, hasLength(1));
    expect(uploads.single.files.map((f) => f.key), ['images[]', 'images[]', 'images[]']);
    expect(find.text('4 / 9'), findsOneWidget);
  });

  testWidgets('picking more than the slots left is refused with the message', (tester) async {
    final (api, uploads) = await open(tester, existing: 7, picked: 3);
    expect((sellerImagePicker as _FakePicker).limit, 2);
    expect(uploads, isEmpty);
    expect(api.calls, isNot(contains('POST seller/products/7/images')));
    expect(find.text('Only 2 more photos fit. A product has at most 9.'), findsOneWidget);
    expect(find.text('7 / 9'), findsOneWidget);
  });

  testWidgets('at 9 photos the add tile is gone and the limit is explained', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {...sellerBaseRoutes(), 'GET seller/products/7': (_, _) => _productWith(9)});
    await Harness(api: api, token: 'tok').pump(tester, location: '/seller/products/7');
    expect(find.byKey(const Key('seller-product-add-image')), findsNothing);
    expect(find.text('9 / 9'), findsOneWidget);
    expect(find.text('9 photos is the limit. Remove one to add another.'), findsOneWidget);
  });
}
