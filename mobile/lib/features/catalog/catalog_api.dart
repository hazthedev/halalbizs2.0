import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/api/json.dart';
import '../../core/offline/offline.dart';
import 'models.dart';

/// A page of products plus where it came from.
class ProductPage {
  const ProductPage(this.items, {required this.meta, this.fromCache = false, this.fetchedAt});

  final List<Product> items;
  final PageMeta meta;
  final bool fromCache;
  final DateTime? fetchedAt;

  static ProductPage from(CachedResult<dynamic> r) => ProductPage(
        asMapList(r.data).map(Product.new).toList(),
        meta: PageMeta(r.meta),
        fromCache: r.fromCache,
        fetchedAt: r.fetchedAt,
      );
}

/// Public catalogue reads (`/api/v1`, no auth). Every read goes through
/// [OfflineRepo] so the last response renders offline. Filters follow the
/// mobile plan §4 (`category`, `sort`, `min_sen`, `max_sen`, `store`); the
/// controller ignores the ones it does not support yet.
class CatalogApi {
  CatalogApi(this._repo, this._api);

  final OfflineRepo _repo;
  final ApiClient _api;

  Future<ProductPage> products({String? sort, String? category, String? store, int page = 1}) async {
    final r = await _repo.getJson('products', query: {
      'sort': ?sort,
      'category': ?category,
      'store': ?store,
      if (page > 1) 'page': page,
    });
    return ProductPage.from(r);
  }

  Future<CachedResult<Product>> product(String slug) async {
    final r = await _repo.getJson('products/$slug');
    return CachedResult(Product(asMap(r.data)), fromCache: r.fromCache, fetchedAt: r.fetchedAt);
  }

  Future<CachedResult<List<Category>>> categories() async {
    final r = await _repo.getJson('categories');
    return CachedResult(asMapList(r.data).map(Category.new).toList(), fromCache: r.fromCache, fetchedAt: r.fetchedAt);
  }

  /// Search is online-only (no snapshot): results change with every keystroke.
  Future<ProductPage> search(String q, {String? category, String? sort}) async {
    Map<String, dynamic>? meta;
    final data = await _api.get('search', query: {
      'q': q,
      'category': ?category,
      'sort': ?sort,
    }, meta: (m) => meta = m);
    return ProductPage(asMapList(data).map(Product.new).toList(), meta: PageMeta(meta));
  }

  /// `GET stores/{slug}` (slice 2). Until it exists the header is built from
  /// the slug alone (name filled from the first product's `store`).
  Future<CachedResult<Store>> store(String slug) async {
    try {
      final r = await _repo.getJson('stores/$slug');
      return CachedResult(Store(asMap(r.data)), fromCache: r.fromCache, fetchedAt: r.fetchedAt);
    } on ApiException catch (e) {
      if (!e.isNotFound) rethrow;
      return CachedResult(Store({'slug': slug}));
    }
  }

  /// `GET stores/{slug}/products` (slice 2), falling back to
  /// `GET products?store={slug}` while the route is missing.
  Future<ProductPage> storeProducts(String slug, {int page = 1}) async {
    try {
      final r = await _repo.getJson('stores/$slug/products', query: {if (page > 1) 'page': page});
      return ProductPage.from(r);
    } on ApiException catch (e) {
      if (!e.isNotFound) rethrow;
      return products(store: slug, page: page);
    }
  }
}

final catalogApiProvider =
    Provider<CatalogApi>((ref) => CatalogApi(ref.watch(offlineRepoProvider), ref.watch(apiClientProvider)));

/// Home: featured products (`sort=popular`) — `GET home` replaces this in slice 2.
final featuredProductsProvider =
    FutureProvider<ProductPage>((ref) => ref.watch(catalogApiProvider).products(sort: 'popular'));

final categoriesProvider =
    FutureProvider<CachedResult<List<Category>>>((ref) => ref.watch(catalogApiProvider).categories());

final productProvider = FutureProvider.family<CachedResult<Product>, String>(
    (ref, slug) => ref.watch(catalogApiProvider).product(slug));

final categoryProductsProvider = FutureProvider.family<ProductPage, String>(
    (ref, slug) => ref.watch(catalogApiProvider).products(category: slug));

final storeProvider =
    FutureProvider.family<CachedResult<Store>, String>((ref, slug) => ref.watch(catalogApiProvider).store(slug));

final storeProductsProvider =
    FutureProvider.family<ProductPage, String>((ref, slug) => ref.watch(catalogApiProvider).storeProducts(slug));
