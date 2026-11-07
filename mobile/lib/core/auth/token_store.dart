import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Sanctum bearer token in platform secure storage (Keychain on iOS,
/// Keystore on Android). Tests use [MemoryTokenStore].
class TokenStore {
  TokenStore();

  static const _kToken = 'halalbizs_token';
  static const _storage = FlutterSecureStorage();

  String? _cached;

  Future<String?> read() async {
    if (_cached != null) return _cached;
    try {
      return _cached = await _storage.read(key: _kToken);
    } catch (_) {
      return null;
    }
  }

  /// Throws if the platform keychain refuses the write — callers must treat
  /// that as a failed sign-in (the in-memory cache is only set on success).
  Future<void> write(String token) async {
    await _storage.write(key: _kToken, value: token);
    _cached = token;
  }

  /// Throws if the keychain refuses the delete, so the caller decides what a
  /// lingering token means (sign-out still completes locally).
  Future<void> clear() async {
    _cached = null;
    await _storage.delete(key: _kToken);
  }

  /// Stable per-install suffix so two identical devices ("iPhone") never share
  /// a device_name — Sanctum tokens are listed by name in the account.
  static Future<String> _installId() async {
    const key = 'halalbizs_install_id';
    try {
      final existing = await _storage.read(key: key);
      if (existing != null && existing.isNotEmpty) return existing;
      final id = DateTime.now().microsecondsSinceEpoch.toRadixString(36);
      final suffix = id.substring(id.length - 6);
      await _storage.write(key: key, value: suffix);
      return suffix;
    } catch (_) {
      return 'x';
    }
  }

  /// Device model + install suffix for `POST auth/login` (e.g. "iPhone · ios ·
  /// k3f9q1"). Never the owner's device name ("Haze's iPhone").
  Future<String> deviceName() async {
    final suffix = await _installId();
    return '${await _baseDeviceName()} · $suffix';
  }

  static Future<String> _baseDeviceName() async {
    if (kIsWeb) return 'Web browser';
    final info = DeviceInfoPlugin();
    try {
      if (defaultTargetPlatform == TargetPlatform.iOS) {
        final i = await info.iosInfo;
        return '${i.model} · ios';
      }
      if (defaultTargetPlatform == TargetPlatform.android) {
        final a = await info.androidInfo;
        return '${a.model} · android';
      }
    } catch (_) {}
    return 'HalalBizs device';
  }
}

/// In-memory token store for widget tests (no platform channel).
class MemoryTokenStore extends TokenStore {
  MemoryTokenStore([this._token]);

  String? _token;

  @override
  Future<String?> read() async => _token;

  @override
  Future<void> write(String token) async => _token = token;

  @override
  Future<void> clear() async => _token = null;

  @override
  Future<String> deviceName() async => 'Test device';
}
