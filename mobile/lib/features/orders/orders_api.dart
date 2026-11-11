import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/api/api_client.dart';
import '../../core/api/json.dart';
import '../../core/offline/offline.dart';
import '../catalog/models.dart';

/// One sub-order (`SubOrderResource`); the detail call adds tracking,
/// history and the address snapshot. Item fields are the order_items
/// snapshot — never the live product (hard rule 5).
class SubOrder {
  SubOrder(this.json);

  final Map<String, dynamic> json;

  int get id => asInt(json['id']) ?? 0;
  String get subOrderNo => asStr(json['sub_order_no']) ?? '';
  String get orderNo => asStr(json['order_no']) ?? '';
  String get status => asStr(json['status']) ?? '';
  String get statusLabel => asStr(json['status_label']) ?? status;
  Map<String, dynamic> get store => asMap(json['store']);
  int get storeId => asInt(store['id']) ?? 0;
  String get storeName => asStr(store['name']) ?? '';
  String? get storeSlug => asStr(store['slug']);
  List<OrderItem> get items => asMapList(json['items']).map(OrderItem.new).toList();
  int get subtotalSen => asInt(json['subtotal_sen']) ?? 0;
  int get shippingSen => asInt(json['shipping_sen']) ?? 0;
  int get discountSen => asInt(json['discount_sen']) ?? 0;
  int get totalSen => asInt(json['total_sen']) ?? 0;
  String get paymentMethod => asStr(asMap(json['payment'])['method']) ?? '';
  String get paymentStatus => asStr(asMap(json['payment'])['status']) ?? '';
  DateTime? get placedAt => asDate(json['placed_at']);

  Map<String, dynamic> get can => asMap(json['can']);
  bool get canCancel => asBool(can['cancel']);
  bool get canConfirmReceived => asBool(can['confirm_received']);
  bool get canReturn => asBool(can['return']);
  bool get canReview => asBool(can['review']);

  /// Unpaid online order: "Pay now" asks the payment endpoint whether it can still be paid.
  bool get canPayNow => status == 'pending_payment' && paymentMethod != 'cod' && paymentStatus == 'pending';
  bool get canBuyAgain => status == 'completed';

  /// `{id, status, reason, created_at}` or null.
  Map<String, dynamic>? get returnRequest => json['return_request'] is Map ? asMap(json['return_request']) : null;

  // ---- detail only --------------------------------------------------------
  /// `{courier, no, url, status}` or null.
  Map<String, dynamic>? get tracking => json['tracking'] is Map ? asMap(json['tracking']) : null;

  /// `[{status, label, at, note}]`, oldest first.
  List<Map<String, dynamic>> get history => asMapList(json['history']);

  /// The `orders.shipping_address` snapshot: recipient_name, phone, line1, line2, postcode, city, state.
  Map<String, dynamic>? get address => json['address'] is Map ? asMap(json['address']) : null;
}

class OrderItem {
  OrderItem(this.json);

  final Map<String, dynamic> json;

  int get id => asInt(json['id']) ?? 0;
  int? get productId => asInt(json['product_id']);

  /// The variant bought; null once it was deleted (Buy again skips it).
  int? get variantId => asInt(json['variant_id']);
  String? get productSlug => asStr(json['product_slug'] ?? json['slug']);
  String get name => asStr(json['name']) ?? '';
  String? get variantLabel => asStr(json['variant_label']);
  int get qty => asInt(json['qty']) ?? 1;
  int get unitPriceSen => asInt(json['unit_price_sen']) ?? 0;
  int get lineTotalSen => asInt(json['line_total_sen']) ?? 0;
  String? get image => asStr(json['product_image'] ?? json['image']);
  bool get reviewed => asBool(json['reviewed']);
}

/// `GET orders/buckets` row.
class OrderBucket {
  OrderBucket(this.json);

  final Map<String, dynamic> json;

