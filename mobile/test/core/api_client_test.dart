import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:drift/native.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/core/api/api_client.dart';
import 'package:halalbizs/core/auth/session.dart';
import 'package:halalbizs/core/auth/token_store.dart';
import 'package:halalbizs/core/db/app_db.dart';

/// Test-only transport: the real [ApiClient] (interceptors, unwrap, error
/// parsing) runs against canned JSON. Throw a [DioException] from [handler]
/// to simulate transport failures.
class StubAdapter implements HttpClientAdapter {
  StubAdapter(this.handler);

  final (int, Object?) Function(RequestOptions o) handler;
  final List<RequestOptions> requests = [];

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    requests.add(options);
    final (status, body) = handler(options);
    return ResponseBody.fromString(body == null ? '' : jsonEncode(body), status,
        headers: {Headers.contentTypeHeader: [Headers.jsonContentType]});
  }

  @override
  void close({bool force = false}) {}
}

ApiClient realClient(StubAdapter adapter, {String? token, String locale = 'en'}) {
  final c = ApiClient(MemoryTokenStore(token), locale: () => locale);
  c.dio.httpClientAdapter = adapter;
  return c;
}

void main() {
  test('relative paths resolve under /api/v1/ (Dio does not insert the slash)', () {
    final dio = ApiClient(MemoryTokenStore()).dio;
    expect(dio.options.baseUrl, endsWith('/api/v1/'));
    expect(Uri.parse(dio.options.baseUrl).resolve('products').toString(), '$kApiBaseUrl/products');
  });

  test('envelope and legacy paginator both unwrap to data + meta', () async {
    final adapter = StubAdapter((o) => o.path.endsWith('products')
        ? (200, {'ok': true, 'data': [1, 2], 'meta': {'total': 2}})
        : (200, {'data': [3], 'links': {}, 'meta': {'current_page': 1}}));
    final api = realClient(adapter);
    Map<String, dynamic>? meta;
    expect(await api.get('products', meta: (m) => meta = m), [1, 2]);
    expect(meta?['total'], 2);
    expect(await api.get('categories', meta: (m) => meta = m), [3]);
    expect(meta?['current_page'], 1);
  });

  test('422 maps fields from the envelope and from Laravel {message, errors}', () async {
    final adapter = StubAdapter((o) => o.path.endsWith('envelope')
        ? (422, {'ok': false, 'error': {'code': 'validation_failed', 'message': 'Invalid.', 'fields': {'email': ['Taken.']}}})
        : (422, {'message': 'The given data was invalid.', 'errors': {'email': ['Taken.', 'Bad.']}}));
    final api = realClient(adapter);
    for (final path in ['envelope', 'legacy']) {
      final e = await api.post(path).then<ApiException?>((_) => null, onError: (Object e) => e as ApiException);
      expect(e!.isValidation, isTrue, reason: path);
      expect(e.code, 'validation_failed');
      expect(e.fields['email']?.first, 'Taken.');
      expect(e.message, isNotEmpty);
    }
  });

  test('2xx with ok:false, bare 500 and transport failures become typed errors', () async {
    final adapter = StubAdapter((o) => switch (o.path) {
          'closed' => (200, {'ok': false, 'error': {'code': 'cart_closed', 'message': 'Closed.'}}),
          'boom' => (500, null),
          'slow' => throw DioException(requestOptions: o, type: DioExceptionType.receiveTimeout),
          _ => throw DioException(requestOptions: o, type: DioExceptionType.connectionError),
        });
    final api = realClient(adapter);
    Future<ApiException> fail(String p) => api.get(p).then((_) => throw StateError('no error'), onError: (Object e) => e as ApiException);
    final closed = await fail('closed');
    expect((closed.code, closed.message, closed.status), ('cart_closed', 'Closed.', 200));
    final boom = await fail('boom');
    expect((boom.code, boom.message), ('http_500', ''));
    expect((await fail('slow')).code, 'timeout');
    expect((await fail('down')).isOffline, isTrue);
  });

  test('every request carries Accept, Accept-Language, X-Display-Currency and the bearer', () async {
    final adapter = StubAdapter((_) => (200, {'ok': true, 'data': null}));
    await realClient(adapter, token: 'tok', locale: 'ms').get('me');
    final h = adapter.requests.single.headers;
    expect(h['Accept'], 'application/json');
    expect(h['Accept-Language'], 'ms');
    expect(h['X-Display-Currency'], 'MYR');
    expect(h['Authorization'], 'Bearer tok');
  });

  test('post forwards per-call headers (Idempotency-Key) on top of the defaults', () async {
    final adapter = StubAdapter((_) => (201, {'ok': true, 'data': null}));
    await realClient(adapter, token: 'tok').post('checkout/place', body: {}, headers: {'Idempotency-Key': 'k-1'});
    final h = adapter.requests.single.headers;
    expect(h['Idempotency-Key'], 'k-1');
    expect(h['Authorization'], 'Bearer tok');
    expect(h['Accept'], 'application/json');
  });

  test('401 tears the session down: token cleared, caches wiped, SignedOut(sessionEnded)', () async {
    final adapter = StubAdapter((_) => (401, {'ok': false, 'error': {'code': 'unauthenticated', 'message': 'Unauthenticated.'}}));
    final tokens = MemoryTokenStore('tok');
    final db = AppDb.test(NativeDatabase.memory());
    addTearDown(db.close);
    await db.putResponse('products', '[]', null);
    await db.kvPut('session.profile', '{"id":7}');
    await db.kvPut('session.user_id', '7');
    await db.kvPut('ui.locale', 'ms');
    final api = ApiClient(tokens)..dio.httpClientAdapter = adapter;
    final container = ProviderContainer(overrides: [
      tokenStoreProvider.overrideWithValue(tokens),
      apiClientProvider.overrideWithValue(api),
      appDbProvider.overrideWithValue(db),
    ]);
    addTearDown(container.dispose);

    var s = container.read(sessionProvider);
    for (var i = 0; i < 100 && s is! SignedOut; i++) {
      await Future<void>.delayed(const Duration(milliseconds: 10));
      s = container.read(sessionProvider);
    }
    expect(s, isA<SignedOut>().having((s) => s.sessionEnded, 'sessionEnded', isTrue));
    expect(await tokens.read(), isNull);
    expect(await db.getResponse('products'), isNull);
    expect(await db.kvGet('session.profile'), isNull);
    expect(await db.kvGet('session.user_id'), '7');
    expect(await db.kvGet('ui.locale'), 'ms');
  });
}
