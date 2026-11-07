// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'app_db.dart';

// ignore_for_file: type=lint
class $ResponseCacheTable extends ResponseCache
    with TableInfo<$ResponseCacheTable, ResponseCacheData> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $ResponseCacheTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _cacheKeyMeta = const VerificationMeta(
    'cacheKey',
  );
  @override
  late final GeneratedColumn<String> cacheKey = GeneratedColumn<String>(
    'cache_key',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _dataMeta = const VerificationMeta('data');
  @override
  late final GeneratedColumn<String> data = GeneratedColumn<String>(
    'data',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _metaMeta = const VerificationMeta('meta');
  @override
  late final GeneratedColumn<String> meta = GeneratedColumn<String>(
    'meta',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _fetchedAtMeta = const VerificationMeta(
    'fetchedAt',
  );
  @override
  late final GeneratedColumn<DateTime> fetchedAt = GeneratedColumn<DateTime>(
    'fetched_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [cacheKey, data, meta, fetchedAt];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'response_cache';
  @override
  VerificationContext validateIntegrity(
    Insertable<ResponseCacheData> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('cache_key')) {
      context.handle(
        _cacheKeyMeta,
        cacheKey.isAcceptableOrUnknown(data['cache_key']!, _cacheKeyMeta),
      );
    } else if (isInserting) {
      context.missing(_cacheKeyMeta);
    }
    if (data.containsKey('data')) {
      context.handle(
        _dataMeta,
        this.data.isAcceptableOrUnknown(data['data']!, _dataMeta),
      );
    } else if (isInserting) {
      context.missing(_dataMeta);
    }
    if (data.containsKey('meta')) {
      context.handle(
        _metaMeta,
        meta.isAcceptableOrUnknown(data['meta']!, _metaMeta),
      );
    }
    if (data.containsKey('fetched_at')) {
      context.handle(
        _fetchedAtMeta,
        fetchedAt.isAcceptableOrUnknown(data['fetched_at']!, _fetchedAtMeta),
      );
    } else if (isInserting) {
      context.missing(_fetchedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {cacheKey};
  @override
  ResponseCacheData map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return ResponseCacheData(
      cacheKey: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}cache_key'],
      )!,
      data: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}data'],
      )!,
      meta: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}meta'],
      ),
      fetchedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}fetched_at'],
      )!,
    );
  }

  @override
  $ResponseCacheTable createAlias(String alias) {
    return $ResponseCacheTable(attachedDatabase, alias);
  }
}

