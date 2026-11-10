import 'dart:async';
import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../api/json.dart';
import '../db/app_db.dart';
import '../push/push_service.dart';
import 'token_store.dart';

/// The signed-in user as served by `GET me` (and `POST auth/login`):
/// `{id, name, email, phone, email_verified, preferred_locale, roles[],
/// store{id,slug,name,status}|null, unread_notifications, coins}`
/// (`UserResource`). Older keys are still read as fallbacks.
class Profile {
  Profile(this.json);

  final Map<String, dynamic> json;

  int get id => asInt(json['id']) ?? 0;
  String get name => asStr(json['name']) ?? '';
  String? get email => asStr(json['email']);
  String? get phone => asStr(json['phone']);
  String? get avatar => asStr(json['avatar']);
  String? get preferredLocale => asStr(json['preferred_locale']);

  /// Loyalty coins are a count, not money.
  int get coins => asInt(json['coins'] ?? json['coins_balance']) ?? 0;
  bool get emailVerified => asBool(json['email_verified']) || json['email_verified_at'] != null;
  List<String> get roles => asList(json['roles']).map((r) => '$r').toList();

  /// `{id, slug, name, status}` of the user's store, if any.
  Map<String, dynamic>? get store => json['store'] is Map ? asMap(json['store']) : null;
  int? get storeId => asInt(store?['id']);
  String? get storeName => asStr(store?['name']);
  bool get isApprovedSeller => asStr(store?['status']) == 'approved';
  int get unreadNotifications =>
      asInt(json['unread_notifications'] ?? asMap(json['unread'])['notifications']) ?? 0;

  /// Not in `GET me` yet; the conversations list carries per-thread counts.
  int get unreadMessages => asInt(json['unread_messages'] ?? asMap(json['unread'])['messages']) ?? 0;
}

sealed class SessionState {
  const SessionState();
}

class SessionLoading extends SessionState {
  const SessionLoading();
}

class SignedOut extends SessionState {
  const SignedOut({this.message, this.sessionEnded = false});
  final String? message;

  /// The server revoked the token (401): the login form explains why.
  final bool sessionEnded;
}

/// Login answered 202 `{two_factor: {challenge, method}}`: the user must
/// enter the TOTP / emailed code before a token is issued.
class TwoFactorPending extends SessionState {
  const TwoFactorPending(this.challenge, this.method);
  final String challenge;

  /// `totp` or `email` (web `TwoFactorMethod`).
  final String method;
}

class SignedIn extends SessionState {
  const SignedIn(this.profile, {this.offline = false});
  final Profile profile;

  /// True when the profile came from cache because the server was unreachable.
  final bool offline;
}

class SessionController extends Notifier<SessionState> {
  static const _kProfile = 'session.profile';
  static const _kUserId = 'session.user_id';

  @override
  SessionState build() {
    final api = ref.read(apiClientProvider);
    api.onUnauthenticated = _onUnauthenticated;
    Future.microtask(restore);
    return const SessionLoading();
  }

  ApiClient get _api => ref.read(apiClientProvider);
  AppDb get _db => ref.read(appDbProvider);
  TokenStore get _tokens => ref.read(tokenStoreProvider);

  /// App start: token present → try `GET me`; offline → cached profile.
  Future<void> restore() async {
    final token = await _tokens.read();
    if (token == null) {
      state = const SignedOut();
      return;
    }
    try {
      final me = asMap(await _api.get('me'));
      await _kvPut(_kProfile, jsonEncode(me));
      state = SignedIn(Profile(me));
    } on ApiException catch (e) {
      if (e.isOffline) {
        final cached = _decode(await _kvGet(_kProfile));
        if (cached != null) {
          state = SignedIn(Profile(cached), offline: true);
          return;
        }
      }
      // 401 (revoked) is handled by _onUnauthenticated via the interceptor;
      // make sure we end signed out for any other failure without a cache.
      if (state is! SignedOut) state = const SignedOut();
    }
  }

  /// `POST auth/login` → SignedIn, or TwoFactorPending when the server
  /// answers with a challenge instead of a token.
  Future<void> login(String email, String password) async {
    final data = asMap(await _api.post('auth/login', body: {
      'email': email,
      'password': password,
      'device_name': await _tokens.deviceName(),
    }));
    if (data['two_factor'] is Map) {
      final tf = asMap(data['two_factor']);
      state = TwoFactorPending(asStr(tf['challenge']) ?? '', asStr(tf['method']) ?? 'totp');
      return;
    }
    await _signIn(data);
  }

