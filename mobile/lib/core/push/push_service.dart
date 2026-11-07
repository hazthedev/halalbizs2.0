import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../db/app_db.dart';

/// `--dart-define=PUSH_ENABLED=true` turns device registration on. Off by
/// default: there is no Firebase project yet (README), so nothing produces a
/// token and the server channel is a no-op until the config files exist.
final pushEnabledProvider = Provider<bool>((_) => const bool.fromEnvironment('PUSH_ENABLED'));

/// Device-token registration against `POST devices` / `DELETE devices/{token}`
/// (mobile plan §5). The FCM token itself arrives from `firebase_messaging`,
/// which is added only once the Firebase config files exist; until then
/// [registerDevice] is called by nobody and [unregister] is a cheap no-op.
class PushService {
  PushService(this._api, this._db, {required this.enabled});

  static const _kToken = 'push.token';

  final ApiClient _api;
  final AppDb _db;
  final bool enabled;

  /// `POST devices {token, platform}`; remembers the token so sign-out can
  /// drop it. [platform] defaults to the running OS (`ios` / `android`).
  Future<void> registerDevice(String token, {String? platform}) async {
    if (!enabled) return;
    platform ??= defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android';
    await _api.post('devices', body: {'token': token, 'platform': platform});
    try {
      await _db.kvPut(_kToken, token);
    } catch (_) {}
  }

  /// `DELETE devices/{token}` for the token registered on this install, if any.
  Future<void> unregister() async {
    String? token;
    try {
      token = await _db.kvGet(_kToken);
    } catch (_) {
      return;
    }
    if (token == null) return;
    try {
      await _api.delete('devices/${Uri.encodeComponent(token)}');
    } finally {
      try {
        await _db.kvDelete(_kToken);
      } catch (_) {}
    }
  }
}

final pushServiceProvider = Provider<PushService>((ref) => PushService(
      ref.watch(apiClientProvider),
      ref.watch(appDbProvider),
      enabled: ref.watch(pushEnabledProvider),
    ));
