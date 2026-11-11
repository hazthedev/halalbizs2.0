import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/api/json.dart';
import '../../core/auth/session.dart';
import '../../core/db/app_db.dart';
import '../../core/offline/offline.dart';

/// `GET cart` payload (CartController::payload): groups by store, counts,
/// the selected subtotal and the purchasing flag. Every write returns the
/// same shape, so the controller just swaps it in.
class Cart {
  Cart(this.json, {this.fromCache = false, this.fetchedAt});

  static final empty = Cart(const {'groups': [], 'item_count': 0, 'selected_count': 0, 'selected_subtotal_sen': 0, 'purchasing_enabled': true});

  final Map<String, dynamic> json;
  final bool fromCache;
  final DateTime? fetchedAt;

  List<CartGroup> get groups => asMapList(json['groups']).map(CartGroup.new).toList();
  int get itemCount => asInt(json['item_count']) ?? 0;
  int get selectedCount => asInt(json['selected_count']) ?? 0;
  int get selectedSubtotalSen => asInt(json['selected_subtotal_sen']) ?? 0;
  bool get purchasingEnabled => json.containsKey('purchasing_enabled') ? asBool(json['purchasing_enabled']) : true;
  bool get isEmpty => groups.isEmpty;

  /// Every available line in every store is selected.
  bool get allSelected {
    final lines = groups.expand((g) => g.lines).where((l) => l.available);
    return lines.isNotEmpty && lines.every((l) => l.selected);
  }
}

class CartGroup {
  CartGroup(this.json);

  final Map<String, dynamic> json;

  Map<String, dynamic> get store => asMap(json['store']);
  int get storeId => asInt(store['id']) ?? 0;
  String get storeName => asStr(store['name']) ?? '';
  String? get storeSlug => asStr(store['slug']);
  String? get storeLogo => asStr(store['logo']);
  List<CartLine> get lines => asMapList(json['lines']).map(CartLine.new).toList();
  int get subtotalSen => asInt(json['subtotal_sen']) ?? 0;

  bool get allSelected {
    final available = lines.where((l) => l.available);
    return available.isNotEmpty && available.every((l) => l.selected);
  }
}

class CartLine {
  CartLine(this.json);

  final Map<String, dynamic> json;

  int get variantId => asInt(json['variant_id']) ?? 0;
  Map<String, dynamic> get product => asMap(json['product']);
  int get productId => asInt(product['id']) ?? 0;
  String? get productSlug => asStr(product['slug']);
  String get name => asStr(product['name']) ?? '';
  String? get thumb => asStr(product['thumb']);

  /// Set only when the product's certificate is verified (web basket line).
  String? get halalCertNumber => asStr(product['halal_cert_number']);
  String? get variantLabel => asStr(json['variant_label']);
  int get qty => asInt(json['qty']) ?? 1;
  int get unitPriceSen => asInt(json['unit_price_sen']) ?? 0;
  int get listPriceSen => asInt(json['list_price_sen']) ?? unitPriceSen;
  bool get onSale => listPriceSen > unitPriceSen;
  int get stock => asInt(json['stock']) ?? 0;
  bool get selected => asBool(json['selected']);
  bool get available => json.containsKey('available') ? asBool(json['available']) : true;
  bool get codEnabled => asBool(json['cod_enabled']);
}

/// The signed-in buyer's cart. Reads go through [OfflineRepo] (a snapshot
/// renders offline, writes disabled); every write (`POST cart/items`,
/// `PATCH/DELETE cart/items/{variant}`, `POST cart/select-all`) answers with
/// the full cart, which replaces the state and the offline snapshot.
class CartController extends AsyncNotifier<Cart> {
  static const _path = 'cart';

  @override
  Future<Cart> build() async {
    final userId = ref.watch(sessionProvider.select((s) => s is SignedIn ? s.profile.id : null));
    if (userId == null) return Cart.empty;
    final r = await ref.read(offlineRepoProvider).getJson(_path);
    return Cart(asMap(r.data), fromCache: r.fromCache, fetchedAt: r.fetchedAt);
  }

  ApiClient get _api => ref.read(apiClientProvider);

  /// Re-reads `GET cart`; the returned cart is the server's, never a stale snapshot.
  Future<Cart> refresh() {
    ref.invalidateSelf();
    return future;
  }

  /// `POST cart/items {variant_id, qty}`.
  Future<void> add({required int variantId, required int qty}) =>
      _apply(_api.post('cart/items', body: {'variant_id': variantId, 'qty': qty}));

  Future<void> setQty(int variantId, int qty) => _apply(_api.patch('cart/items/$variantId', body: {'qty': qty}));

  Future<void> setSelected(int variantId, bool selected) =>
      _apply(_api.patch('cart/items/$variantId', body: {'selected': selected}));

  Future<void> remove(int variantId) => _apply(_api.delete('cart/items/$variantId'));

  /// All lines, or one store's when [storeId] is given.
  Future<void> selectAll(bool selected, {int? storeId}) =>
      _apply(_api.post('cart/select-all', body: {'selected': selected, 'store_id': ?storeId}));

  Future<void> _apply(Future<dynamic> call) async {
    final data = asMap(await call);
    state = AsyncData(Cart(data));
    try {
      await ref.read(appDbProvider).putResponse(_path, jsonEncode(data), null);
    } catch (_) {
      // Offline snapshot only.
    }
  }
}

final cartProvider = AsyncNotifierProvider<CartController, Cart>(CartController.new);

/// Badge on the Cart tab. 0 while signed out, loading or failed.
final cartCountProvider = Provider<int>((ref) => ref.watch(cartProvider).value?.itemCount ?? 0);
