import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../auth/token_store.dart';
import '../locale.dart';

/// Base URL, overridable per build:
/// `flutter build apk --dart-define=API_BASE_URL=https://halalbizs.com/api/v1`.
/// The local Herd host is a debug default only — see [assertApiBaseUrl].
const kApiBaseUrl =
    String.fromEnvironment('API_BASE_URL', defaultValue: 'http://halalbizs2.0-mobile.test/api/v1');

/// Release builds must name their API: fail at startup, loudly, rather than
/// ship a build that talks to a developer's machine.
void assertApiBaseUrl() {
  if (!kReleaseMode) return;
  if (!const bool.hasEnvironment('API_BASE_URL')) {
    throw StateError('API_BASE_URL is not set. Build with --dart-define=API_BASE_URL=https://<host>/api/v1');
  }
  // Bearer tokens and payment hand-offs never travel in clear: https only.
  if (!kApiBaseUrl.startsWith('https://')) {
    throw StateError('API_BASE_URL must start with https:// in a release build (got $kApiBaseUrl).');
  }
}

/// Error the whole app throws for API failures. Mirrors the server envelope
/// `{"ok":false,"error":{code,message,fields}}` (mobile plan §1).
///
/// [message] is the server's text (shown as sent) or, for client-side codes
/// (`offline`, `timeout`, `bad_envelope`, `no_challenge`, `token_storage`),
/// a developer note — the UI translates those by [code] (`apiMessage`).
class ApiException implements Exception {
  ApiException(this.code, this.message, {this.status, this.fields = const {}});

  final String code;
  final String message;
  final int? status;

  /// Field name → list of messages (422 `validation_failed`).
  final Map<String, List<String>> fields;

  bool get isAuth => status == 401;
  bool get isForbidden => status == 403;
  bool get isNotFound => status == 404 || code == 'not_found';
  bool get isValidation => code == 'validation_failed' || status == 422;
  bool get isOffline => code == 'offline';

  static ApiException offline() => ApiException('offline', 'No connection.');

  /// The server was reached but did not answer in time (send/receive timeout).
  static ApiException timeout() => ApiException('timeout', 'The server took too long to respond.');

  @override
  String toString() => 'ApiException($status $code): $message';
}

/// Called by the auth interceptor on any 401 so the session can be torn down
/// (wired up by the session controller at startup).
typedef UnauthenticatedHandler = Future<void> Function();

class ApiClient {
  ApiClient(this._tokenStore, {Dio? dio, this.locale, this.displayCurrency})
      : dio = dio ??
            Dio(BaseOptions(
              // Dio concatenates baseUrl + path verbatim: the slash is required.
              baseUrl: kApiBaseUrl.endsWith('/') ? kApiBaseUrl : '$kApiBaseUrl/',
              connectTimeout: const Duration(seconds: 15),
              receiveTimeout: const Duration(seconds: 60),
              headers: {'Accept': 'application/json'},
            )) {
    this.dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await _tokenStore.read();
        if (token != null) options.headers['Authorization'] = 'Bearer $token';
        options.headers['Accept-Language'] = locale?.call() ?? 'en';
        options.headers['X-Display-Currency'] = displayCurrency?.call() ?? 'MYR';
        handler.next(options);
      },
      onError: (e, handler) async {
        if (e.response?.statusCode == 401 && onUnauthenticated != null) {
          await onUnauthenticated!.call();
        }
        handler.next(e);
      },
    ));
  }

  final Dio dio;
  final TokenStore _tokenStore;

  /// Current UI locale code (`en` / `ms`) and display currency, read per request.
  final String Function()? locale;
  final String Function()? displayCurrency;
  UnauthenticatedHandler? onUnauthenticated;

  /// GET returning the envelope's `data`. [meta] (if given) receives the
  /// envelope's `meta` map (pagination etc.).
  Future<dynamic> get(String path,
      {Map<String, dynamic>? query, void Function(Map<String, dynamic>)? meta}) {
    return _unwrap(() => dio.get(path, queryParameters: query), meta);
  }

  Future<dynamic> post(String path,
          {Object? body,
          Map<String, dynamic>? query,
          Map<String, String>? headers,
          void Function(Map<String, dynamic>)? meta}) =>
      _unwrap(
          () => dio.post(path,
              data: body, queryParameters: query, options: headers == null ? null : Options(headers: headers)),
          meta);

  Future<dynamic> patch(String path, {Object? body}) => _unwrap(() => dio.patch(path, data: body), null);

  Future<dynamic> put(String path, {Object? body}) => _unwrap(() => dio.put(path, data: body), null);

  Future<dynamic> delete(String path, {Object? body}) => _unwrap(() => dio.delete(path, data: body), null);

  /// Tolerant of the pre-slice-1 Laravel paginator shape (`{data, links,
  /// meta}` without `ok`): any 2xx whose body has no `ok` key is a success.
  /// A 2xx carrying `ok:false` is parsed like any other envelope error.
  Future<dynamic> _unwrap(
      Future<Response<dynamic>> Function() run, void Function(Map<String, dynamic>)? meta) async {
    try {
      final res = await run();
      final body = res.data;
      if (body is Map<String, dynamic>) {
        if (body['ok'] == false) throw _parseError(res.statusCode, body);
        if (meta != null && body['meta'] is Map<String, dynamic>) {
          meta(body['meta'] as Map<String, dynamic>);
        }
        return body.containsKey('data') ? body['data'] : body;
      }
      if (body == null || body == '') return null;
      throw ApiException('bad_envelope', 'Unexpected server response.', status: res.statusCode);
    } on DioException catch (e) {
      throw _toApiException(e);
    }
  }

  ApiException _toApiException(DioException e) => switch (e.type) {
        DioExceptionType.connectionError || DioExceptionType.connectionTimeout => ApiException.offline(),
        DioExceptionType.sendTimeout || DioExceptionType.receiveTimeout => ApiException.timeout(),
        _ => _parseError(e.response?.statusCode, e.response?.data),
      };

  /// Envelope error, or Laravel's default `{message, errors}` for routes that
  /// predate the envelope. No JSON body → empty message (the UI shows its
  /// generic text, never Dio's).
  static ApiException _parseError(int? status, dynamic body) {
    if (body is! Map<String, dynamic>) return ApiException('http_$status', '', status: status);
    final err = body['error'] is Map<String, dynamic> ? body['error'] as Map<String, dynamic> : body;
    final fields = <String, List<String>>{};
    final rawFields = err['fields'] ?? err['errors'];
    if (rawFields is Map) {
      rawFields.forEach((k, v) {
        fields['$k'] = (v is List) ? v.map((m) => '$m').toList() : ['$v'];
      });
    }
    final code = '${err['code'] ?? (status == 404 ? 'not_found' : status == 422 ? 'validation_failed' : 'http_$status')}';
    return ApiException(code, '${err['message'] ?? ''}', status: status, fields: fields);
  }
}

final tokenStoreProvider = Provider<TokenStore>((ref) => TokenStore());

/// Riverpod 3 retries failed providers with backoff. A server answer (404,
/// 422, offline) is final: the user has Retry / pull-to-refresh instead.
Duration? soukRetry(int retryCount, Object error) =>
    error is ApiException ? null : ProviderContainer.defaultRetry(retryCount, error);

final apiClientProvider = Provider<ApiClient>((ref) => ApiClient(
      ref.watch(tokenStoreProvider),
      locale: () => ref.read(localeProvider).languageCode,
      displayCurrency: () => ref.read(displayCurrencyProvider),
    ));
