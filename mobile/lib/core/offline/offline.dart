import 'dart:async';
import 'dart:convert';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../db/app_db.dart';
import '../locale.dart';

/// True while the device reports no network route at all. The real source of
/// truth remains each request (a request can still fail on a "connected"
/// network) — use this only for banners and to skip doomed refreshes.
final isOfflineProvider = StreamProvider<bool>((ref) async* {
  final c = Connectivity();
  try {
    yield (await c.checkConnectivity()).every((r) => r == ConnectivityResult.none);
    await for (final results in c.onConnectivityChanged) {
      yield results.every((r) => r == ConnectivityResult.none);
    }
  } catch (_) {
    yield false; // no connectivity plugin (tests, unsupported platform)
  }
});

/// A value plus where it came from, so screens can show "cached · 5 min ago".
class CachedResult<T> {
  const CachedResult(this.data, {this.fromCache = false, this.fetchedAt, this.meta});

  final T data;
  final bool fromCache;
  final DateTime? fetchedAt;
  final Map<String, dynamic>? meta;
}

/// Network-first with transparent snapshot fallback: the standard read path
/// for every catalogue screen.
///
///   final r = await ref.read(offlineRepoProvider).getJson('products', query: {...});
///   r.fromCache  → show the cached chip with r.fetchedAt
class OfflineRepo {
  OfflineRepo(this._api, this._db);

  final ApiClient _api;
  final AppDb _db;

  static String cacheKey(String path, [Map<String, dynamic>? query]) {
    if (query == null || query.isEmpty) return path;
    final keys = query.keys.toList()..sort();
    return Uri(path: path, queryParameters: {for (final k in keys) k: '${query[k]}'}).toString();
  }

  Future<CachedResult<dynamic>> getJson(String path,
      {Map<String, dynamic>? query, bool allowStale = true}) async {
    final key = cacheKey(path, query);
    try {
      Map<String, dynamic>? meta;
      final data = await _api.get(path, query: query, meta: (m) => meta = m);
      // The screen gets the data now; the offline snapshot is written behind it.
      unawaited(_saveSnapshot(key, data, meta));
      return CachedResult(data, meta: meta);
    } on ApiException catch (e) {
      if (!e.isOffline || !allowStale) rethrow;
      final ResponseCacheData? cached;
      try {
        cached = await _db.getResponse(key);
      } catch (_) {
        rethrow;
      }
      if (cached == null) rethrow;
      return CachedResult(
        jsonDecode(cached.data),
        fromCache: true,
        fetchedAt: cached.fetchedAt,
        meta: cached.meta == null ? null : jsonDecode(cached.meta!) as Map<String, dynamic>?,
      );
    }
  }

  Future<void> _saveSnapshot(String key, dynamic data, Map<String, dynamic>? meta) async {
    try {
      await _db.putResponse(key, jsonEncode(data), meta == null ? null : jsonEncode(meta));
    } catch (_) {
      // A failed snapshot only costs offline coverage for this one endpoint.
    }
  }
}

/// Watches the UI locale so a language switch rebuilds every API wrapper and
/// screen provider on top of it: names come back in the new Accept-Language.
final offlineRepoProvider = Provider<OfflineRepo>((ref) {
  ref.watch(localeProvider);
  return OfflineRepo(ref.watch(apiClientProvider), ref.watch(appDbProvider));
});
