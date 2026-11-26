import 'dart:async';
import 'dart:convert';

import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/core/db/app_db.dart';

import '../helpers.dart';

/// HB-BUY2: the buy flag follows the saved config, then the fresh one (no
/// 3 s fail-open); each product button spins alone.
const _cart = {'groups': [], 'item_count': 1, 'selected_count': 1, 'selected_subtotal_sen': 1000, 'purchasing_enabled': true};

class _SlowApi extends FakeApi {
  _SlowApi({this.config, this.postDone}) : super(routes: {
        'GET me': (_, _) => fakeUser(),
        'GET products': (_, _) => [],
        'GET categories': (_, _) => [],
        'GET products/madu-tualang': (_, _) => fakeProduct(),
        'GET cart': (_, _) => _cart,
      });

  final Future<Map<String, dynamic>>? config;
  final Future<dynamic>? postDone;

  @override
  Future<dynamic> get(String path, {Map<String, dynamic>? query, void Function(Map<String, dynamic>)? meta}) async =>
      path == 'config' && config != null ? config : super.get(path, query: query, meta: meta);

  @override
  Future<dynamic> post(String path, {Object? body, Map<String, dynamic>? query, Map<String, String>? headers, void Function(Map<String, dynamic>)? meta}) async =>
      path == 'cart/items' ? postDone : super.post(path, body: body, query: query, headers: headers, meta: meta);
}

void main() {
  testWidgets('config answers false after 4 s: buy buttons vanish when it lands', (tester) async {
    usePhone(tester);
    final config = Completer<Map<String, dynamic>>();
    await Harness(api: _SlowApi(config: config.future), token: 'tok').pump(tester, location: '/p/madu-tualang');
    expect(find.byKey(const Key('product-buy-now')), findsOneWidget, reason: 'unknown = on');
    await tester.pump(const Duration(seconds: 4));
    config.complete({'purchasing_enabled': false});
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('product-add-to-cart')), findsNothing);
    expect(find.byKey(const Key('product-buy-now')), findsNothing);
  });

  testWidgets('saved config false, fresh config never lands: buttons stay hidden', (tester) async {
    usePhone(tester);
    final db = AppDb.test(NativeDatabase.memory());
    await db.putResponse('config', jsonEncode({'purchasing_enabled': false}), null);
    await Harness(api: _SlowApi(config: Completer<Map<String, dynamic>>().future), db: db, token: 'tok').pump(tester, location: '/p/madu-tualang');
    await tester.pump(const Duration(seconds: 5));
    expect(find.byKey(const Key('product-add-to-cart')), findsNothing);
    expect(find.byKey(const Key('product-buy-now')), findsNothing);
  });

  for (final buyNow in [true, false]) {
    testWidgets('${buyNow ? 'Buy now' : 'Add to cart'} spins alone; both are disabled meanwhile', (tester) async {
      usePhone(tester);
      final post = Completer<dynamic>();
      await Harness(api: _SlowApi(postDone: post.future), token: 'tok').pump(tester, location: '/p/madu-tualang');
      final (mine, other) = buyNow ? ('product-buy-now', 'product-add-to-cart') : ('product-add-to-cart', 'product-buy-now');
      final spin = find.descendant(of: find.byKey(Key(mine)), matching: find.byType(CircularProgressIndicator));
      final otherSpin = find.descendant(of: find.byKey(Key(other)), matching: find.byType(CircularProgressIndicator));
      await tester.tap(find.byKey(Key(mine)));
      await tester.pump();
      expect(spin, findsOneWidget);
      expect(otherSpin, findsNothing);
      await tester.tap(find.byKey(Key(other)), warnIfMissed: false);
      await tester.pump();
      expect(otherSpin, findsNothing);
      post.complete(_cart);
      await tester.pumpAndSettle();
    });
  }
}
