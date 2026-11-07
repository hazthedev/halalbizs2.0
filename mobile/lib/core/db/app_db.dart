import 'package:drift/drift.dart';
import 'package:drift_flutter/drift_flutter.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

part 'app_db.g.dart';

/// Snapshot cache for GET endpoints (home, product, category, store…) so
/// every catalogue screen can render something offline. Keyed by
/// endpoint + normalised query. `data` is the envelope's `data` JSON; `meta`
/// the envelope's `meta` JSON (pagination), if any.
class ResponseCache extends Table {
  TextColumn get cacheKey => text()();
  TextColumn get data => text()();
  TextColumn get meta => text().nullable()();
  DateTimeColumn get fetchedAt => dateTime()();

  @override
  Set<Column> get primaryKey => {cacheKey};
}

/// Small key-value store: cached profile, UI locale, preferences.
class Kv extends Table {
  TextColumn get k => text()();
  TextColumn get v => text()();

  @override
  Set<Column> get primaryKey => {k};
}

@DriftDatabase(tables: [ResponseCache, Kv])
class AppDb extends _$AppDb {
  /// Web needs the worker + wasm shipped in `web/` (drift docs).
  AppDb()
      : super(driftDatabase(
          name: 'halalbizs',
          web: DriftWebOptions(
            sqlite3Wasm: Uri.parse('sqlite3.wasm'),
            driftWorker: Uri.parse('drift_worker.js'),
          ),
        ));

  /// In-memory database for widget/unit tests: `AppDb.test(NativeDatabase.memory())`.
  AppDb.test(super.e);

  @override
  int get schemaVersion => 1;

  // ---- KV ------------------------------------------------------------------
  Future<String?> kvGet(String key) async {
    final row = await (select(kv)..where((t) => t.k.equals(key))).getSingleOrNull();
    return row?.v;
  }

  Future<void> kvPut(String key, String value) =>
      into(kv).insertOnConflictUpdate(KvCompanion.insert(k: key, v: value));

  Future<void> kvDelete(String key) => (delete(kv)..where((t) => t.k.equals(key))).go();

  // ---- Response cache ------------------------------------------------------
  Future<void> putResponse(String key, String data, String? meta) =>
      into(responseCache).insertOnConflictUpdate(ResponseCacheCompanion.insert(
          cacheKey: key, data: data, meta: Value(meta), fetchedAt: DateTime.now()));

  Future<ResponseCacheData?> getResponse(String key) =>
      (select(responseCache)..where((t) => t.cacheKey.equals(key))).getSingleOrNull();

  /// Wipe everything (login of a different account).
  Future<void> wipe() async {
    await delete(responseCache).go();
    await delete(kv).go();
  }

  /// Wipe cached data on sign-out / session revocation, keeping the cached
  /// profile (account-switch detection) and UI preferences (`ui.*`).
  Future<void> wipeCaches() async {
    await delete(responseCache).go();
    await (delete(kv)..where((t) => t.k.equals('session.profile').not() & t.k.like('ui.%').not())).go();
  }
}

final appDbProvider = Provider<AppDb>((ref) => AppDb());
