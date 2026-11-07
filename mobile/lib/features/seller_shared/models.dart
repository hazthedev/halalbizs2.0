/// Map wrappers over the seller resources (PR #176). Keys follow the API
/// contract; readers are tolerant so a missing field never crashes a screen.
library;

import '../../core/api/json.dart';

/// One page of a seller list. The seller endpoints paginate with
/// `meta{page, per_page, total, last_page}`; `current_page` is read too for
/// the older paginator shape.
class SellerPage<T> {
  SellerPage(this.items, {Map<String, dynamic>? meta, this.fromCache = false, this.fetchedAt})
      : page = asInt(meta?['page'] ?? meta?['current_page']) ?? 1,
        lastPage = asInt(meta?['last_page']) ?? 1,
        total = asInt(meta?['total']) ?? 0;

  final List<T> items;
  final int page;
  final int lastPage;
  final int total;
  final bool fromCache;
  final DateTime? fetchedAt;

  bool get hasMore => page < lastPage;
}

class SellerOrderItem {
  SellerOrderItem(this.json);

  final Map<String, dynamic> json;

  int get id => asInt(json['id']) ?? 0;
  String get name => asStr(json['name']) ?? '';
  String? get variantName => asStr(json['variant_name']);
  int get qty => asInt(json['qty']) ?? 0;
  int get unitPriceSen => asInt(json['unit_price_sen']) ?? 0;
  int get lineTotalSen => asInt(json['line_total_sen']) ?? 0;
  String? get productImage => asStr(json['product_image']);
}

class SellerOrder {
  SellerOrder(this.json);

  final Map<String, dynamic> json;

  int get id => asInt(json['id']) ?? 0;
  String get subOrderNo => asStr(json['sub_order_no']) ?? '';
  String? get orderNo => asStr(json['order_no']);
  String get status => asStr(json['status']) ?? '';
  String get statusLabel => asStr(json['status_label']) ?? status;
  String? get placedAt => asStr(json['placed_at']);
  String get buyerName => asStr(asMap(json['buyer'])['name']) ?? '';
  String? get buyerPhone => asStr(asMap(json['buyer'])['phone']);
  Map<String, dynamic> get address => asMap(json['address']);
  List<SellerOrderItem> get items => asMapList(json['items']).map(SellerOrderItem.new).toList();
  int get itemsCount => items.fold(0, (n, i) => n + i.qty);
  int get subtotalSen => asInt(json['subtotal_sen']) ?? 0;
  int get shippingSen => asInt(json['shipping_sen']) ?? 0;
  int get discountSen => asInt(json['discount_sen']) ?? 0;
  int get taxSen => asInt(json['tax_sen']) ?? 0;
  int get totalSen => asInt(json['total_sen']) ?? 0;
  String? get commissionRate => asStr(json['commission_rate']);
  int? get commissionSen => asInt(json['commission_sen']);
  String? get paymentMethod => asStr(asMap(json['payment'])['method']);
  String? get paymentStatus => asStr(asMap(json['payment'])['status']);
  Map<String, dynamic> get tracking => asMap(json['tracking']);
  Map<String, dynamic>? get returnRequest => json['return_request'] is Map ? asMap(json['return_request']) : null;
  List<Map<String, dynamic>> get history => asMapList(json['history']);
}

class SellerVariant {
  SellerVariant(this.json);

  final Map<String, dynamic> json;

  int get id => asInt(json['id']) ?? 0;
  /// Null for the implicit default variant of a no-variation product.
  String? get optionsLabel => asStr(json['options_label']);
  String? get sku => asStr(json['sku']);
  int get priceSen => asInt(json['price_sen']) ?? 0;
  int? get salePriceSen => asInt(json['sale_price_sen']);
  int get stock => asInt(json['stock']) ?? 0;
  bool get isDefault => asBool(json['is_default']);
}

class SellerImage {
  SellerImage(this.json);

  final Map<String, dynamic> json;

  int get id => asInt(json['id']) ?? 0;
  String? get url => asStr(json['url']);
  String? get thumb => asStr(json['thumb']) ?? url;
}

class SellerProduct {
  SellerProduct(this.json);

  final Map<String, dynamic> json;

  int get id => asInt(json['id']) ?? 0;
  String get name => asStr(json['name']) ?? '';
  Map<String, String> get nameTranslations => _translations('name_translations');
  Map<String, String> get descriptionTranslations => _translations('description_translations');
  String get status => asStr(json['status']) ?? 'draft';
  String get statusLabel => asStr(json['status_label']) ?? status;
  int? get categoryId => asInt(asMap(json['category'])['id']);
  String? get categoryName => asStr(asMap(json['category'])['name']);
  String? get thumb => asStr(json['thumb']);
  int? get priceSen => asInt(json['price_sen']);
  int? get salePriceSen => asInt(json['sale_price_sen']);
  int get minPriceSen => asInt(json['min_price_sen']) ?? 0;
  int get stockTotal => asInt(json['stock_total']) ?? 0;
  int get variantsCount => asInt(json['variants_count']) ?? variants.length;
  bool get codEnabled => asBool(json['cod_enabled']);
  List<SellerVariant> get variants => asMapList(json['variants']).map(SellerVariant.new).toList();
  List<SellerImage> get images => asMapList(json['images']).map(SellerImage.new).toList();
  int? get weightGrams => asInt(json['weight_grams']);
  int? get lengthMm => asInt(json['length_mm']);
  int? get widthMm => asInt(json['width_mm']);
  int? get heightMm => asInt(json['height_mm']);
  String? get blockedReason => asStr(json['blocked_reason']);

