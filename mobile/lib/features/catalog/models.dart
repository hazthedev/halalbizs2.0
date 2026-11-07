import '../../core/api/json.dart';

/// `ProductResource` (app/Http/Resources/ProductResource.php): id, slug,
/// name, description, min_price_sen, sold_count, rating_avg, image,
/// store{name,slug}, variants[]. Slice 2 adds `images[]`, `cod_enabled` and
/// a reviews summary; they are read here when present.
class Product {
  Product(this.json);

  final Map<String, dynamic> json;

  int get id => asInt(json['id']) ?? 0;
  String get slug => asStr(json['slug']) ?? '';
  String get name => asStr(json['name']) ?? '';
  String get description => asStr(json['description']) ?? '';
  int get minPriceSen => asInt(json['min_price_sen']) ?? 0;
  int get soldCount => asInt(json['sold_count']) ?? 0;
  double get ratingAvg => asDouble(json['rating_avg']) ?? 0;
  int get reviewCount => asInt(json['review_count'] ?? asMap(json['reviews'])['count']) ?? 0;
  String? get image => asStr(json['image']);

  /// Gallery: `images[]` when the API sends it, else the single `image`.
  List<String> get images {
    final list = asList(json['images']).map((i) => i is Map ? asStr(i['url'] ?? i['card']) : asStr(i)).nonNulls.toList();
    if (list.isNotEmpty) return list;
    return image == null ? const [] : [image!];
  }

  bool get codEnabled => asBool(json['cod_enabled']);
  String? get storeName => asStr(asMap(json['store'])['name']);
  String? get storeSlug => asStr(asMap(json['store'])['slug']);
  List<Variant> get variants => asMapList(json['variants']).map(Variant.new).toList();
  bool get inStock => variants.isEmpty ? true : variants.any((v) => v.stock > 0);
}

/// `variants[{id, sku, options_label, price_sen, stock}]`; `price_sen` is the
/// EFFECTIVE price (sale applied server-side). When the API also sends the
/// original `price_sen` as `regular_price_sen` / a lower `sale_price_sen`,
/// the strike-through shows.
class Variant {
  Variant(this.json);

  final Map<String, dynamic> json;

  int get id => asInt(json['id']) ?? 0;
  String get sku => asStr(json['sku']) ?? '';
  String get label => asStr(json['options_label'] ?? json['name']) ?? 'Default';
  int get priceSen => asInt(json['sale_price_sen']) ?? asInt(json['price_sen']) ?? 0;

  /// Original price when on sale, else null.
  int? get regularPriceSen {
    final sale = asInt(json['sale_price_sen']);
    final regular = asInt(json['regular_price_sen']) ?? asInt(json['price_sen']);
    if (sale != null && regular != null && sale < regular) return regular;
    return null;
  }

  int get stock => asInt(json['stock']) ?? 0;
  bool get inStock => stock > 0;
}

/// `CategoryResource`: id, slug, name, parent_id.
class Category {
  Category(this.json);

  final Map<String, dynamic> json;

  int get id => asInt(json['id']) ?? 0;
  String get slug => asStr(json['slug']) ?? '';
  String get name => asStr(json['name']) ?? '';
  int? get parentId => asInt(json['parent_id']);
}

/// `GET stores/{slug}` (slice 2): name, slug, description, logo, banner,
/// product_count, rating_avg. Built from a product's `store{name,slug}` when
/// the endpoint is not there yet.
class Store {
  Store(this.json);

  final Map<String, dynamic> json;

  String get slug => asStr(json['slug']) ?? '';
  String get name => asStr(json['name']) ?? slug;
  String? get description => asStr(json['description']);
  String? get logo => asStr(json['logo']);
  String? get banner => asStr(json['banner']);
  int? get productCount => asInt(json['product_count']);
  double? get ratingAvg => asDouble(json['rating_avg']);
}

/// Pagination from the envelope `meta` (Laravel paginator keys).
class PageMeta {
  PageMeta(Map<String, dynamic>? m)
      : page = asInt(m?['current_page']) ?? 1,
        lastPage = asInt(m?['last_page']) ?? 1,
        total = asInt(m?['total']) ?? 0;

  final int page;
  final int lastPage;
  final int total;
  bool get hasMore => page < lastPage;
}
