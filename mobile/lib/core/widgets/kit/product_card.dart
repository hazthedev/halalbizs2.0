import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';

import '../../../features/account/wishlist_heart.dart';
import '../../../features/catalog/halal.dart';
import '../../../features/catalog/live.dart';
import '../../../features/catalog/models.dart';
import '../../../l10n/l10n.dart';
import '../../theme/souk_theme.dart';
import 'empty_state.dart';
import 'price_text.dart';

/// Product image (`card` conversion) with the khatam placeholder on an empty
/// URL or a load error.
class ProductImage extends StatelessWidget {
  const ProductImage(this.url, {super.key, this.fit = BoxFit.cover, this.radius = SoukRadius.card});

  final String? url;
  final BoxFit fit;
  final double radius;

  @override
  Widget build(BuildContext context) {
    final placeholder = Container(
      color: SoukColors.cream,
      alignment: Alignment.center,
      child: const KhatamMark(size: 36, color: SoukColors.brass),
    );
    return ClipRRect(
      borderRadius: BorderRadius.circular(radius),
      child: url == null || url!.isEmpty
          ? placeholder
          : CachedNetworkImage(
              imageUrl: url!,
              fit: fit,
              placeholder: (_, _) => Container(color: SoukColors.cream),
              errorWidget: (_, _, _) => placeholder,
            ),
    );
  }
}

/// Grid tile: image, name (serif, 2 lines), mono price, sold count, rating.
/// Key `product-card-<slug>`. The corner heart (Key `product-wishlist`) is off
/// where the page draws its own (the wishlist grid).
class ProductCard extends StatelessWidget {
  const ProductCard(this.product, {super.key, required this.onTap, this.soldLabel = 'sold', this.wishlist = true});

  final Product product;
  final VoidCallback onTap;
  final String soldLabel;
  final bool wishlist;

  @override
  Widget build(BuildContext context) {
    final p = product;
    return Semantics(
      button: true,
      label: '${p.name}, ${formatSen(p.minPriceSen)}',
      child: Material(
        key: Key('product-card-${p.slug}'),
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(SoukRadius.card),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              AspectRatio(
                aspectRatio: 1,
                child: Container(
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(SoukRadius.card),
                    border: Border.all(color: SoukColors.line),
                  ),
                  child: Stack(fit: StackFit.expand, children: [
                    ProductImage(p.image),
                    // Web card's "Halal certified" line; on the packshot here so
                    // the fixed-ratio grid tile does not grow a row.
                    if (p.halal?.verified ?? false)
                      Positioned(
                        left: 6,
                        bottom: 6,
                        right: 6,
                        child: Align(
                          alignment: Alignment.bottomLeft,
                          child: Container(
                            key: Key('product-halal-${p.slug}'),
                            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                            decoration: BoxDecoration(
                              color: SoukColors.surface.withValues(alpha: .92),
                              borderRadius: BorderRadius.circular(SoukRadius.pill),
                              border: Border.all(color: SoukColors.emeraldTintEdge),
                            ),
                            child: HalalTick(context.l10n.halalCertified, caps: true),
                          ),
                        ),
                      ),
                    if (p.storeLive)
                      Positioned(top: 6, left: 6, child: LiveBadge(p.liveSlug!, key: Key('product-live-${p.slug}'))),
                    if (wishlist)
                      Positioned(
                        top: 6,
                        right: 6,
                        child: Material(
                          color: SoukColors.surface.withValues(alpha: .92),
                          shape: const CircleBorder(side: BorderSide(color: SoukColors.line)),
                          child: WishlistHeart(p),
                        ),
                      ),
                  ]),
                ),
              ),
              const SizedBox(height: SoukSpace.s2),
              Text(p.name, maxLines: 2, overflow: TextOverflow.ellipsis, style: SoukText.displayName),
              const SizedBox(height: SoukSpace.s1),
              PriceText(p.minPriceSen),
              const SizedBox(height: 2),
              Row(children: [
                if (p.ratingAvg > 0) ...[
                  const FaIcon(FontAwesomeIcons.solidStar, size: 10, color: SoukColors.brass),
                  const SizedBox(width: 3),
                  Text(p.ratingAvg.toStringAsFixed(1), style: SoukText.mono.copyWith(fontSize: 11.5, color: SoukColors.inkMoss)),
                  const SizedBox(width: SoukSpace.s2),
                ],
                Flexible(
                  child: Text('${p.soldCount} $soldLabel',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: SoukText.mono.copyWith(fontSize: 11.5, color: SoukColors.inkMoss)),
                ),
              ]),
            ],
          ),
        ),
      ),
    );
  }
}

/// Two-column product grid used by home, search, category and store.
class ProductGrid extends StatelessWidget {
  const ProductGrid(this.products, {super.key, required this.onTap, this.soldLabel = 'sold'});

  final List<Product> products;
  final void Function(Product) onTap;
  final String soldLabel;

  @override
  Widget build(BuildContext context) => GridView.builder(
        key: const Key('product-grid'),
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        itemCount: products.length,
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 2,
          mainAxisSpacing: SoukSpace.s4,
          crossAxisSpacing: SoukSpace.s3,
          childAspectRatio: .64,
        ),
        itemBuilder: (_, i) => ProductCard(products[i], onTap: () => onTap(products[i]), soldLabel: soldLabel),
      );
}
