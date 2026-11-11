import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_client.dart';
import '../../core/auth/session.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import '../account/wishlist_heart.dart';
import '../cart/cart_api.dart';
import '../messages/messages_api.dart';
import 'catalog_api.dart';
import 'halal.dart';
import 'models.dart';
import 'product_extras.dart';
import 'variant_sheet.dart';

/// `/p/:slug` — gallery, name, price (range until a variant is picked, sale
/// strike-through), "Select: Colour, Size" row opening the variant sheet,
/// stock, store row, description, sold / rating, pinned "Add to cart".
class ProductScreen extends ConsumerStatefulWidget {
  const ProductScreen({super.key, required this.slug});

  final String slug;

  @override
  ConsumerState<ProductScreen> createState() => _ProductScreenState();
}

class _ProductScreenState extends ConsumerState<ProductScreen> {
  /// Picker row id → value id (see [Product.optionGroups]).
  Map<int, int> _picks = {};
  int _qty = 1;
  int _image = 0;
  bool _adding = false;
  bool _chatting = false;

  /// The page's own messenger: the "Added to cart" bar floats above this
  /// page's bottom bar (the shell's messenger would lay it over Add to cart)
  /// and leaves with the page instead of covering Cart's Checkout.
  final _messenger = GlobalKey<ScaffoldMessengerState>();

  Variant? _selected(Product p) => p.variantFor(_picks);

  /// The variant sheet; true when the shopper confirmed a full pick.
  Future<bool> _openSheet(Product p) => showVariantSheet(
        context,
        product: p,
        picks: _picks,
        qty: _qty,
        onChanged: (picks, qty) => setState(() {
          _picks = picks;
          _qty = qty;
        }),
      );

  Future<void> _refresh() async {
    ref.invalidate(productProvider(widget.slug));
    ref.invalidate(productReviewsProvider(widget.slug));
    try {
      await ref.read(productProvider(widget.slug).future);
    } catch (_) {
      // surfaced by the ErrorPane
    }
  }

