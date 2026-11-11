import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/api/json.dart';
import '../../core/auth/session.dart';
import '../../core/offline/offline.dart';
import 'models.dart';
import 'paged.dart';

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

  PageOf<Product> get paged => (items: items, meta: meta, fromCache: fromCache, fetchedAt: fetchedAt);
}

/// `GET home`: the home tab in one read (HomeController).
class HomeFeed {
  HomeFeed(Map<String, dynamic> json, {this.fromCache = false, this.fetchedAt})
      : featured = asMapList(json['featured']).map(Product.new).toList(),
        categories = asMapList(json['categories']).map(Category.new).toList(),
        banners = asMapList(json['banners']).map(HomeBanner.new).where((b) => b.image != null).toList(),
        flashEndsAt = asDate(asMap(json['flash_sale'])['ends_at']),
        flashItems = asMapList(asMap(json['flash_sale'])['products']).map(FlashItem.new).toList();

  final List<Product> featured;
  final List<Category> categories;
  final List<HomeBanner> banners;

  /// Null when no sale is live (`flash_sale: null`).
  final DateTime? flashEndsAt;
  final List<FlashItem> flashItems;
  final bool fromCache;
  final DateTime? fetchedAt;
}

/// Public catalogue reads (`/api/v1`, no auth). Every read goes through
/// [OfflineRepo] so the last response renders offline. Filters follow the
/// mobile plan §4 (`category`, `sort`, `min_sen`, `max_sen`, `store`); the
/// controller ignores the ones it does not support yet.
class CatalogApi {
  CatalogApi(this._repo, this._api);

  final OfflineRepo _repo;
  final ApiClient _api;

  Future<ProductPage> products(
      {String? sort, String? category, String? store, ProductFilter filter = const ProductFilter(), int page = 1}) async {
    final r = await _repo.getJson('products', query: {
      ...filter.query,
      'sort': ?sort,
      'category': ?category,
      'store': ?store,
      if (page > 1) 'page': page,
    });
    return ProductPage.from(r);
  }

  Future<HomeFeed> home() async {
    final r = await _repo.getJson('home');
    return HomeFeed(asMap(r.data), fromCache: r.fromCache, fetchedAt: r.fetchedAt);
  }

  Future<CachedResult<Product>> product(String slug) async {
    final r = await _repo.getJson('products/${Uri.encodeComponent(slug)}');
    return CachedResult(Product(asMap(r.data)), fromCache: r.fromCache, fetchedAt: r.fetchedAt);
  }

  Future<CachedResult<List<Category>>> categories() async {
    final r = await _repo.getJson('categories');
    return CachedResult(asMapList(r.data).map(Category.new).toList(), fromCache: r.fromCache, fetchedAt: r.fetchedAt);
  }

  /// Search is online-only (no snapshot): results change with every keystroke.
  /// The API answers every hit at once today (no paging meta), so "more" never shows.
  Future<ProductPage> search(String q, {String? category, ProductFilter filter = const ProductFilter(), int page = 1}) async {
    Map<String, dynamic>? meta;
    final data = await _api.get('search', query: {
      'q': q,
      'category': ?category,
      ...filter.query,
      if (page > 1) 'page': page,
    }, meta: (m) => meta = m);
    return ProductPage(asMapList(data).map(Product.new).toList(), meta: PageMeta(meta));
  }

  /// `GET products/{slug}/related`: the web "Verified alongside it" strip (same
  /// category, newest first, 6). Online only: it follows the live catalogue.
  Future<List<Product>> related(String slug) async =>
      asMapList(await _api.get('products/${Uri.encodeComponent(slug)}/related')).map(Product.new).toList();

  /// `GET recommended`: the web Home "Recommended for you" strip (12). Signed in =
  /// the buyer's own signals; never snapshotted, so a cached feed cannot cross accounts.
  Future<List<Product>> recommended() async => asMapList(await _api.get('recommended')).map(Product.new).toList();

