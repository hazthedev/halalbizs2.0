import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/api/json.dart';
import '../../core/offline/offline.dart';
import '../catalog/models.dart';

/// `AddressResource`: id, label, recipient_name, phone, line1, line2,
/// postcode, city, state, country, is_default.
class Address {
  Address(this.json);

  final Map<String, dynamic> json;

  int get id => asInt(json['id']) ?? 0;
  String? get label => asStr(json['label']);
  String get recipientName => asStr(json['recipient_name']) ?? '';
  String get phone => asStr(json['phone']) ?? '';
  String get line1 => asStr(json['line1']) ?? '';
  String? get line2 => asStr(json['line2']);
  String get postcode => asStr(json['postcode']) ?? '';
  String get city => asStr(json['city']) ?? '';
  String get state => asStr(json['state']) ?? '';
  bool get isDefault => asBool(json['is_default']);

  /// "12 Jalan Satu, Taman Dua, 50450 Kuala Lumpur, Kuala Lumpur".
  String get oneLine => [line1, ?line2, '$postcode $city'.trim(), state].where((s) => s.isNotEmpty).join(', ');
}

/// `GET coins`: balance, lifetime_earned, streak, can_check_in, expiring_at, ledger[].
class CoinsSummary {
  CoinsSummary(this.json);

  final Map<String, dynamic> json;

  int get balance => asInt(json['balance']) ?? 0;
  int get lifetimeEarned => asInt(json['lifetime_earned']) ?? 0;
  int get streak => asInt(json['streak']) ?? 0;
  bool get canCheckIn => asBool(json['can_check_in']);
  DateTime? get expiringAt => asDate(json['expiring_at']);
  List<Map<String, dynamic>> get ledger => asMapList(json['ledger']);
}

/// `GET notifications` row: id, type, message, detail, url, app_url, read_at, created_at.
class AppNotification {
  AppNotification(this.json);

  final Map<String, dynamic> json;

  String get id => asStr(json['id']) ?? '';
  String? get message => asStr(json['message']);
  String? get detail => asStr(json['detail']);
  String? get appUrl => asStr(json['app_url']);
  bool get read => json['read_at'] != null;
  DateTime? get createdAt => asDate(json['created_at']);
}

/// Profile, password, deletion, address book, wishlist, coins and
/// notifications (`auth:sanctum`). Writes are plain API calls; the reads a
/// page can usefully show offline go through [OfflineRepo].
class AccountApi {
  AccountApi(this._api, this._repo);

  final ApiClient _api;
  final OfflineRepo _repo;

  /// `PATCH me {name?, phone?, preferred_locale?}`.
  Future<void> updateMe({String? name, String? phone, String? preferredLocale}) =>
      _api.patch('me', body: {'name': ?name, 'phone': ?phone, 'preferred_locale': ?preferredLocale});

  /// `POST me/web-sign-in-link {to}` → a 5-minute, single-use URL that opens
  /// the web page already signed in as this user.
  Future<String> webSignInLink(String to, {int? id}) async =>
      asStr(asMap(await _api.post('me/web-sign-in-link', body: {'to': to, 'id': ?id}))['url']) ?? '';

  /// `PUT me/password {current_password, password, password_confirmation}`.
  /// Every token is revoked server-side; the answer carries this device's fresh one.
  Future<String?> changePassword({required String current, required String password, required String confirmation}) async {
    final data = await _api.put('me/password',
        body: {'current_password': current, 'password': password, 'password_confirmation': confirmation});
    return data is Map ? data['token'] as String? : null;
  }

  /// `DELETE me {password}` — 409 `account_deletion_blocked` carries the reason.
  Future<void> deleteAccount(String password) => _api.delete('me', body: {'password': password});

  Future<List<Address>> addresses() async => asMapList(await _api.get('addresses')).map(Address.new).toList();

  Future<Address> createAddress(Map<String, dynamic> body) async => Address(asMap(await _api.post('addresses', body: body)));

  Future<Address> updateAddress(int id, Map<String, dynamic> body) async =>
      Address(asMap(await _api.put('addresses/$id', body: body)));

  Future<void> deleteAddress(int id) => _api.delete('addresses/$id');

  Future<List<Product>> wishlist() async => asMapList(await _api.get('wishlist')).map(Product.new).toList();

  /// `POST wishlist/{product}` → `{wishlisted}`.
  Future<bool> toggleWishlist(int productId) async => asBool(asMap(await _api.post('wishlist/$productId'))['wishlisted']);

  Future<CoinsSummary> coins() async => CoinsSummary(asMap(await _api.get('coins')));

  /// `POST coins/check-in` → `{coins, streak, day}`; 409 `already_checked_in`.
  Future<Map<String, dynamic>> checkIn() async => asMap(await _api.post('coins/check-in'));

  Future<List<AppNotification>> notifications() async =>
      asMapList(await _api.get('notifications')).map(AppNotification.new).toList();

  Future<void> markNotificationRead(String id) => _api.post('notifications/$id/read');

  Future<void> markAllNotificationsRead() => _api.post('notifications/read-all');

  /// `GET config` (public) — the app needs `states[]` for the address form.
  Future<Map<String, dynamic>> config() async => asMap((await _repo.getJson('config')).data);
}

final accountApiProvider =
    Provider<AccountApi>((ref) => AccountApi(ref.watch(apiClientProvider), ref.watch(offlineRepoProvider)));

final addressesProvider = FutureProvider<List<Address>>((ref) => ref.watch(accountApiProvider).addresses());

final wishlistProvider = FutureProvider<List<Product>>((ref) => ref.watch(accountApiProvider).wishlist());

final coinsProvider = FutureProvider<CoinsSummary>((ref) => ref.watch(accountApiProvider).coins());

final notificationsProvider =
    FutureProvider<List<AppNotification>>((ref) => ref.watch(accountApiProvider).notifications());

final configProvider = FutureProvider<Map<String, dynamic>>((ref) => ref.watch(accountApiProvider).config());

/// Malaysian states from `GET config`, for the address form dropdown.
final statesProvider = FutureProvider<List<String>>(
    (ref) async => asList((await ref.watch(configProvider.future))['states']).map((s) => '$s').toList());
