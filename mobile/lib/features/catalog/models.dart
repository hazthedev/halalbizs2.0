import 'package:collection/collection.dart';
import 'package:html/parser.dart' as html_parser;

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

  /// The product's store has a session live now (`store_live`); [liveSlug] is its room.
  bool get storeLive => asBool(json['store_live']) && liveSlug != null;
  String? get liveSlug => asStr(json['live_slug']);

  /// Gallery: `images[{large, card}]` (detail only) in the seller's order,
  /// else the single `image`.
  List<String> get images => _gallery('card');

  /// Full-size gallery URLs for the zoom view (same order as [images]).
  List<String> get largeImages => _gallery('large');

  List<String> _gallery(String size) {
    final list = asList(json['images']).map((i) => i is Map ? asStr(i[size] ?? i['card'] ?? i['url']) : asStr(i)).nonNulls.toList();
    if (list.isNotEmpty) return list;
    return image == null ? const [] : [image!];
  }

  /// Picker rows (`options[{id, name, values[{id, value}]}]`, detail only).
  /// Without option data but with several variants, one row of the
  /// variants themselves (value id = variant id).
  List<OptionGroup> get optionGroups {
    final groups = asMapList(json['options']).map(OptionGroup.new).where((g) => g.values.isNotEmpty).toList();
    if (groups.isNotEmpty || variants.length < 2) return groups;
    return [
      OptionGroup({
        'id': 0,
        'name': '',
        'values': [for (final v in variants) {'id': v.id, 'value': v.label}],
      }),
    ];
  }

  /// The variant matching one pick per row ([optionGroups] id → value id),
  /// or null while a row is unpicked. A single variant needs no pick.
  Variant? variantFor(Map<int, int> picks) {
    final groups = optionGroups;
    if (variants.length == 1) return variants.first;
    if (groups.isEmpty || picks.length < groups.length) return null;
    return variants.firstWhereOrNull((v) => picks.values.every(_valueIds(v, groups).contains));
  }

  /// Web ProductDetail::availabilityMap: a value is pickable when some
  /// in-stock variant carries it together with the other rows' picks.
  bool isAvailable(Map<int, int> picks, int groupId, int valueId) {
    final groups = optionGroups;
    final wanted = {...picks, groupId: valueId}.values;
    return variants.any((v) => v.inStock && wanted.every(_valueIds(v, groups).contains));
  }

  static List<int> _valueIds(Variant v, List<OptionGroup> groups) =>
      groups.length == 1 && groups.first.id == 0 ? [v.id] : v.optionValueIds;

  int get maxPriceSen => variants.isEmpty ? minPriceSen : variants.map((v) => v.priceSen).max;
  int get lowestPriceSen => variants.isEmpty ? minPriceSen : variants.map((v) => v.priceSen).min;

  bool get codEnabled => asBool(json['cod_enabled']);

  /// Detail only: the web's COD badge rule (purchasing on, product flag, platform switch).
  bool get codAvailable => asBool(json['cod_available']);

  /// Detail only: the shipping row's "Ships from :state".
  String? get shipsFrom => asStr(json['ships_from']);

  /// Specifications tab rows (detail only): brand, condition, weight, category.
  String? get specBrand => asStr(asMap(json['specs'])['brand']);
  String? get specCondition => asStr(asMap(json['specs'])['condition']);
  int? get specWeightGrams => asInt(asMap(json['specs'])['weight_grams']);
  String? get specCategory => asStr(asMap(json['specs'])['category']);

  /// Seller card (detail only; list rows carry name + slug only).
  Map<String, dynamic> get _store => asMap(json['store']);
  bool get storeVerified => asBool(_store['verified']);
  String? get storeLogo => asStr(_store['logo']);
  double get storeRatingAvg => asDouble(_store['rating_avg']) ?? 0;
  int get storeRatingCount => asInt(_store['rating_count']) ?? 0;
  double get storeServiceAvg => asDouble(_store['service_rating_avg']) ?? 0;
  int get storeServiceCount => asInt(_store['service_rating_count']) ?? 0;
  int? get storeProductCount => asInt(_store['product_count']);
  String? get storeState => asStr(_store['state']);
  DateTime? get storeJoinedAt => asDate(_store['joined_at']);

  String? get storeName => asStr(asMap(json['store'])['name']);
  String? get storeSlug => asStr(asMap(json['store'])['slug']);
  List<Variant> get variants => asMapList(json['variants']).map(Variant.new).toList();
  bool get inStock => variants.isEmpty ? true : variants.any((v) => v.stock > 0);

  /// The certificate block (null = no certificate record, show nothing).
  HalalCert? get halal => json['halal'] is Map ? HalalCert(asMap(json['halal'])) : null;

  /// Traceability (detail only): batch, packing date, the printed number.
  String? get halalBatchCode => asStr(json['halal_batch_code']);
  DateTime? get halalPackedOn => asDate(json['halal_packed_on']);
  String? get halalCertNumber => asStr(json['halal_cert_number']);

  /// `metafields[{key, label, rows[{label, value}]}]` (detail only), labels
  /// already translated server-side; the `halal` group is the web's
  /// "Halal certification" box.
  List<MetafieldGroup> get metafields => asMapList(json['metafields']).map(MetafieldGroup.new).toList();
}

