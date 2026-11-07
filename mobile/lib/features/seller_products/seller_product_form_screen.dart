import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_client.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import '../catalog/catalog_api.dart';
import '../catalog/models.dart';
import '../seller_shared/models.dart';
import '../seller_shared/rm.dart';
import '../seller_shared/seller_kit.dart';
import '../seller_shared/seller_api.dart';

/// `/seller/products/new` and `/seller/products/:id` — one form. Create
/// posts the single default variant's price/stock/SKU; edit sends the same
/// fields for a single-variant product or `variants[]` for a matrix.
/// Images upload one at a time once the product exists.
class SellerProductFormScreen extends ConsumerStatefulWidget {
  const SellerProductFormScreen({super.key, this.id});

  /// Null = new product.
  final int? id;

  @override
  ConsumerState<SellerProductFormScreen> createState() => _SellerProductFormScreenState();
}

class _VariantEdit {
  _VariantEdit(SellerVariant v)
      : id = v.id,
        label = v.label,
        price = TextEditingController(text: rmInput(v.priceSen)),
        sale = TextEditingController(text: rmInput(v.salePriceSen)),
        stock = TextEditingController(text: '${v.stock}'),
        sku = TextEditingController(text: v.sku ?? '');

  final int id;
  final String label;
  final TextEditingController price;
  final TextEditingController sale;
  final TextEditingController stock;
  final TextEditingController sku;

  void dispose() {
    price.dispose();
    sale.dispose();
    stock.dispose();
    sku.dispose();
  }
}

class _SellerProductFormScreenState extends ConsumerState<SellerProductFormScreen> {
  final _name = {'en': TextEditingController(), 'ms': TextEditingController(), 'vi': TextEditingController()};
  final _desc = {'en': TextEditingController(), 'ms': TextEditingController(), 'vi': TextEditingController()};
  final _price = TextEditingController();
  final _sale = TextEditingController();
  final _stock = TextEditingController();
  final _sku = TextEditingController();
  final _weight = TextEditingController();
  final _length = TextEditingController();
  final _width = TextEditingController();
  final _height = TextEditingController();
  bool _cod = false;
  int? _categoryId;
  List<_VariantEdit> _variants = const [];

  SellerProduct? _product;
  bool _loading = false;
  Object? _loadError;
  String? _busy;
  ApiException? _remote;
  Map<String, String> _local = const {};
  String? _blocked;

  bool get _isNew => widget.id == null;
  bool get _single => _variants.length <= 1;

  @override
  void initState() {
    super.initState();
    if (!_isNew) _load();
  }

