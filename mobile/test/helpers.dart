import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:halalbizs/core/api/api_client.dart';
import 'package:halalbizs/core/auth/token_store.dart';
import 'package:halalbizs/core/db/app_db.dart';
import 'package:halalbizs/core/offline/offline.dart';
import 'package:halalbizs/core/router.dart';
import 'package:halalbizs/features/cart/cart_api.dart';
import 'package:halalbizs/main.dart';

export 'package:halalbizs/features/cart/cart_api.dart' show cartProvider;

/// The cart controller, for tests that swap the fake's answer and refresh.
final cartProviderForTest = cartProvider;

/// `verified.api` 403 envelope.
ApiException unverifiedError() => ApiException('email_unverified', 'Verify your email address to continue.', status: 403);

typedef FakeHandler = dynamic Function(Map<String, dynamic>? query, Object? body);

/// In-memory [ApiClient]: `routes['GET products']` answers the call. Unknown
/// routes throw `not_found`. Every call is recorded in [calls].
class FakeApi extends ApiClient {
  FakeApi({Map<String, FakeHandler>? routes, this.meta})
      : routes = routes ?? {},
        super(MemoryTokenStore());

  final Map<String, FakeHandler> routes;
  final Map<String, dynamic>? meta;
  final List<String> calls = [];

  dynamic _call(String method, String path, Map<String, dynamic>? query, Object? body,
      void Function(Map<String, dynamic>)? metaCb) {
    calls.add('$method $path${query == null || query.isEmpty ? '' : '?${OfflineRepo.cacheKey('', query).substring(1)}'}');
    final h = routes['$method $path'];
    // Mirrors Laravel's missing-route answer (what CartApi's stub keys on).
    if (h == null) throw ApiException('not_found', 'The route api/v1/$path could not be found.', status: 404);
    final out = h(query, body);
    if (out is ApiException) throw out;
    if (metaCb != null && meta != null) metaCb(meta!);
    return out;
  }

  @override
  Future<dynamic> get(String path, {Map<String, dynamic>? query, void Function(Map<String, dynamic>)? meta}) async =>
      _call('GET', path, query, null, meta);

  /// Headers of the most recent `post` (null when none were sent).
  Map<String, String>? lastHeaders;

  @override
  Future<dynamic> post(String path,
      {Object? body,
      Map<String, dynamic>? query,
      Map<String, String>? headers,
      void Function(Map<String, dynamic>)? meta}) async {
    lastHeaders = headers;
    return _call('POST', path, query, body, meta);
  }

  @override
  Future<dynamic> patch(String path, {Object? body}) async => _call('PATCH', path, null, body, null);

  @override
  Future<dynamic> put(String path, {Object? body}) async => _call('PUT', path, null, body, null);

  @override
  Future<dynamic> delete(String path, {Object? body}) async => _call('DELETE', path, null, body, null);
}

Map<String, dynamic> fakeProduct({
  int id = 1,
  String slug = 'madu-tualang',
  String name = 'Madu Tualang Asli',
  int priceSen = 1250,
  int stock = 12,
  int? salePriceSen,
}) =>
    {
      'id': id,
      'slug': slug,
      'name': name,
      'description': 'Raw wild honey.',
      'min_price_sen': salePriceSen ?? priceSen,
      'sold_count': 128,
      'rating_avg': 4.8,
      'image': null,
      'store': {'name': 'Kedai Madu', 'slug': 'kedai-madu'},
      'variants': [
        {
          'id': id * 10,
          'sku': 'SKU-$id',
          'options_label': '500g',
          'price_sen': priceSen,
          'sale_price_sen': ?salePriceSen,
          'stock': stock,
        },
      ],
    };

Map<String, dynamic> fakeUser({bool verified = true, String? storeStatus}) => {
      'id': 7,
      'name': 'Buyer One',
      'email': 'buyer@halalbizs.test',
      'email_verified_at': verified ? '2026-01-01T00:00:00Z' : null,
      'roles': ['buyer', if (storeStatus != null) 'seller'],
      'store': ?(storeStatus == null ? null : {'id': 3, 'slug': 'kedai-madu', 'status': storeStatus}),
    };

/// Pumps the whole app with fakes. Returns the container so tests can read
/// providers and drive the router: `h.router.go('/cart')`.
class Harness {
  Harness({FakeApi? api, this.token, this.offline = false, AppDb? db})
      : api = api ?? FakeApi(),
        db = db ?? AppDb.test(NativeDatabase.memory());

  final FakeApi api;
  final String? token;
  final bool offline;
  final AppDb db;
  late final ProviderContainer container;

  GoRouter get router => container.read(routerProvider);

  Future<void> pump(WidgetTester tester, {String? location}) async {
    container = ProviderContainer(retry: soukRetry, overrides: [
      tokenStoreProvider.overrideWith((ref) => MemoryTokenStore(token)),
      apiClientProvider.overrideWith((ref) => api),
      appDbProvider.overrideWith((ref) => db),
      isOfflineProvider.overrideWith((ref) => Stream.value(offline)),
    ]);
    addTearDown(container.dispose);
    addTearDown(db.close);
    await tester.pumpWidget(UncontrolledProviderScope(container: container, child: const HalalBizsApp()));
    // Session restore (microtask) + the splash cross-fade.
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 300));
    if (location != null) {
      router.go(location);
      await tester.pumpAndSettle();
    }
  }
}

/// Phone viewport (iPhone 14: 390×844 @3x).
void usePhone(WidgetTester tester) {
  tester.view.physicalSize = const Size(390 * 3, 844 * 3);
  tester.view.devicePixelRatio = 3;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
}