  Future<void> _addToCart(Product p, {bool buyNow = false}) async {
    // An incomplete pick opens the sheet instead of failing.
    if (_selected(p) == null) {
      if (p.variants.isEmpty || !await _openSheet(p) || !mounted) return;
    }
    final v = _selected(p);
    if (v == null) return;
    if (ref.read(sessionProvider) is! SignedIn) {
      context.push('/login?from=${Uri.encodeComponent('/p/${p.slug}')}');
      return;
    }
    setState(() => _adding = true);
    final l = context.l10n;
    final router = GoRouter.of(context);
    try {
      await ref.read(cartProvider.notifier).add(variantId: v.id, qty: _qty);
      if (!mounted) return;
      // Web buyNow: add, then straight to the cart (its Checkout button pays).
      if (buyNow) return router.go('/cart');
      _messenger.currentState
        ?..clearSnackBars()
        ..showSnackBar(SnackBar(
          content: Text(l.addedToCart),
          // An action makes a SnackBar persist by default; this one must go.
          persist: false,
          duration: const Duration(seconds: 4),
          action: SnackBarAction(key: const Key('product-view-cart'), label: l.cartViewCart, textColor: SoukColors.onDark, onPressed: () => router.go('/cart')),
        ));
    } catch (e) {
      if (mounted) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _adding = false);
    }
  }

  /// `POST conversations {store_id, product_id}` then the thread. The product
  /// payload has no store id, so it comes from `GET stores/{slug}`.
  Future<void> _chat(Product p) async {
    if (ref.read(sessionProvider) is! SignedIn) {
      context.push('/login?from=${Uri.encodeComponent('/p/${p.slug}')}');
      return;
    }
    final slug = p.storeSlug;
    if (slug == null) return;
    setState(() => _chatting = true);
    final router = GoRouter.of(context);
    try {
      final storeId = (await ref.read(storeProvider(slug).future)).data.id;
      if (storeId == null) throw ApiException('not_found', 'Store not found.', status: 404);
      final c = await ref.read(messagesApiProvider).open(storeId: storeId, productId: p.id);
      if (!mounted) return;
      router.push('/account/messages/${c.id}');
    } catch (e) {
      if (mounted) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _chatting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final async = ref.watch(productProvider(widget.slug));
    final product = async.value?.data;
    final variant = product == null ? null : _selected(product);
    final canBuy = product != null && (variant?.inStock ?? product.inStock);
    final picker = product != null && product.variants.length > 1;

    return ScaffoldMessenger(key: _messenger, child: SoukPage(
      title: product?.name ?? l.product,
      scrollable: true,
      onRefresh: _refresh,
      fromCache: async.value?.fromCache ?? false,
      cachedAt: async.value?.fetchedAt,
      padding: EdgeInsets.zero,
      actions: [
        if (product != null) WishlistHeart(product),
        if (product?.storeSlug != null)
          SoukIconButton(
            key: const Key('product-chat'),
            icon: FontAwesomeIcons.comment,
            label: l.messagesChatWithSeller,
            onPressed: _chatting ? null : () => _chat(product!),
          ),
      ],
      bottomBar: product == null
          ? null
          : Row(children: [
              // Several variants: quantity lives in the variant sheet.
              if (!picker) ...[
                QtyStepper(value: _qty, max: variant?.stock, enabled: canBuy, onChanged: (v) => setState(() => _qty = v)),
                const SizedBox(width: SoukSpace.s3),
              ],
              Expanded(
                child: SoukButton(
                  key: const Key('product-add-to-cart'),
                  label: canBuy ? l.addToCart : l.outOfStock,
                  variant: SoukButtonVariant.secondary,
                  compact: true,
                  expand: true,
                  busy: _adding,
                  requiresOnline: true,
                  onPressed: canBuy ? () => _addToCart(product) : null,
                ),
              ),
              const SizedBox(width: SoukSpace.s2),
              Expanded(
                child: SoukButton(
                  key: const Key('product-buy-now'),
                  label: l.productBuyNow,
                  variant: SoukButtonVariant.primary,
                  compact: true,
                  expand: true,
                  busy: _adding,
                  requiresOnline: true,
                  onPressed: canBuy ? () => _addToCart(product, buyNow: true) : null,
                ),
              ),
            ]),
      body: async.when(
        skipLoadingOnRefresh: true,
        loading: () => const Padding(
          padding: EdgeInsets.all(SoukSpace.page),
          child: Column(children: [
            AspectRatio(aspectRatio: 1, child: SoukSkeleton.block(height: double.infinity, radius: SoukRadius.card)),
            SizedBox(height: SoukSpace.s4),
            SoukSkeleton.list(rows: 3),
          ]),
        ),
        error: (e, _) => ErrorPane(error: e, onRetry: _refresh, retryLabel: l.retry),
        data: (r) => _ProductBody(
          product: r.data,
          variant: variant,
          image: _image,
          onImage: (i) => setState(() => _image = i),
          onSelect: () => _openSheet(r.data),
        ),
      ),
    ));
  }
}

class _ProductBody extends StatelessWidget {
  const _ProductBody({
    required this.product,
    required this.variant,
    required this.image,
    required this.onImage,
    required this.onSelect,
  });

