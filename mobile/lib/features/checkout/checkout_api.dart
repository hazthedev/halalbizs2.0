import 'dart:math';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/api/json.dart';

/// `POST checkout/quote` → `CheckoutQuote::toArray()`. Every amount is a
/// server-computed `_sen` integer; the app only displays them.
class CheckoutQuote {
  CheckoutQuote(this.json);

  final Map<String, dynamic> json;

  bool get purchasingEnabled => json.containsKey('purchasing_enabled') ? asBool(json['purchasing_enabled']) : true;
  List<QuoteGroup> get groups => asMapList(json['groups']).map(QuoteGroup.new).toList();
  int get subtotalSen => asInt(json['subtotal_sen']) ?? 0;
  int get shippingTotalSen => asInt(json['shipping_total_sen']) ?? 0;
  int get shippingSubsidySen => asInt(json['shipping_subsidy_sen']) ?? 0;
  int get taxTotalSen => asInt(json['tax_total_sen']) ?? 0;
  int get platformDiscountSen => asInt(json['platform_discount_sen']) ?? 0;
  int get shopDiscountSen => asInt(json['shop_discount_sen']) ?? 0;
  int get coinRedemptionSen => asInt(json['coin_redemption_sen']) ?? 0;
  int get grandTotalSen => asInt(json['grand_total_sen']) ?? 0;

  /// Applied voucher per slot (`platform`, `shop`, `shipping`): `{id, code, type, discount_sen}` or null.
  Map<String, dynamic>? voucher(String slot) {
    final v = asMap(json['vouchers'])[slot];
    return v is Map ? asMap(v) : null;
  }

  /// Slot → reason for a code that did not apply.
  Map<String, String> get voucherErrors =>
      asMap(json['voucher_errors']).map((k, v) => MapEntry(k, '$v')).cast<String, String>();

  /// `voucher_options.{platform,shop}[]`: the vouchers this buyer may pick.
  List<VoucherOption> voucherOptions(String slot) =>
      asMapList(asMap(json['voucher_options'])[slot]).map(VoucherOption.new).toList();

  /// The platform voucher the server ranks best (largest discount), or null.
  int? get bestPlatformVoucherId => asInt(asMap(json['voucher_options'])['best_platform_voucher_id']);

  Map<String, dynamic> get coins => asMap(json['coins']);
  bool get coinsEnabled => asBool(coins['enabled']);
  int get coinBalance => asInt(coins['balance']) ?? 0;
  int get coinsRedeemed => asInt(coins['redeemed']) ?? 0;

  List<PaymentOption> get paymentMethods => asMapList(json['payment_methods']).map(PaymentOption.new).toList();
  String? get paymentMethod => asStr(json['payment_method']);
  bool get paymentMethodAvailable => json.containsKey('payment_method_available') ? asBool(json['payment_method_available']) : true;
  String? get blockReason => asStr(json['block_reason']);
}

class VoucherOption {
  VoucherOption(this.json);

  final Map<String, dynamic> json;

  int get id => asInt(json['id']) ?? 0;
  String get code => asStr(json['code']) ?? '';
  String get label => asStr(json['label']) ?? code;
  String? get storeName => asStr(json['store_name']);
  bool get minSpendMet => json.containsKey('min_spend_met') ? asBool(json['min_spend_met']) : true;
  int get shortBySen => asInt(json['short_by_sen']) ?? 0;
  int get discountSen => asInt(json['discount_sen']) ?? 0;
  bool get freeShipping => asBool(json['free_shipping']);
}

class QuoteGroup {
  QuoteGroup(this.json);

  final Map<String, dynamic> json;

  Map<String, dynamic> get store => asMap(json['store']);
  int get storeId => asInt(store['id']) ?? 0;
  String get storeName => asStr(store['name']) ?? '';
  int get itemsSubtotalSen => asInt(json['items_subtotal_sen']) ?? 0;

  /// Null until an address is chosen (shipping depends on the state).
  int? get shippingFeeSen => asInt(json['shipping_fee_sen']);
  int get shippingSubsidySen => asInt(json['shipping_subsidy_sen']) ?? 0;
  List<Map<String, dynamic>> get lines => asMapList(json['lines']);
}

/// `payment_methods[{method, available, reason}]`.
class PaymentOption {
  PaymentOption(this.json);

