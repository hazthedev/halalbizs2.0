import 'dart:convert';

import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/core/api/api_client.dart';
import 'package:halalbizs/core/db/app_db.dart';
import 'package:halalbizs/core/offline/offline.dart';

import '../helpers.dart';

void main() {
  testWidgets('offline home renders the snapshot with the offline and cached strips', (tester) async {
    usePhone(tester);
    final db = AppDb.test(NativeDatabase.memory());
    await db.putResponse(
      'home',
      jsonEncode({
        'featured': [fakeProduct(name: 'Madu Tualang Asli (cached)')],
        'categories': [],
        'flash_sale': null,
        'banners': [],
      }),
      null,
    );
    final api = FakeApi(routes: {'GET home': (_, _) => ApiException.offline()});
    final h = Harness(api: api, db: db, offline: true);
    await h.pump(tester);
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('souk-offline-strip')), findsOneWidget);
    expect(find.byKey(const Key('souk-cached-strip')), findsOneWidget);
    expect(find.textContaining('Showing cached data'), findsOneWidget);
    expect(find.text('Madu Tualang Asli (cached)'), findsOneWidget);
  });

  test('OfflineRepo falls back to the snapshot only when the failure is offline', () async {
    final db = AppDb.test(NativeDatabase.memory());
    addTearDown(db.close);
    final api = FakeApi(routes: {
      'GET products': (_, _) => ApiException.offline(),
      'GET products/x': (_, _) => ApiException('not_found', 'Not found.', status: 404),
    });
    final repo = OfflineRepo(api, db);
    await db.putResponse('products', jsonEncode([fakeProduct()]), null);

    final r = await repo.getJson('products');
    expect(r.fromCache, isTrue);
    expect(r.fetchedAt, isNotNull);
    expect((r.data as List).length, 1);

    await expectLater(repo.getJson('products/x'), throwsA(isA<ApiException>()));
  });
}