  final Product product;
  final Variant? variant;
  final int image;
  final ValueChanged<int> onImage;
  final VoidCallback onSelect;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = product;
    final images = p.images;
    // Web PDP: the picked variant's price, else min – max (cards show min).
    final price = variant?.priceSen ?? p.lowestPriceSen;
    final maxPrice = variant == null ? p.maxPriceSen : price;
    final regular = variant?.regularPriceSen;
    final groups = p.variants.length > 1 ? p.optionGroups : const <OptionGroup>[];
    final stock = variant?.stock;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Gallery
        AspectRatio(
          aspectRatio: 1,
          child: images.isEmpty
              ? const ProductImage(null, radius: 0)
              : PageView.builder(
                  key: const Key('product-gallery'),
                  itemCount: images.length,
                  onPageChanged: onImage,
                  itemBuilder: (_, i) => GestureDetector(
                    key: Key('product-gallery-$i'),
                    onTap: () => showImageViewer(context, p.largeImages.elementAtOrNull(i) ?? images[i]),
                    child: ProductImage(images[i], radius: 0),
                  ),
                ),
        ),
        if (images.length > 1)
          Padding(
            padding: const EdgeInsets.only(top: SoukSpace.s2),
            child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
              for (var i = 0; i < images.length; i++)
                Container(
                  key: Key('product-gallery-dot-$i${i == image ? '-on' : ''}'),
                  width: i == image ? 18 : 6,
                  height: 6,
                  margin: const EdgeInsets.symmetric(horizontal: 3),
                  decoration: BoxDecoration(
                    color: i == image ? SoukColors.emerald : SoukColors.lineStrong,
                    borderRadius: BorderRadius.circular(SoukRadius.pill),
                  ),
                ),
            ]),
          ),
        Padding(
          padding: const EdgeInsets.fromLTRB(SoukSpace.page, SoukSpace.s4, SoukSpace.page, SoukSpace.s6),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(p.name, key: const Key('product-name'), style: SoukText.displayH2),
              // Web: the certificate sits above the price — it outranks it here.
              if (p.halal case final cert?) ...[
                const SizedBox(height: SoukSpace.s3),
                HalalPanel(cert),
              ],
              const SizedBox(height: SoukSpace.s3),
              // Wrap: a min – max range is two large prices and may not fit one line.
              Wrap(crossAxisAlignment: WrapCrossAlignment.end, children: [
                PriceText(price, key: const Key('product-price'), large: true),
                if (maxPrice > price) ...[
                  const Padding(padding: EdgeInsets.symmetric(horizontal: SoukSpace.s2), child: Text('–', style: SoukText.priceLarge)),
                  PriceText(maxPrice, key: const Key('product-price-max'), large: true),
                ],
                if (regular != null) ...[
                  const SizedBox(width: SoukSpace.s2),
                  Padding(
                    padding: const EdgeInsets.only(bottom: 3),
                    child: PriceText(regular, key: const Key('product-regular-price'), strike: true),
                  ),
                ],
              ]),
              const SizedBox(height: SoukSpace.s2),
              Row(children: [
                if (p.ratingAvg > 0) ...[
                  const FaIcon(FontAwesomeIcons.solidStar, size: 11, color: SoukColors.brass),
                  const SizedBox(width: 4),
                  Text(p.ratingAvg.toStringAsFixed(1), style: SoukText.mono),
                  if (p.reviewCount > 0) Text('  ·  ${l.reviewsCount(p.reviewCount)}', style: SoukText.meta),
                  const SizedBox(width: SoukSpace.s3),
                ],
                Text('${p.soldCount} ${l.sold}', style: SoukText.mono),
              ]),
              if (groups.isNotEmpty) ...[
                const SizedBox(height: SoukSpace.s5),
                SoukCard(
                  key: const Key('product-select'),
                  padding: const EdgeInsets.symmetric(horizontal: SoukSpace.s4, vertical: SoukSpace.s3),
                  onTap: onSelect,
                  child: Row(children: [
                    Expanded(
                      child: Text(
                        variant != null ? variant!.label : l.productSelect(groups.map((g) => g.name.isEmpty ? l.variant : g.name).join(', ')),
                        style: SoukText.body,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    const Icon(Icons.chevron_right_rounded, color: SoukColors.inkFaint),
                  ]),
                ),
              ],
              const SizedBox(height: SoukSpace.s3),
              if (stock != null)
                Text(
                  key: const Key('product-stock'),
                  stock <= 0
                      ? l.outOfStock
                      : stock <= 5
                          ? l.onlyLeft(stock)
                          : l.inStock(stock),
                  style: SoukText.mono.copyWith(color: stock <= 0 ? SoukColors.danger : (stock <= 5 ? SoukColors.warn : SoukColors.inkMoss)),
                ),
              if (variant != null && variant!.sku.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(top: 2),
                  child: Text('SKU ${variant!.sku}', style: SoukText.monoLabel),
                ),
              if (p.shipsFrom != null) ...[
                const SizedBox(height: SoukSpace.s4),
                ShippingRow(p),
              ],
              if (p.storeSlug != null) ...[
                const SizedBox(height: SoukSpace.s4),
                SellerCard(p),
              ],
              HalalDetails(p),
              const SizedBox(height: SoukSpace.s6),
              ProductTabs(p),
              Consumer(
                builder: (_, ref, _) => ProductStrip(
                    keyPrefix: 'related', title: l.relatedVerified, items: ref.watch(relatedProvider(p.slug)).value ?? const []),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