  Map<String, String> _translations(String key) =>
      asMap(json[key]).map((k, v) => MapEntry(k, asStr(v) ?? ''));
}

class LedgerEntry {
  LedgerEntry(this.json);

  final Map<String, dynamic> json;

  int get id => asInt(json['id']) ?? 0;
  String get type => asStr(json['type']) ?? '';
  String get typeLabel => asStr(json['type_label']) ?? type;
  int get amountSen => asInt(json['amount_sen']) ?? 0;
  String? get reference => asStr(json['reference']);
  String? get createdAt => asStr(json['created_at']);
}

class Payout {
  Payout(this.json);

  final Map<String, dynamic> json;

  int get id => asInt(json['id']) ?? 0;
  String get payoutNo => asStr(json['payout_no']) ?? '';
  int get amountSen => asInt(json['amount_sen']) ?? 0;
  String get status => asStr(json['status']) ?? '';
  String get statusLabel => asStr(json['status_label']) ?? status;
  String? get requestedAt => asStr(json['requested_at']);
  String? get paidAt => asStr(json['paid_at']);
}

class Earnings {
  Earnings(this.json);

  final Map<String, dynamic> json;

  int get availableSen => asInt(json['available_sen']) ?? 0;
  int get heldSen => asInt(json['held_sen']) ?? 0;
  int get paidOutSen => asInt(json['paid_out_sen']) ?? 0;
  int get payoutMinSen => asInt(json['payout_min_sen']) ?? 0;
  Payout? get openPayout => json['open_payout'] is Map ? Payout(asMap(json['open_payout'])) : null;
  List<LedgerEntry> get ledger => asMapList(json['ledger']).map(LedgerEntry.new).toList();
  List<Payout> get payouts => asMapList(json['payouts']).map(Payout.new).toList();
}

class SellerStore {
  SellerStore(this.json);

  final Map<String, dynamic> json;

  int get id => asInt(json['id']) ?? 0;
  String get name => asStr(json['name']) ?? '';
  String? get description => asStr(json['description']);
  String? get state => asStr(json['state']);
  String get status => asStr(json['status']) ?? '';
  String get statusLabel => asStr(json['status_label']) ?? status;
  bool get holidayMode => asBool(json['holiday_mode']);
  String? get logo => asStr(json['logo']);
  String? get banner => asStr(json['banner']);
  String get shippingMode => asStr(asMap(json['shipping'])['mode']) ?? 'flat';
  int? get flatFeeSen => asInt(asMap(json['shipping'])['flat_fee_sen']);
  int? get freeAboveSen => asInt(asMap(json['shipping'])['free_above_sen']);
  Map<String, dynamic>? get bank => json['bank'] is Map ? asMap(json['bank']) : null;
  String? get bankName => asStr(bank?['bank_name']);
  String? get bankAccountName => asStr(bank?['account_name']);

  /// Masked by the server to the last four digits (`******8705`).
  String? get bankAccountNoMasked => asStr(bank?['account_no']);
}

class SellerReview {
  SellerReview(this.json);

  final Map<String, dynamic> json;

  int get id => asInt(json['id']) ?? 0;
  int get rating => asInt(json['rating']) ?? 0;
  String? get body => asStr(json['body']);
  String get buyerName => asStr(json['buyer_name']) ?? '';
  String? get productName => asStr(asMap(json['product'])['name']);
  String? get productThumb => asStr(asMap(json['product'])['thumb']);
  String? get createdAt => asStr(json['created_at']);
  String? get replyBody => asStr(asMap(json['reply'])['body']);
  String? get replyAt => asStr(asMap(json['reply'])['at']);
  bool get canReply => asBool(json['can_reply']);
}

class Dashboard {
  Dashboard(this.json);

  final Map<String, dynamic> json;

  int get todaySalesSen => asInt(json['today_sales_sen']) ?? 0;
  int get todayOrders => asInt(json['today_orders']) ?? 0;
  Map<String, int> get ordersByStatus => asMap(json['orders_by_status']).map((k, v) => MapEntry(k, asInt(v) ?? 0));
  int get toShip => asInt(json['to_ship']) ?? 0;
  int get liveProducts => asInt(json['live_products']) ?? 0;
  int get lowStock => asInt(json['low_stock']) ?? 0;
  int get availableSen => asInt(json['available_sen']) ?? 0;
  int get heldSen => asInt(json['held_sen']) ?? 0;
  List<SellerOrder> get recentOrders => asMapList(json['recent_orders']).map(SellerOrder.new).toList();
}

/// Sub-order statuses in queue order (`App\Enums\SubOrderStatus`).
const kSubOrderStatuses = [
  'pending_payment', 'confirmed', 'processing', 'shipped', 'delivered',
  'completed', 'cancelled', 'return_requested', 'returned', 'refunded',
];

/// Product statuses a seller filters by (`App\Enums\ProductStatus`, minus `banned`).
const kProductStatuses = ['draft', 'pending_review', 'live', 'delisted'];

/// `App\Support\MalaysianStates::ALL` — the store-location select.
const kMalaysianStates = [
  'Johor', 'Kedah', 'Kelantan', 'Melaka', 'Negeri Sembilan', 'Pahang', 'Perak', 'Perlis',
  'Pulau Pinang', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu', 'Kuala Lumpur', 'Labuan', 'Putrajaya',
];

/// `App\Support\MalaysianBanks::ALL` — the bank-details select.
const kMalaysianBanks = ['Maybank', 'CIMB', 'Public Bank', 'RHB', 'Bank Islam', 'Hong Leong', 'AmBank'];
