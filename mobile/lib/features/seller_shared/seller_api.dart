import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/api/api_client.dart';
import '../../core/api/json.dart';
import '../../core/offline/offline.dart';
import 'models.dart';

/// `/api/v1/seller/*` (PR #176). Reads go through [OfflineRepo] so the last
/// response renders offline; writes hit the API directly and return the
/// server's fresh resource. Service refusals arrive as 409 `ApiException`s
/// (`invalid_transition`, `halal_blocked`, `payout_rejected`, `reply_locked`).
class SellerApi {
  SellerApi(this._repo, this._api);

  final OfflineRepo _repo;
  final ApiClient _api;

  Future<CachedResult<T>> _one<T>(String path, T Function(Map<String, dynamic>) make) async {
    final r = await _repo.getJson(path);
    return CachedResult(make(asMap(r.data)), fromCache: r.fromCache, fetchedAt: r.fetchedAt);
  }

  Future<SellerPage<T>> _page<T>(String path, Map<String, dynamic> query, T Function(Map<String, dynamic>) make) async {
    final r = await _repo.getJson(path, query: query);
    return SellerPage(asMapList(r.data).map(make).toList(), meta: r.meta, fromCache: r.fromCache, fetchedAt: r.fetchedAt);
  }

  // Dashboard
  Future<CachedResult<Dashboard>> dashboard() => _one('seller/dashboard', Dashboard.new);

  // Orders
  Future<SellerPage<SellerOrder>> orders({String? status, int page = 1}) =>
      _page('seller/orders', {'status': ?status, if (page > 1) 'page': page}, SellerOrder.new);

  Future<CachedResult<SellerOrder>> order(int id) => _one('seller/orders/$id', SellerOrder.new);

  Future<List<String>> couriers() async => asList(await _api.get('seller/couriers')).map((c) => '$c').toList();

  /// `pack`, `ship`, `delivered`, `return/accept`, `return/dispute`, `return/received`.
  Future<SellerOrder> orderAction(int id, String action, {Map<String, dynamic>? body}) async =>
      SellerOrder(asMap(await _api.post('seller/orders/$id/$action', body: body)));

  // Products
  Future<SellerPage<SellerProduct>> products({String? status, String? q, int page = 1}) => _page(
        'seller/products',
        {'status': ?status, if (q != null && q.isNotEmpty) 'q': q, if (page > 1) 'page': page},
        SellerProduct.new,
      );

  Future<CachedResult<SellerProduct>> product(int id) => _one('seller/products/$id', SellerProduct.new);

  Future<SellerProduct> createProduct(Map<String, dynamic> body) async =>
      SellerProduct(asMap(await _api.post('seller/products', body: body)));

  Future<SellerProduct> updateProduct(int id, Map<String, dynamic> body) async =>
      SellerProduct(asMap(await _api.patch('seller/products/$id', body: body)));

  /// `publish`, `delist`, `relist`.
  Future<SellerProduct> productAction(int id, String action) async =>
      SellerProduct(asMap(await _api.post('seller/products/$id/$action')));

  Future<SellerProduct> uploadImages(int id, List<XFile> files) async {
    final form = FormData();
    for (final f in files) {
      form.files.add(MapEntry('images[]', MultipartFile.fromBytes(await f.readAsBytes(), filename: f.name)));
    }
    return SellerProduct(asMap(await _api.post('seller/products/$id/images', body: form)));
  }

  Future<void> deleteImage(int id, int mediaId) => _api.delete('seller/products/$id/images/$mediaId');

  // Earnings
  Future<CachedResult<Earnings>> earnings({int page = 1}) async {
    final r = await _repo.getJson('seller/earnings', query: {if (page > 1) 'page': page});
    return CachedResult(Earnings(asMap(r.data)), fromCache: r.fromCache, fetchedAt: r.fetchedAt, meta: r.meta);
  }

  Future<Payout> requestPayout(int amountSen) async =>
      Payout(asMap(await _api.post('seller/payouts', body: {'amount_sen': amountSen})));

  // Store
  Future<CachedResult<SellerStore>> store() => _one('seller/store', SellerStore.new);

  Future<SellerStore> updateStore(Map<String, dynamic> body) async =>
      SellerStore(asMap(await _api.patch('seller/store', body: body)));

  /// [kind] is `logo` or `banner`; the server replaces the previous file.
  Future<SellerStore> uploadStoreImage(String kind, XFile file) async {
    final form = FormData.fromMap({kind: MultipartFile.fromBytes(await file.readAsBytes(), filename: file.name)});
    return SellerStore(asMap(await _api.post('seller/store/$kind', body: form)));
  }

  // Reviews
  Future<SellerPage<SellerReview>> reviews({int page = 1}) =>
      _page('seller/reviews', {if (page > 1) 'page': page}, SellerReview.new);

  Future<SellerReview> reply(int id, String body) async =>
      SellerReview(asMap(await _api.post('seller/reviews/$id/reply', body: {'body': body})));
}

final sellerApiProvider =
    Provider<SellerApi>((ref) => SellerApi(ref.watch(offlineRepoProvider), ref.watch(apiClientProvider)));

final sellerDashboardProvider =
    FutureProvider<CachedResult<Dashboard>>((ref) => ref.watch(sellerApiProvider).dashboard());

final sellerOrderProvider =
    FutureProvider.family<CachedResult<SellerOrder>, int>((ref, id) => ref.watch(sellerApiProvider).order(id));

final sellerCouriersProvider = FutureProvider<List<String>>((ref) => ref.watch(sellerApiProvider).couriers());

final sellerEarningsProvider =
    FutureProvider<CachedResult<Earnings>>((ref) => ref.watch(sellerApiProvider).earnings());