/// `ProductResource.halal`. [verdict] is the server's Product::halalVerdict()
/// (`verified|lapsed|pending`) — rendered, never re-derived here.
class HalalCert {
  HalalCert(this.json);

  final Map<String, dynamic> json;

  String get verdict => asStr(json['verdict']) ?? 'unverified';
  bool get verified => verdict == 'verified';
  String get number => asStr(json['number']) ?? '';
  String? get issuingBody => asStr(json['issuing_body']);
  DateTime? get validFrom => asDate(json['valid_from']);
  DateTime? get validTo => asDate(json['valid_to']);

  /// Malaysia-day count from the server; negative once lapsed.
  int get daysRemaining => asInt(json['days_remaining']) ?? 0;
  String? get scopeNote => asStr(json['scope_note']);
  String? get facility => asStr(json['facility']);
  String? get registerUrl => asStr(json['register_url']);
}

/// One metafield group: `{key, label, rows[{label, value}]}`.
class MetafieldGroup {
  MetafieldGroup(this.json);

  final Map<String, dynamic> json;

  String get key => asStr(json['key']) ?? '';
  String get label => asStr(json['label']) ?? '';
  List<({String label, String value})> get rows =>
      [for (final r in asMapList(json['rows'])) (label: asStr(r['label']) ?? '', value: asStr(r['value']) ?? '')];
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

  /// Original price when on sale (`regular_price_sen`, or `price_sen` beside
  /// a `sale_price_sen`), else null.
  int? get regularPriceSen {
    final regular = asInt(json['regular_price_sen']) ?? (json['sale_price_sen'] == null ? null : asInt(json['price_sen']));
    return regular != null && regular > priceSen ? regular : null;
  }

  List<int> get optionValueIds => asList(json['option_value_ids']).map(asInt).nonNulls.toList();

  int get stock => asInt(json['stock']) ?? 0;
  bool get inStock => stock > 0;
}

/// One picker row: `{id, name, values[{id, value}]}`.
class OptionGroup {
  OptionGroup(this.json);

  final Map<String, dynamic> json;

  int get id => asInt(json['id']) ?? 0;
  String get name => asStr(json['name']) ?? '';
  List<({int id, String value})> get values =>
      [for (final v in asMapList(json['values'])) (id: asInt(v['id']) ?? 0, value: asStr(v['value']) ?? '')];
}

/// `CategoryResource`: id, slug, name, parent_id.
class Category {
  Category(this.json);

  final Map<String, dynamic> json;

  int get id => asInt(json['id']) ?? 0;
  String get slug => asStr(json['slug']) ?? '';
  String get name => asStr(json['name']) ?? '';
  int? get parentId => asInt(json['parent_id']);
  String? get image => asStr(json['image']);
}

/// `GET stores/{slug}` (slice 2): name, slug, description, logo, banner,
/// product_count, rating_avg. Built from a product's `store{name,slug}` when
/// the endpoint is not there yet.
class Store {
  Store(this.json);

  final Map<String, dynamic> json;

  int? get id => asInt(json['id']);
  String get slug => asStr(json['slug']) ?? '';
  String get name => asStr(json['name']) ?? slug;

  /// Seller rich-text HTML; render with HtmlWidget, or use [descriptionPlain].
  String? get description => asStr(json['description']);

  /// [description] as one line of plain text for clamped blurbs.
  String? get descriptionPlain {
    final d = description;
    return d == null ? null : htmlToPlain(d);
  }

  String? get logo => asStr(json['logo']);
  String? get banner => asStr(json['banner']);
  int? get productCount => asInt(json['product_count']);
  double? get ratingAvg => asDouble(json['rating_avg']);
  int get followersCount => asInt(json['followers_count']) ?? 0;

  /// Per caller (bearer token); false for guests.
  bool get isFollowing => asBool(json['is_following']);

  /// Live now (`store_live` + `live_slug`): the header shows the LIVE badge.
  bool get storeLive => asBool(json['store_live']) && liveSlug != null;
  String? get liveSlug => asStr(json['live_slug']);

  /// Approval date (else created), as the web store page's "Joined".
  DateTime? get joinedAt => asDate(json['joined_at']);
}

