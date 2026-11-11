import '../../api/api_client.dart';

/// First 422 message for [path] or any `path.N` child (`photos.0`,
/// `items.3.rating`), for inputs that stand for a whole list.
String? fieldErrorAt(Object? error, String path) {
  if (error is! ApiException) return null;
  for (final e in error.fields.entries) {
    if (e.key == path || e.key.startsWith('$path.')) return e.value.firstOrNull;
  }
  return null;
}

/// True when every key in a 422 lands on one of [paths] (as the field or a
/// `path.N` child). False means some message has no input to sit under, so
/// the screen should surface the error as a whole instead of swallowing it.
bool fieldsCovered(Object? error, Iterable<String> paths) =>
    error is ApiException && error.fields.keys.every((k) => paths.any((p) => k == p || k.startsWith('$p.')));