  /// `POST auth/two-factor {challenge, code, device_name}` → token + user.
  Future<void> verifyTwoFactor(String code) async {
    final s = state;
    if (s is! TwoFactorPending) throw ApiException('no_challenge', 'Sign in again to get a new code.');
    final data = asMap(await _api.post('auth/two-factor', body: {
      'challenge': s.challenge,
      'code': code,
      'device_name': await _tokens.deviceName(),
    }));
    await _signIn(data);
  }

  /// `POST auth/register` → token (+ user).
  Future<void> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
  }) async {
    final data = asMap(await _api.post('auth/register', body: {
      'name': name,
      'email': email,
      'password': password,
      'password_confirmation': passwordConfirmation,
      'device_name': await _tokens.deviceName(),
    }));
    await _signIn(data);
  }

  Future<void> _signIn(Map<String, dynamic> data) async {
    final token = data['token'];
    if (token is! String || token.isEmpty) {
      throw ApiException('bad_envelope', 'Sign-in response carried no token.');
    }
    final previousId = await _kvGet(_kUserId);
    try {
      await _tokens.write(token);
    } catch (_) {
      throw ApiException('token_storage', 'This device refused to store the sign-in token securely.');
    }
    var user = data['user'] is Map ? asMap(data['user']) : null;
    user ??= asMap(await _api.get('me'));
    // A different account must not see the previous account's cached data.
    if (previousId != null && previousId != '${user['id']}') {
      try {
        await _db.wipe();
      } catch (_) {}
    }
    await _kvPut(_kUserId, '${user['id']}');
    await _kvPut(_kProfile, jsonEncode(user));
    state = SignedIn(Profile(user));
  }

  /// Back to the login form from the two-factor screen.
  void cancelTwoFactor() {
    if (state is TwoFactorPending) state = const SignedOut();
  }

  /// How long sign-out waits for the server to revoke the token before it
  /// signs out locally anyway.
  static Duration revokeTimeout = const Duration(seconds: 5);

  bool _signingOut = false;

  /// Sign out of this device. Never hangs and never fails: the server revoke
  /// is bounded by [revokeTimeout] and best-effort; the token, cached data
  /// and session state are always cleared afterwards.
  /// The server rotated this device's token (password change): keep the
  /// session, swap the bearer.
  Future<void> adoptToken(String token) => _tokens.write(token);

  Future<void> logout() async {
    if (_signingOut) return;
    _signingOut = true;
    try {
      try {
        // Push registration is per account: drop it while the token still works.
        await ref.read(pushServiceProvider).unregister().timeout(revokeTimeout);
      } catch (_) {}
      try {
        await _api.post('auth/logout').timeout(revokeTimeout);
      } catch (_) {
        // Timed out, offline, or the token is already dead — sign out locally anyway.
      }
      try {
        await _tokens.clear().timeout(revokeTimeout);
      } catch (_) {}
      try {
        await _db.wipeCaches().timeout(revokeTimeout);
      } catch (_) {}
    } finally {
      state = const SignedOut();
      _signingOut = false;
    }
  }

  /// Refresh the profile in place (after "I have verified", profile edits…).
  Future<void> refreshProfile() async {
    final me = asMap(await _api.get('me'));
    await _kvPut(_kProfile, jsonEncode(me));
    state = SignedIn(Profile(me));
  }

  Future<void> _onUnauthenticated() async {
    // A 401 from POST auth/logout itself is expected — logout() finishes.
    if (_signingOut) return;
    if (state is SignedIn || state is SessionLoading) {
      try {
        await _tokens.clear();
      } catch (_) {
        // The keychain refused the delete; the token is already dead server-side.
      }
      try {
        await _db.wipeCaches();
      } catch (_) {}
      state = const SignedOut(sessionEnded: true);
    }
  }

  // The database can be unavailable (web without the sqlite worker, early
  // test teardown): the session must still work without the profile cache.
  Future<String?> _kvGet(String k) async {
    try {
      return await _db.kvGet(k);
    } catch (_) {
      return null;
    }
  }

  Future<void> _kvPut(String k, String v) async {
    try {
      await _db.kvPut(k, v);
    } catch (_) {}
  }

  /// A corrupt cached profile counts as no cache.
  static Map<String, dynamic>? _decode(String? json) {
    if (json == null) return null;
    try {
      final v = jsonDecode(json);
      return v is Map<String, dynamic> ? v : null;
    } catch (_) {
      return null;
    }
  }
}

final sessionProvider = NotifierProvider<SessionController, SessionState>(SessionController.new);

/// Convenience: the signed-in profile, or null.
final profileProvider = Provider<Profile?>((ref) {
  final s = ref.watch(sessionProvider);
  return s is SignedIn ? s.profile : null;
});
