/// Tolerant JSON readers for the Map-wrapper models (no codegen). The API is
/// typed on the server; these only guard against null / string-typed numbers.
library;

Map<String, dynamic> asMap(dynamic v) => v is Map<String, dynamic> ? v : (v is Map ? Map<String, dynamic>.from(v) : const {});

List<dynamic> asList(dynamic v) => v is List ? v : const [];

List<Map<String, dynamic>> asMapList(dynamic v) => asList(v).map(asMap).toList();

num? asNum(dynamic v) => v is num ? v : (v is String ? num.tryParse(v) : null);

int? asInt(dynamic v) => asNum(v)?.toInt();

double? asDouble(dynamic v) => asNum(v)?.toDouble();

String? asStr(dynamic v) => v == null ? null : (v is String ? (v.isEmpty ? null : v) : '$v');

bool asBool(dynamic v) => v == true || v == 1 || v == '1' || v == 'true';

DateTime? asDate(dynamic v) => v is String ? DateTime.tryParse(v) : null;