  String get key => asStr(json['key']) ?? '';
  String get label => asStr(json['label']) ?? key;
  int get count => asInt(json['count']) ?? 0;
}

class OrdersPage {
  const OrdersPage(this.items, {required this.meta, this.fromCache = false, this.fetchedAt});

  final List<SubOrder> items;
  final PageMeta meta;
  final bool fromCache;
  final DateTime? fetchedAt;
}

/// The buyer's orders and the actions behind `can{}`.
class OrdersApi {
  OrdersApi(this._api, this._repo);

  final ApiClient _api;
  final OfflineRepo _repo;

  Future<List<OrderBucket>> buckets() async => asMapList(await _api.get('orders/buckets')).map(OrderBucket.new).toList();

  /// `GET orders/quick-status`: the web account dashboard's four shortcuts.
  Future<List<OrderBucket>> quickStatus() async => asMapList(await _api.get('orders/quick-status')).map(OrderBucket.new).toList();

  /// `GET orders?bucket=&page=` — page 1 is snapshotted for offline.
  Future<OrdersPage> list({String? bucket, int page = 1}) async {
    final r = await _repo.getJson('orders', query: {'bucket': ?bucket, if (page > 1) 'page': page}, allowStale: page == 1);
    return OrdersPage(asMapList(r.data).map(SubOrder.new).toList(), meta: PageMeta(r.meta), fromCache: r.fromCache, fetchedAt: r.fetchedAt);
  }

  Future<CachedResult<SubOrder>> detail(int id) async {
    final r = await _repo.getJson('orders/$id');
    return CachedResult(SubOrder(asMap(r.data)), fromCache: r.fromCache, fetchedAt: r.fetchedAt);
  }

  /// `POST orders/{id}/cancel {reason?}` → the fresh detail; 409 `invalid_transition`.
  Future<SubOrder> cancel(int id, {String? reason}) async =>
      SubOrder(asMap(await _api.post('orders/$id/cancel', body: {'reason': ?reason})));

  Future<SubOrder> confirmReceived(int id) async => SubOrder(asMap(await _api.post('orders/$id/confirm-received')));

  Future<List<Map<String, dynamic>>> returnReasons() async => asMapList(await _api.get('return-reasons'));

  /// Multipart `POST orders/{id}/return {reason_id, note?, photos[]}`.
  Future<void> submitReturn(int id, {required int reasonId, String? note, List<XFile> photos = const []}) async {
    final form = FormData.fromMap({'reason_id': reasonId, 'note': ?note});
    for (final p in photos) {
      form.files.add(MapEntry('photos[]', MultipartFile.fromBytes(await p.readAsBytes(), filename: p.name)));
    }
    await _api.post('orders/$id/return', body: form);
  }

  /// `POST orders/{id}/review {items[{order_item_id, rating, body?}], seller_rating?, seller_comment?}`.
  Future<SubOrder> submitReview(
    int id, {
    required List<Map<String, dynamic>> items,
    int? sellerRating,
    String? sellerComment,
  }) async =>
      SubOrder(asMap(await _api.post('orders/$id/review', body: {
        'items': items,
        'seller_rating': ?sellerRating,
        'seller_comment': ?sellerComment,
      })));
}

final ordersApiProvider = Provider<OrdersApi>((ref) => OrdersApi(ref.watch(apiClientProvider), ref.watch(offlineRepoProvider)));

final orderBucketsProvider = FutureProvider<List<OrderBucket>>((ref) => ref.watch(ordersApiProvider).buckets());

final orderQuickStatusProvider = FutureProvider<List<OrderBucket>>((ref) => ref.watch(ordersApiProvider).quickStatus());

final orderDetailProvider =
    FutureProvider.family<CachedResult<SubOrder>, int>((ref, id) => ref.watch(ordersApiProvider).detail(id));

final returnReasonsProvider =
    FutureProvider<List<Map<String, dynamic>>>((ref) => ref.watch(ordersApiProvider).returnReasons());