  @override
  void dispose() {
    for (final c in [..._name.values, ..._desc.values, _price, _sale, _stock, _sku, _weight, _length, _width, _height]) {
      c.dispose();
    }
    for (final v in _variants) {
      v.dispose();
    }
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _loadError = null;
    });
    try {
      final r = await ref.read(sellerApiProvider).product(widget.id!);
      if (!mounted) return;
      setState(() => _populate(r.data));
    } catch (e) {
      if (mounted) setState(() => _loadError = e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _populate(SellerProduct p) {
    _product = p;
    for (final e in _name.entries) {
      e.value.text = p.nameTranslations[e.key] ?? '';
    }
    for (final e in _desc.entries) {
      e.value.text = p.descriptionTranslations[e.key] ?? '';
    }
    _cod = p.codEnabled;
    _categoryId = p.categoryId;
    final def = p.variants.where((v) => v.isDefault).firstOrNull ?? p.variants.firstOrNull;
    _price.text = rmInput(def?.priceSen ?? p.priceSen);
    _sale.text = rmInput(def?.salePriceSen ?? p.salePriceSen);
    _stock.text = '${def?.stock ?? p.stockTotal}';
    _sku.text = def?.sku ?? '';
    _weight.text = p.weightGrams?.toString() ?? '';
    _length.text = p.lengthMm?.toString() ?? '';
    _width.text = p.widthMm?.toString() ?? '';
    _height.text = p.heightMm?.toString() ?? '';
    for (final v in _variants) {
      v.dispose();
    }
    _variants = p.variants.length > 1 ? p.variants.map(_VariantEdit.new).toList() : const [];
    _blocked = p.blockedReason;
  }

  String? _err(String path) => _local[path] ?? fieldError(_remote, path);

  String? _orNull(TextEditingController c) => c.text.trim().isEmpty ? null : c.text.trim();

  /// Local checks, then the request body — null when a field is wrong.
  Map<String, dynamic>? _validate() {
    final l = context.l10n;
    final local = <String, String>{};
    final body = <String, dynamic>{};

    if (_name['en']!.text.trim().isEmpty) local['name.en'] = l.fieldRequired;
    body['name'] = {for (final e in _name.entries) e.key: _orNull(e.value)};
    body['description'] = {for (final e in _desc.entries) e.key: _orNull(e.value)};
    if (_categoryId != null) {
      body['category_id'] = _categoryId;
    } else if (_isNew) {
      local['category_id'] = l.fieldRequired;
    }
    body['cod_enabled'] = _cod;

    int? rm(TextEditingController c, String path, {bool required = false}) {
      final t = c.text.trim();
      if (t.isEmpty) {
        if (required) local[path] = l.fieldRequired;
        return null;
      }
      final sen = parseRm(t);
      if (sen == null) local[path] = l.sellerPriceFormat;
      return sen;
    }

    int? whole(TextEditingController c, String path, {bool required = false}) {
      try {
        final n = parseWhole(c.text);
        if (n == null && required) local[path] = l.fieldRequired;
        return n;
      } on FormatException {
        local[path] = l.sellerNumberInvalid;
        return null;
      }
    }

    body['weight_grams'] = whole(_weight, 'weight_grams');
    body['length_mm'] = whole(_length, 'length_mm');
    body['width_mm'] = whole(_width, 'width_mm');
    body['height_mm'] = whole(_height, 'height_mm');

    if (_single) {
      final price = rm(_price, 'price_sen', required: _isNew);
      if (price != null) body['price_sen'] = price;
      body['sale_price_sen'] = rm(_sale, 'sale_price_sen');
      final stock = whole(_stock, 'stock', required: _isNew);
      if (stock != null) body['stock'] = stock;
      body['sku'] = _orNull(_sku);
    } else {
      body['variants'] = [
        for (final (i, v) in _variants.indexed)
          {
            'id': v.id,
            'price_sen': ?rm(v.price, 'variants.$i.price_sen', required: true),
            'sale_price_sen': rm(v.sale, 'variants.$i.sale_price_sen'),
            'stock': ?whole(v.stock, 'variants.$i.stock', required: true),
            'sku': _orNull(v.sku),
          },
      ];
    }

    setState(() {
      _local = local;
      _remote = null;
    });
    return local.isEmpty ? body : null;
  }

  void _snack(String message) => ScaffoldMessenger.of(context)
    ..clearSnackBars()
    ..showSnackBar(SnackBar(content: Text(message)));

  /// Handles every write's failure: 422 onto the fields, 409 `halal_blocked`
  /// into the banner, anything else as a snackbar.
  void _fail(Object e) {
    if (e is ApiException && e.isValidation) {
      setState(() => _remote = e);
    } else if (e is ApiException && e.code == 'halal_blocked') {
      setState(() => _blocked = e.message);
    } else {
      showSellerError(context, e);
    }
  }

  Future<bool> _save() async {
    final body = _validate();
    if (body == null) return false;
    setState(() => _busy = 'save');
    final l = context.l10n;
    try {
      final api = ref.read(sellerApiProvider);
      final p = _isNew ? await api.createProduct(body) : await api.updateProduct(_product!.id, body);
      if (!mounted) return false;
      setState(() => _populate(p));
      _snack(l.sellerSaved);
      if (_isNew) context.replace('/seller/products/${p.id}');
      return true;
    } catch (e) {
      if (mounted) _fail(e);
      return false;
    } finally {
      if (mounted) setState(() => _busy = null);
    }
  }

  /// `publish` / `delist` / `relist`; publish saves the form first.
  Future<void> _action(String action) async {
    if (action == 'publish' && !await _save()) return;
    if (!mounted) return;
    setState(() => _busy = action);
    final l = context.l10n;
    try {
      final p = await ref.read(sellerApiProvider).productAction(_product!.id, action);
      if (!mounted) return;
      setState(() {
        _populate(p);
        _blocked = p.blockedReason;
      });
      _snack('${l.sellerSaved} · ${p.statusLabel}');
    } catch (e) {
      if (mounted) _fail(e);
    } finally {
      if (mounted) setState(() => _busy = null);
    }
  }

  Future<void> _addImage() async {
    final file = await pickSellerImage(context);
    if (file == null || !mounted) return;
    setState(() => _busy = 'image');
    try {
      final p = await ref.read(sellerApiProvider).uploadImages(_product!.id, [file]);
      if (mounted) setState(() => _populate(p));
    } catch (e) {
      if (mounted) _fail(e);
    } finally {
      if (mounted) setState(() => _busy = null);
    }
  }

  Future<void> _removeImage(SellerImage image) async {
    final l = context.l10n;
    final ok = await showSoukConfirm(context,
        title: l.sellerRemoveImage, message: l.sellerRemoveImageBody, confirmLabel: l.sellerRemove, cancelLabel: l.cancel, danger: true);
    if (!ok || !mounted) return;
    setState(() => _busy = 'image');
    try {
      final api = ref.read(sellerApiProvider);
      await api.deleteImage(_product!.id, image.id);
      final r = await api.product(_product!.id);
      if (mounted) setState(() => _populate(r.data));
    } catch (e) {
      if (mounted) _fail(e);
    } finally {
      if (mounted) setState(() => _busy = null);
    }
  }

  Future<void> _pickCategory() async {
    final l = context.l10n;
    final List<Category> categories;
    try {
      categories = (await ref.read(categoriesProvider.future)).data;
    } catch (e) {
      if (mounted) showSellerError(context, e);
      return;
    }
    if (!mounted) return;
    final leaves = categories.where((c) => !categories.any((o) => o.parentId == c.id)).toList()
      ..sort((a, b) => _path(categories, a.id).compareTo(_path(categories, b.id)));
    final picked = await showSellerSheet<int>(
      context,
      title: l.sellerChooseCategory,
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        for (final c in leaves)
          SoukRow(
            key: Key('seller-category-${c.id}'),
            label: _path(categories, c.id),
            trailing: c.id == _categoryId ? const Icon(Icons.check_rounded, color: SoukColors.emerald) : const SizedBox.shrink(),
            onTap: () => Navigator.of(context, rootNavigator: true).pop(c.id),
          ),
      ]),
    );
    if (picked != null && mounted) setState(() => _categoryId = picked);
  }

  /// "Groceries & Pantry › Rice & Grains" from the flat category list.
  static String _path(List<Category> all, int id) {
    final names = <String>[];
    int? cur = id;
    while (cur != null && names.length < 6) {
      final c = all.where((c) => c.id == cur).firstOrNull;
      if (c == null) break;
      names.insert(0, c.name);
      cur = c.parentId;
    }
    return names.join(' › ');
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = _product;
    final categories = ref.watch(categoriesProvider).value?.data;
    final categoryLabel = _categoryId == null
        ? null
        : (categories == null ? p?.categoryName : _path(categories, _categoryId!));

    return SoukPage(
      title: _isNew ? l.sellerNewProduct : (p?.name ?? l.sellerEditProduct),
      scrollable: true,
      bottomBar: _loading || _loadError != null ? null : _buttons(l),
      body: _loadError != null
          ? SellerErrorPane(error: _loadError!, onRetry: _load)
          : _loading
              ? const SoukSkeleton.list(rows: 6)
              : Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  if (p != null)
                    Row(children: [
                      SoukBadge(p.statusLabel, key: const Key('seller-product-status'), status: p.status),
                      if (p.codEnabled) ...[const SizedBox(width: SoukSpace.s2), SoukBadge(l.sellerCod, status: 'sale')],
                    ]),
                  if (_blocked != null) ...[
                    const SizedBox(height: SoukSpace.s3),
                    SoukAlert('${l.sellerHalalBlocked}: $_blocked', key: const Key('seller-product-halal-blocked'), status: 'warn'),
                  ],
                  if (_err('images') != null) ...[
                    const SizedBox(height: SoukSpace.s3),
                    SoukAlert(_err('images')!, status: 'danger'),
                  ],
                  const SizedBox(height: SoukSpace.s4),
                  _field('seller-product-name-en', l.sellerNameEn, _name['en']!, path: 'name.en', required: true),
                  _field('seller-product-name-ms', l.sellerNameMs, _name['ms']!, path: 'name.ms'),
                  _field('seller-product-name-vi', l.sellerNameVi, _name['vi']!, path: 'name.vi'),
                  _field('seller-product-desc-en', l.sellerDescriptionEn, _desc['en']!, path: 'description.en', lines: 4),
                  _field('seller-product-desc-ms', l.sellerDescriptionMs, _desc['ms']!, path: 'description.ms', lines: 4),
                  _field('seller-product-desc-vi', l.sellerDescriptionVi, _desc['vi']!, path: 'description.vi', lines: 4),
                  SoukField(
                    label: l.sellerCategory,
                    required: true,
                    error: _err('category_id'),
                    child: InkWell(
                      key: const Key('seller-product-category'),
                      borderRadius: BorderRadius.circular(SoukRadius.control),
                      onTap: _pickCategory,
                      child: InputDecorator(
                        decoration: const InputDecoration(suffixIcon: Icon(Icons.expand_more_rounded, color: SoukColors.inkFaint)),
                        child: Text(
                          categoryLabel ?? l.sellerChooseCategory,
                          style: SoukText.body.copyWith(color: categoryLabel == null ? SoukColors.inkFaint : SoukColors.ink),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: SoukSpace.s4),
                  if (_single) ...[
                    Row(children: [
                      Expanded(child: _field('seller-product-price', l.sellerPriceRm, _price, path: 'price_sen', required: true, decimal: true)),
                      const SizedBox(width: SoukSpace.s3),
                      Expanded(child: _field('seller-product-sale', l.sellerSalePriceRm, _sale, path: 'sale_price_sen', decimal: true)),
                    ]),
                    Row(children: [
                      Expanded(child: _field('seller-product-stock', l.sellerStock, _stock, path: 'stock', required: true, number: true)),
                      const SizedBox(width: SoukSpace.s3),
                      Expanded(child: _field('seller-product-sku', l.sellerSku, _sku, path: 'sku')),
                    ]),
                  ] else ...[
                    Text(l.sellerVariants, style: SoukText.displayH3),
                    const SizedBox(height: SoukSpace.s2),
                    for (final (i, v) in _variants.indexed)
                      Padding(
                        padding: const EdgeInsets.only(bottom: SoukSpace.s3),
                        child: SoukCard(
                          key: Key('seller-variant-${v.id}'),
                          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                            Text(v.label, style: SoukText.displayName),
                            const SizedBox(height: SoukSpace.s3),
                            Row(children: [
                              Expanded(child: _field('seller-variant-${v.id}-price', l.sellerPriceRm, v.price, path: 'variants.$i.price_sen', required: true, decimal: true)),
                              const SizedBox(width: SoukSpace.s3),
                              Expanded(child: _field('seller-variant-${v.id}-sale', l.sellerSalePriceRm, v.sale, path: 'variants.$i.sale_price_sen', decimal: true)),
                            ]),
                            Row(children: [
                              Expanded(child: _field('seller-variant-${v.id}-stock', l.sellerStock, v.stock, path: 'variants.$i.stock', required: true, number: true, last: true)),
                              const SizedBox(width: SoukSpace.s3),
                              Expanded(child: _field('seller-variant-${v.id}-sku', l.sellerSku, v.sku, path: 'variants.$i.sku', last: true)),
                            ]),
                          ]),
                        ),
                      ),
                    const SizedBox(height: SoukSpace.s2),
                  ],
                  _field('seller-product-weight', l.sellerWeightG, _weight, path: 'weight_grams', number: true),
                  Row(children: [
                    Expanded(child: _field('seller-product-length', l.sellerLengthMm, _length, path: 'length_mm', number: true)),
                    const SizedBox(width: SoukSpace.s2),
                    Expanded(child: _field('seller-product-width', l.sellerWidthMm, _width, path: 'width_mm', number: true)),
                    const SizedBox(width: SoukSpace.s2),
                    Expanded(child: _field('seller-product-height', l.sellerHeightMm, _height, path: 'height_mm', number: true)),
                  ]),
                  SwitchListTile.adaptive(
                    key: const Key('seller-product-cod'),
                    contentPadding: EdgeInsets.zero,
                    activeThumbColor: SoukColors.emerald,
                    title: Text(l.sellerCodEnabled, style: SoukText.label),
                    subtitle: Text(l.sellerCodEnabledHelp, style: SoukText.meta),
                    value: _cod,
                    onChanged: (v) => setState(() => _cod = v),
                  ),
                  const SizedBox(height: SoukSpace.s4),
                  Text(l.sellerImages, style: SoukText.displayH3),
                  const SizedBox(height: SoukSpace.s1),
                  Text(p == null ? l.sellerImagesSaveFirst : l.sellerImagesHelp, style: SoukText.meta),
                  const SizedBox(height: SoukSpace.s3),
                  if (p != null) _images(l, p),
                ]),
    );
  }

  Widget _field(
    String key,
    String label,
    TextEditingController c, {
    required String path,
    bool required = false,
    bool decimal = false,
    bool number = false,
    int lines = 1,
    bool last = false,
  }) =>
      Padding(
        padding: EdgeInsets.only(bottom: last ? 0 : SoukSpace.s4),
        child: SoukTextField(
          fieldKey: Key(key),
          label: label,
          controller: c,
          required: required,
          error: _err(path),
          maxLines: lines,
          hint: decimal ? '0.00' : null,
          keyboardType: decimal
              ? const TextInputType.numberWithOptions(decimal: true)
              : number
                  ? TextInputType.number
                  : (lines > 1 ? TextInputType.multiline : null),
          autocorrect: !(decimal || number || path == 'sku'),
        ),
      );

  Widget _images(AppLocalizations l, SellerProduct p) => Wrap(
        key: const Key('seller-product-images'),
        spacing: SoukSpace.s2,
        runSpacing: SoukSpace.s2,
        children: [
          for (final img in p.images)
            SizedBox(
              width: 96,
              height: 96,
              child: Stack(children: [
                Positioned.fill(child: ProductImage(img.thumb, radius: SoukRadius.control)),
                Positioned(
                  top: 2,
                  right: 2,
                  child: Material(
                    color: SoukColors.surface.withValues(alpha: .9),
                    shape: const CircleBorder(),
                    child: IconButton(
                      key: Key('seller-product-image-${img.id}-remove'),
                      tooltip: l.sellerRemoveImage,
                      visualDensity: VisualDensity.compact,
                      icon: const Icon(Icons.close_rounded, size: 16, color: SoukColors.danger),
                      onPressed: _busy == null ? () => _removeImage(img) : null,
                    ),
                  ),
                ),
              ]),
            ),
          if (p.images.length < 9)
            SizedBox(
              width: 96,
              height: 96,
              child: Material(
                color: SoukColors.cream,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(SoukRadius.control),
                  side: const BorderSide(color: SoukColors.lineStrong),
                ),
                child: InkWell(
                  key: const Key('seller-product-add-image'),
                  borderRadius: BorderRadius.circular(SoukRadius.control),
                  onTap: _busy == null ? _addImage : null,
                  child: Center(
                    child: _busy == 'image'
                        ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                        : Column(mainAxisSize: MainAxisSize.min, children: [
                            const FaIcon(FontAwesomeIcons.plus, size: 16, color: SoukColors.emerald),
                            const SizedBox(height: SoukSpace.s1),
                            Text(l.sellerAddImage, style: SoukText.meta, textAlign: TextAlign.center),
                          ]),
                  ),
                ),
              ),
            ),
        ],
      );

  /// One emerald CTA per state: Save alone for a new / pending / banned
  /// product, Publish for a draft, Relist for a delisted one; a live product
  /// keeps Save as its CTA with Delist beside it.
  Widget _buttons(AppLocalizations l) {
    final status = _product?.status;
    Widget save({bool primary = false}) => Expanded(
          child: SoukButton(
            key: const Key('seller-product-save'),
            label: l.sellerSave,
            variant: primary ? SoukButtonVariant.primary : SoukButtonVariant.secondary,
            expand: true,
            busy: _busy == 'save',
            requiresOnline: true,
            onPressed: _busy == null ? _save : null,
          ),
        );
    Widget action(String action, String label, {bool primary = true}) => Expanded(
          child: SoukButton(
            key: Key('seller-product-$action'),
            label: label,
            variant: primary ? SoukButtonVariant.primary : SoukButtonVariant.secondary,
            expand: true,
            busy: _busy == action,
            requiresOnline: true,
            onPressed: _busy == null ? () => _action(action) : null,
          ),
        );
    const gap = SizedBox(width: SoukSpace.s3);
    return Row(children: switch (status) {
      'draft' => [save(), gap, action('publish', l.sellerPublish)],
      'live' => [action('delist', l.sellerDelist, primary: false), gap, save(primary: true)],
      'delisted' => [save(), gap, action('relist', l.sellerRelist)],
      _ => [save(primary: true)],
    });
  }
}