  /// `GET products/{slug}/reviews`: visible reviews, newest first, 10 a page.
  Future<PageOf<Review>> reviews(String slug, {int page = 1}) async {
    final r = await _repo.getJson('products/${Uri.encodeComponent(slug)}/reviews', query: {if (page > 1) 'page': page});
    return (items: asMapList(r.data).map(Review.new).toList(), meta: PageMeta(r.meta), fromCache: r.fromCache, fetchedAt: r.fetchedAt);
  }

  /// `GET products/{slug}/questions`: visible questions, newest first, 10 a page.
  Future<PageOf<Question>> questions(String slug, {int page = 1}) async {
    final r = await _repo.getJson('products/${Uri.encodeComponent(slug)}/questions', query: {if (page > 1) 'page': page});
    return (items: asMapList(r.data).map(Question.new).toList(), meta: PageMeta(r.meta), fromCache: r.fromCache, fetchedAt: r.fetchedAt);
  }

  /// `POST products/{slug}/questions` (signed in): 10-500 chars, 3 a minute.
  Future<Question> ask(String slug, String question) async =>
      Question(asMap(await _api.post('products/${Uri.encodeComponent(slug)}/questions', body: {'question': question})));

  /// `GET stores/{slug}` (slice 2). Until it exists the header is built from
  /// the slug alone (name filled from the first product's `store`).
  Future<CachedResult<Store>> store(String slug) async {
    try {
      final r = await _repo.getJson('stores/${Uri.encodeComponent(slug)}');
      return CachedResult(Store(asMap(r.data)), fromCache: r.fromCache, fetchedAt: r.fetchedAt);
    } on ApiException catch (e) {
      if (!e.isNotFound) rethrow;
      return CachedResult(Store({'slug': slug}));
    }
  }

  /// `GET stores/{slug}/products` (sort + page only). A price range or halal
  /// facet goes through `GET products?store=` instead, which takes every
  /// filter; a missing store route falls back the same way.
  Future<ProductPage> storeProducts(String slug, {ProductFilter filter = const ProductFilter(), int page = 1}) async {
    if (filter.narrows) return products(store: slug, filter: filter, page: page);
    try {
      final r = await _repo.getJson('stores/${Uri.encodeComponent(slug)}/products',
          query: {'sort': ?filter.sort, if (page > 1) 'page': page});
      return ProductPage.from(r);
    } on ApiException catch (e) {
      if (!e.isNotFound) rethrow;
      return products(store: slug, filter: filter, page: page);
    }
  }
}

final catalogApiProvider =
    Provider<CatalogApi>((ref) => CatalogApi(ref.watch(offlineRepoProvider), ref.watch(apiClientProvider)));

/// Home tab: banners, flash sale, categories and featured from `GET home`.
final homeProvider = FutureProvider<HomeFeed>((ref) => ref.watch(catalogApiProvider).home());

/// `/featured` ("View all"): the paged `sort=top` list (`GET home` sends one
/// page only; the API accepts `latest|top|price_asc|price_desc`).
final featuredProductsProvider =
    FutureProvider<ProductPage>((ref) => ref.watch(catalogApiProvider).products(sort: 'top'));

final categoriesProvider =
    FutureProvider<CachedResult<List<Category>>>((ref) => ref.watch(catalogApiProvider).categories());

final productProvider = FutureProvider.family<CachedResult<Product>, String>(
    (ref, slug) => ref.watch(catalogApiProvider).product(slug));

final storeProvider =
    FutureProvider.family<CachedResult<Store>, String>((ref, slug) => ref.watch(catalogApiProvider).store(slug));

final productReviewsProvider = FutureProvider.family<PageOf<Review>, String>(
    (ref, slug) => ref.watch(catalogApiProvider).reviews(slug));

final relatedProvider =
    FutureProvider.family<List<Product>, String>((ref, slug) => ref.watch(catalogApiProvider).related(slug));

/// Re-reads when the signed-in account changes (the strip is personal).
final recommendedProvider = FutureProvider<List<Product>>((ref) {
  ref.watch(profileProvider.select((p) => p?.id));
  return ref.watch(catalogApiProvider).recommended();
});