/// `ReviewResource`: id, rating, body, buyer_name (already masked server-side,
/// "Aisyah R."), photos[] (thumb URLs), created_at.
class Review {
  Review(this.json);

  final Map<String, dynamic> json;

  int get id => asInt(json['id']) ?? 0;
  int get rating => (asInt(json['rating']) ?? 0).clamp(0, 5);
  String get body => asStr(json['body']) ?? '';
  String get buyerName => asStr(json['buyer_name']) ?? '';
  List<String> get photos => asList(json['photos']).map(asStr).nonNulls.toList();
  DateTime? get createdAt => asDate(json['created_at']);
  String? get sellerReply => asStr(asMap(json['seller_reply'])['body']);
}

/// `ProductQuestionResource`: question, asker_name (masked), answer (null =
/// awaiting the seller), created_at, answered_at.
class Question {
  Question(this.json);

  final Map<String, dynamic> json;

  int get id => asInt(json['id']) ?? 0;
  String get question => asStr(json['question']) ?? '';
  String get askerName => asStr(json['asker_name']) ?? '';
  String? get answer => asStr(json['answer']);
  DateTime? get createdAt => asDate(json['created_at']);
  DateTime? get answeredAt => asDate(json['answered_at']);
}

/// Pagination from the envelope `meta` (`page` from ApiResponse, or the
/// legacy paginator's `current_page`).
class PageMeta {
  PageMeta(Map<String, dynamic>? m)
      : page = asInt(m?['current_page'] ?? m?['page']) ?? 1,
        lastPage = asInt(m?['last_page']) ?? 1,
        total = asInt(m?['total']) ?? 0;

  final int page;
  final int lastPage;
  final int total;
  bool get hasMore => page < lastPage;
}

/// Tags stripped, entities decoded, whitespace collapsed. Block ends and
/// `<br>` become spaces so "<p>a</p><p>b</p>" reads "a b", not "ab".
String htmlToPlain(String html) => html_parser
    .parseFragment(html.replaceAll(RegExp(r'<br\s*/?>|</(p|div|li|h[1-6])>', caseSensitive: false), ' '))
    .text!
    .replaceAll(RegExp(r'\s+'), ' ')
    .trim();

/// `GET home` → `banners[]` (HomeController): artwork with the copy baked
/// in, so [title] is the screen-reader label. [appUrl] is the go_router
/// route (`AppLinks`), null when the target has no app screen.
class HomeBanner {
  HomeBanner(this.json);

  final Map<String, dynamic> json;

  int get id => asInt(json['id']) ?? 0;
  String get title => asStr(json['title']) ?? '';
  String? get image => asStr(json['image']);
  String? get appUrl => asStr(json['app_url']);
  String? get subtitle => asStr(json['subtitle']);
  String? get ctaLabel => asStr(json['cta_label']);
}

/// `GET home` → `flash_sale.products[]`: a product plus its `flash` block.
/// [originalSen] is the flash variant's own price (what the web strikes).
class FlashItem {
  FlashItem(Map<String, dynamic> json)
      : product = Product(json),
        _flash = asMap(json['flash']);

  final Product product;
  final Map<String, dynamic> _flash;

  int get priceSen => asInt(_flash['price_sen']) ?? 0;
  int? get originalSen {
    final v = product.variants.firstWhereOrNull((v) => v.id == asInt(_flash['variant_id']));
    final sen = v == null ? null : v.regularPriceSen ?? v.priceSen;
    return sen != null && sen > priceSen ? sen : null;
  }
}

/// `GET live/{slug}`: one room. [embedUrl] plays in-app; external platforms
/// (Shopee/TikTok/Lazada) have only [externalUrl].
class LiveRoom {
  LiveRoom(this.json);

  final Map<String, dynamic> json;

  String get slug => asStr(json['slug']) ?? '';
  String get title => asStr(json['title']) ?? '';
  String get storeName => asStr(asMap(json['store'])['name']) ?? '';
  String? get storeSlug => asStr(asMap(json['store'])['slug']);
  String? get platform => asStr(json['platform']);
  String? get embedUrl => asStr(json['embed_url']);
  String? get externalUrl => asStr(json['external_url']);
  bool get isLive => asBool(json['is_live']);
  List<Product> get products => asMapList(json['products']).map(Product.new).toList();
}

/// One row of `GET live`: a session streaming now. Opens the in-app room.
class LiveSession {
  LiveSession(this.json);

  final Map<String, dynamic> json;

  String get slug => asStr(json['slug']) ?? '';
  String get title => asStr(json['title']) ?? '';
  String get storeName => asStr(json['store_name']) ?? '';
  String? get thumbnail => asStr(json['thumbnail']);
}