  final Map<String, dynamic> json;

  String get method => asStr(json['method']) ?? '';
  bool get available => asBool(json['available']);
  String? get reason => asStr(json['reason']);
}

/// `POST checkout/place` 201 payload.
class PlacedOrder {
  PlacedOrder(this.json);

  final Map<String, dynamic> json;

  String get orderNo => asStr(json['order_no']) ?? '';
  String get paymentMethod => asStr(json['payment_method']) ?? 'cod';
  int get totalSen => asInt(json['total_sen']) ?? 0;

  /// Signed web hand-off URL (iPay88 only; open in the system browser).
  String? get paymentUrl => asStr(json['payment_url']);
  DateTime? get expiresAt => asDate(json['expires_at']);
}

/// `GET orders/{orderNo}/payment`.
class PaymentState {
  PaymentState(this.json);

  final Map<String, dynamic> json;

  String get orderNo => asStr(json['order_no']) ?? '';
  String get method => asStr(json['method']) ?? '';
  String get status => asStr(json['status']) ?? '';
  bool get paid => asBool(json['paid']);
  DateTime? get expiresAt => asDate(json['expires_at']);

  /// Fresh hand-off URL while the order can still be paid, else null.
  String? get retryUrl => asStr(json['retry_url']);

  int get totalSen => asInt(json['total_sen']) ?? 0;

  /// One row per shop order: `{id, sub_order_no, store{id,name}, status}`.
  List<Map<String, dynamic>> get subOrders => asMapList(json['sub_orders']);
  bool get expired => retryUrl == null && !paid && (expiresAt == null || !DateTime.now().isBefore(expiresAt!));
}

/// Checkout over `verified.api` (403 `email_unverified` → `/verify-email`).
class CheckoutApi {
  CheckoutApi(this._api);

  final ApiClient _api;

  Map<String, dynamic> _body({
    int? addressId,
    String? paymentMethod,
    String? platformCode,
    String? shopCode,
    int coins = 0,
    Map<int, String>? sellerNotes,
  }) =>
      {
        'address_id': ?addressId,
        'payment_method': ?paymentMethod,
        'platform_code': ?platformCode,
        'shop_code': ?shopCode,
        if (coins > 0) 'coins': coins,
        if (sellerNotes != null && sellerNotes.isNotEmpty)
          'seller_notes': sellerNotes.map((k, v) => MapEntry('$k', v)),
      };

  Future<CheckoutQuote> quote({
    int? addressId,
    String? paymentMethod,
    String? platformCode,
    String? shopCode,
    int coins = 0,
  }) async =>
      CheckoutQuote(asMap(await _api.post('checkout/quote',
          body: _body(addressId: addressId, paymentMethod: paymentMethod, platformCode: platformCode, shopCode: shopCode, coins: coins))));

  /// [idempotencyKey]: one per checkout attempt, reused on retry — the server
  /// replays the order that key already placed instead of placing a second.
  Future<PlacedOrder> place({
    required int addressId,
    required String paymentMethod,
    required String idempotencyKey,
    String? platformCode,
    String? shopCode,
    int coins = 0,
    Map<int, String>? sellerNotes,
  }) async =>
      PlacedOrder(asMap(await _api.post('checkout/place',
          headers: {'Idempotency-Key': idempotencyKey},
          body: _body(
            addressId: addressId,
            paymentMethod: paymentMethod,
            platformCode: platformCode,
            shopCode: shopCode,
            coins: coins,
            sellerNotes: sellerNotes,
          ))));

  Future<PaymentState> payment(String orderNo) async =>
      PaymentState(asMap(await _api.get('orders/${Uri.encodeComponent(orderNo)}/payment')));
}

/// 32 hex chars from the secure RNG — server accepts `[A-Za-z0-9_-]{8,64}`.
String newIdempotencyKey() {
  final r = Random.secure();
  return List.generate(16, (_) => r.nextInt(256).toRadixString(16).padLeft(2, '0')).join();
}

final checkoutApiProvider = Provider<CheckoutApi>((ref) => CheckoutApi(ref.watch(apiClientProvider)));

/// The done page's read of `GET orders/{orderNo}/payment`.
final paymentStateProvider =
    FutureProvider.autoDispose.family<PaymentState, String>((ref, orderNo) => ref.watch(checkoutApiProvider).payment(orderNo));