class ResponseCacheData extends DataClass
    implements Insertable<ResponseCacheData> {
  final String cacheKey;
  final String data;
  final String? meta;
  final DateTime fetchedAt;
  const ResponseCacheData({
    required this.cacheKey,
    required this.data,
    this.meta,
    required this.fetchedAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['cache_key'] = Variable<String>(cacheKey);
    map['data'] = Variable<String>(data);
    if (!nullToAbsent || meta != null) {
      map['meta'] = Variable<String>(meta);
    }
    map['fetched_at'] = Variable<DateTime>(fetchedAt);
    return map;
  }

  ResponseCacheCompanion toCompanion(bool nullToAbsent) {
    return ResponseCacheCompanion(
      cacheKey: Value(cacheKey),
      data: Value(data),
      meta: meta == null && nullToAbsent ? const Value.absent() : Value(meta),
      fetchedAt: Value(fetchedAt),
    );
  }

  factory ResponseCacheData.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return ResponseCacheData(
      cacheKey: serializer.fromJson<String>(json['cacheKey']),
      data: serializer.fromJson<String>(json['data']),
      meta: serializer.fromJson<String?>(json['meta']),
      fetchedAt: serializer.fromJson<DateTime>(json['fetchedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'cacheKey': serializer.toJson<String>(cacheKey),
      'data': serializer.toJson<String>(data),
      'meta': serializer.toJson<String?>(meta),
      'fetchedAt': serializer.toJson<DateTime>(fetchedAt),
    };
  }

  ResponseCacheData copyWith({
    String? cacheKey,
    String? data,
    Value<String?> meta = const Value.absent(),
    DateTime? fetchedAt,
  }) => ResponseCacheData(
    cacheKey: cacheKey ?? this.cacheKey,
    data: data ?? this.data,
    meta: meta.present ? meta.value : this.meta,
    fetchedAt: fetchedAt ?? this.fetchedAt,
  );
  ResponseCacheData copyWithCompanion(ResponseCacheCompanion data) {
    return ResponseCacheData(
      cacheKey: data.cacheKey.present ? data.cacheKey.value : this.cacheKey,
      data: data.data.present ? data.data.value : this.data,
      meta: data.meta.present ? data.meta.value : this.meta,
      fetchedAt: data.fetchedAt.present ? data.fetchedAt.value : this.fetchedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('ResponseCacheData(')
          ..write('cacheKey: $cacheKey, ')
          ..write('data: $data, ')
          ..write('meta: $meta, ')
          ..write('fetchedAt: $fetchedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(cacheKey, data, meta, fetchedAt);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is ResponseCacheData &&
          other.cacheKey == this.cacheKey &&
          other.data == this.data &&
          other.meta == this.meta &&
          other.fetchedAt == this.fetchedAt);
}

class ResponseCacheCompanion extends UpdateCompanion<ResponseCacheData> {
  final Value<String> cacheKey;
  final Value<String> data;
  final Value<String?> meta;
  final Value<DateTime> fetchedAt;
  final Value<int> rowid;
  const ResponseCacheCompanion({
    this.cacheKey = const Value.absent(),
    this.data = const Value.absent(),
    this.meta = const Value.absent(),
    this.fetchedAt = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  ResponseCacheCompanion.insert({
    required String cacheKey,
    required String data,
    this.meta = const Value.absent(),
    required DateTime fetchedAt,
    this.rowid = const Value.absent(),
  }) : cacheKey = Value(cacheKey),
       data = Value(data),
       fetchedAt = Value(fetchedAt);
  static Insertable<ResponseCacheData> custom({
    Expression<String>? cacheKey,
    Expression<String>? data,
    Expression<String>? meta,
    Expression<DateTime>? fetchedAt,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (cacheKey != null) 'cache_key': cacheKey,
      if (data != null) 'data': data,
      if (meta != null) 'meta': meta,
      if (fetchedAt != null) 'fetched_at': fetchedAt,
      if (rowid != null) 'rowid': rowid,
    });
  }

  ResponseCacheCompanion copyWith({
    Value<String>? cacheKey,
    Value<String>? data,
    Value<String?>? meta,
    Value<DateTime>? fetchedAt,
    Value<int>? rowid,
  }) {
    return ResponseCacheCompanion(
      cacheKey: cacheKey ?? this.cacheKey,
      data: data ?? this.data,
      meta: meta ?? this.meta,
      fetchedAt: fetchedAt ?? this.fetchedAt,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (cacheKey.present) {
      map['cache_key'] = Variable<String>(cacheKey.value);
    }
    if (data.present) {
      map['data'] = Variable<String>(data.value);
    }
    if (meta.present) {
      map['meta'] = Variable<String>(meta.value);
    }
    if (fetchedAt.present) {
      map['fetched_at'] = Variable<DateTime>(fetchedAt.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('ResponseCacheCompanion(')
          ..write('cacheKey: $cacheKey, ')
          ..write('data: $data, ')
          ..write('meta: $meta, ')
          ..write('fetchedAt: $fetchedAt, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $KvTable extends Kv with TableInfo<$KvTable, KvData> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $KvTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _kMeta = const VerificationMeta('k');
  @override
  late final GeneratedColumn<String> k = GeneratedColumn<String>(
    'k',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _vMeta = const VerificationMeta('v');
  @override
  late final GeneratedColumn<String> v = GeneratedColumn<String>(
    'v',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [k, v];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'kv';
  @override
  VerificationContext validateIntegrity(
    Insertable<KvData> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('k')) {
      context.handle(_kMeta, k.isAcceptableOrUnknown(data['k']!, _kMeta));
    } else if (isInserting) {
      context.missing(_kMeta);
    }
    if (data.containsKey('v')) {
      context.handle(_vMeta, v.isAcceptableOrUnknown(data['v']!, _vMeta));
    } else if (isInserting) {
      context.missing(_vMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {k};
  @override
  KvData map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return KvData(
      k: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}k'],
      )!,
      v: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}v'],
      )!,
    );
  }

  @override
  $KvTable createAlias(String alias) {
    return $KvTable(attachedDatabase, alias);
  }
}

class KvData extends DataClass implements Insertable<KvData> {
  final String k;
  final String v;
  const KvData({required this.k, required this.v});
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['k'] = Variable<String>(k);
    map['v'] = Variable<String>(v);
    return map;
  }

  KvCompanion toCompanion(bool nullToAbsent) {
    return KvCompanion(k: Value(k), v: Value(v));
  }

  factory KvData.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return KvData(
      k: serializer.fromJson<String>(json['k']),
      v: serializer.fromJson<String>(json['v']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'k': serializer.toJson<String>(k),
      'v': serializer.toJson<String>(v),
    };
  }

  KvData copyWith({String? k, String? v}) =>
      KvData(k: k ?? this.k, v: v ?? this.v);
  KvData copyWithCompanion(KvCompanion data) {
    return KvData(
      k: data.k.present ? data.k.value : this.k,
      v: data.v.present ? data.v.value : this.v,
    );
  }

  @override
  String toString() {
    return (StringBuffer('KvData(')
          ..write('k: $k, ')
          ..write('v: $v')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(k, v);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is KvData && other.k == this.k && other.v == this.v);
}

class KvCompanion extends UpdateCompanion<KvData> {
  final Value<String> k;
  final Value<String> v;
  final Value<int> rowid;
  const KvCompanion({
    this.k = const Value.absent(),
    this.v = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  KvCompanion.insert({
    required String k,
    required String v,
    this.rowid = const Value.absent(),
  }) : k = Value(k),
       v = Value(v);
  static Insertable<KvData> custom({
    Expression<String>? k,
    Expression<String>? v,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (k != null) 'k': k,
      if (v != null) 'v': v,
      if (rowid != null) 'rowid': rowid,
    });
  }

  KvCompanion copyWith({
    Value<String>? k,
    Value<String>? v,
    Value<int>? rowid,
  }) {
    return KvCompanion(
      k: k ?? this.k,
      v: v ?? this.v,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (k.present) {
      map['k'] = Variable<String>(k.value);
    }
    if (v.present) {
      map['v'] = Variable<String>(v.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('KvCompanion(')
          ..write('k: $k, ')
          ..write('v: $v, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

abstract class _$AppDb extends GeneratedDatabase {
  _$AppDb(QueryExecutor e) : super(e);
  $AppDbManager get managers => $AppDbManager(this);
  late final $ResponseCacheTable responseCache = $ResponseCacheTable(this);
  late final $KvTable kv = $KvTable(this);
  @override
  Iterable<TableInfo<Table, Object?>> get allTables =>
      allSchemaEntities.whereType<TableInfo<Table, Object?>>();
  @override
  List<DatabaseSchemaEntity> get allSchemaEntities => [responseCache, kv];
}

typedef $$ResponseCacheTableCreateCompanionBuilder =
    ResponseCacheCompanion Function({
      required String cacheKey,
      required String data,
      Value<String?> meta,
      required DateTime fetchedAt,
      Value<int> rowid,
    });
typedef $$ResponseCacheTableUpdateCompanionBuilder =
    ResponseCacheCompanion Function({
      Value<String> cacheKey,
      Value<String> data,
      Value<String?> meta,
      Value<DateTime> fetchedAt,
      Value<int> rowid,
    });

class $$ResponseCacheTableFilterComposer
    extends Composer<_$AppDb, $ResponseCacheTable> {
  $$ResponseCacheTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get cacheKey => $composableBuilder(
    column: $table.cacheKey,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get data => $composableBuilder(
    column: $table.data,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get meta => $composableBuilder(
    column: $table.meta,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get fetchedAt => $composableBuilder(
    column: $table.fetchedAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $$ResponseCacheTableOrderingComposer
    extends Composer<_$AppDb, $ResponseCacheTable> {
  $$ResponseCacheTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get cacheKey => $composableBuilder(
    column: $table.cacheKey,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get data => $composableBuilder(
    column: $table.data,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get meta => $composableBuilder(
    column: $table.meta,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get fetchedAt => $composableBuilder(
    column: $table.fetchedAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$ResponseCacheTableAnnotationComposer
    extends Composer<_$AppDb, $ResponseCacheTable> {
  $$ResponseCacheTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get cacheKey =>
      $composableBuilder(column: $table.cacheKey, builder: (column) => column);

  GeneratedColumn<String> get data =>
      $composableBuilder(column: $table.data, builder: (column) => column);

  GeneratedColumn<String> get meta =>
      $composableBuilder(column: $table.meta, builder: (column) => column);

  GeneratedColumn<DateTime> get fetchedAt =>
      $composableBuilder(column: $table.fetchedAt, builder: (column) => column);
}

class $$ResponseCacheTableTableManager
    extends
        RootTableManager<
          _$AppDb,
          $ResponseCacheTable,
          ResponseCacheData,
          $$ResponseCacheTableFilterComposer,
          $$ResponseCacheTableOrderingComposer,
          $$ResponseCacheTableAnnotationComposer,
          $$ResponseCacheTableCreateCompanionBuilder,
          $$ResponseCacheTableUpdateCompanionBuilder,
          (
            ResponseCacheData,
            BaseReferences<_$AppDb, $ResponseCacheTable, ResponseCacheData>,
          ),
          ResponseCacheData,
          PrefetchHooks Function()
        > {
  $$ResponseCacheTableTableManager(_$AppDb db, $ResponseCacheTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$ResponseCacheTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$ResponseCacheTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$ResponseCacheTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> cacheKey = const Value.absent(),
                Value<String> data = const Value.absent(),
                Value<String?> meta = const Value.absent(),
                Value<DateTime> fetchedAt = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => ResponseCacheCompanion(
                cacheKey: cacheKey,
                data: data,
                meta: meta,
                fetchedAt: fetchedAt,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String cacheKey,
                required String data,
                Value<String?> meta = const Value.absent(),
                required DateTime fetchedAt,
                Value<int> rowid = const Value.absent(),
              }) => ResponseCacheCompanion.insert(
                cacheKey: cacheKey,
                data: data,
                meta: meta,
                fetchedAt: fetchedAt,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map(
                (e) => (
                  e.readTable<$ResponseCacheTable, ResponseCacheData>(table),
                  BaseReferences<
                    _$AppDb,
                    $ResponseCacheTable,
                    ResponseCacheData
                  >(db, table, e),
                ),
              )
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$ResponseCacheTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDb,
      $ResponseCacheTable,
      ResponseCacheData,
      $$ResponseCacheTableFilterComposer,
      $$ResponseCacheTableOrderingComposer,
      $$ResponseCacheTableAnnotationComposer,
      $$ResponseCacheTableCreateCompanionBuilder,
      $$ResponseCacheTableUpdateCompanionBuilder,
      (
        ResponseCacheData,
        BaseReferences<_$AppDb, $ResponseCacheTable, ResponseCacheData>,
      ),
      ResponseCacheData,
      PrefetchHooks Function()
    >;
typedef $$KvTableCreateCompanionBuilder = KvCompanion Function({
  required String k,
  required String v,
  Value<int> rowid,
});
typedef $$KvTableUpdateCompanionBuilder = KvCompanion Function({
  Value<String> k,
  Value<String> v,
  Value<int> rowid,
});

class $$KvTableFilterComposer extends Composer<_$AppDb, $KvTable> {
  $$KvTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get k => $composableBuilder(
    column: $table.k,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get v => $composableBuilder(
    column: $table.v,
    builder: (column) => ColumnFilters(column),
  );
}

class $$KvTableOrderingComposer extends Composer<_$AppDb, $KvTable> {
  $$KvTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get k => $composableBuilder(
    column: $table.k,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get v => $composableBuilder(
    column: $table.v,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$KvTableAnnotationComposer extends Composer<_$AppDb, $KvTable> {
  $$KvTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get k =>
      $composableBuilder(column: $table.k, builder: (column) => column);

  GeneratedColumn<String> get v =>
      $composableBuilder(column: $table.v, builder: (column) => column);
}

class $$KvTableTableManager
    extends
        RootTableManager<
          _$AppDb,
          $KvTable,
          KvData,
          $$KvTableFilterComposer,
          $$KvTableOrderingComposer,
          $$KvTableAnnotationComposer,
          $$KvTableCreateCompanionBuilder,
          $$KvTableUpdateCompanionBuilder,
          (KvData, BaseReferences<_$AppDb, $KvTable, KvData>),
          KvData,
          PrefetchHooks Function()
        > {
  $$KvTableTableManager(_$AppDb db, $KvTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$KvTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$KvTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$KvTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback: ({
            Value<String> k = const Value.absent(),
            Value<String> v = const Value.absent(),
            Value<int> rowid = const Value.absent(),
          }) => KvCompanion(k: k, v: v, rowid: rowid),
          createCompanionCallback: ({
            required String k,
            required String v,
            Value<int> rowid = const Value.absent(),
          }) => KvCompanion.insert(k: k, v: v, rowid: rowid),
          withReferenceMapper: (p0) => p0
              .map(
                (e) => (
                  e.readTable<$KvTable, KvData>(table),
                  BaseReferences<_$AppDb, $KvTable, KvData>(db, table, e),
                ),
              )
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$KvTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDb,
      $KvTable,
      KvData,
      $$KvTableFilterComposer,
      $$KvTableOrderingComposer,
      $$KvTableAnnotationComposer,
      $$KvTableCreateCompanionBuilder,
      $$KvTableUpdateCompanionBuilder,
      (KvData, BaseReferences<_$AppDb, $KvTable, KvData>),
      KvData,
      PrefetchHooks Function()
    >;

class $AppDbManager {
  final _$AppDb _db;
  $AppDbManager(this._db);
  $$ResponseCacheTableTableManager get responseCache =>
      $$ResponseCacheTableTableManager(_db, _db.responseCache);
  $$KvTableTableManager get kv => $$KvTableTableManager(_db, _db.kv);
}
