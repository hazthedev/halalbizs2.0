import 'package:flutter/material.dart';
import 'package:flutter_widget_from_html_core/flutter_widget_from_html_core.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import 'models.dart';
import 'questions.dart';
import 'reviews.dart';

/// Web PDP shipping row ("Ships from :state · Shipping calculated at
/// checkout") and the COD badge under the seller card.
class ShippingRow extends StatelessWidget {
  const ShippingRow(this.product, {super.key});

  final Product product;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          key: const Key('product-shipping'),
          children: [
            const FaIcon(
              FontAwesomeIcons.truck,
              size: 13,
              color: SoukColors.inkFaint,
            ),
            const SizedBox(width: SoukSpace.s2),
            Expanded(
              child: Text(
                '${l.productShipsFrom(product.shipsFrom ?? 'Malaysia')} · ${l.productShippingAtCheckout}',
                style: SoukText.small,
              ),
            ),
          ],
        ),
        if (product.codAvailable)
          Padding(
            padding: const EdgeInsets.only(top: SoukSpace.s2),
            child: Align(
              alignment: Alignment.centerLeft,
              child: SoukBadge(
                l.productCod,
                key: const Key('product-cod'),
                status: 'processing',
              ),
            ),
          ),
      ],
    );
  }
}

/// Web seller card: logo or initial, name, Verified, product and service
/// ratings, product count, state, joined date; taps through to the store.
class SellerCard extends StatelessWidget {
  const SellerCard(this.product, {super.key});

  final Product product;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = product;
    final name = p.storeName ?? p.storeSlug!;
    final joined = p.storeJoinedAt == null
        ? null
        : DateFormat(
            'MMM yyyy',
            Localizations.localeOf(context).toString(),
          ).format(p.storeJoinedAt!.toLocal());
    final facts = <Widget>[
      if (p.storeRatingCount > 0)
        _fact(
          '${p.storeRatingAvg.toStringAsFixed(1)} (${p.storeRatingCount})',
          star: true,
        ),
      if (p.storeServiceCount > 0)
        _fact(
          '${l.productSellerService} ${p.storeServiceAvg.toStringAsFixed(1)} (${p.storeServiceCount})',
          star: true,
        ),
      if (p.storeProductCount != null)
        _fact('${p.storeProductCount} ${l.products.toLowerCase()}'),
      if (p.storeState != null) _fact(p.storeState!),
      if (joined != null)
        _fact(l.storeJoined(joined), key: const Key('product-store-joined')),
    ];
    return SoukCard(
      key: const Key('product-store'),
      padding: const EdgeInsets.symmetric(
        horizontal: SoukSpace.s4,
        vertical: SoukSpace.s3,
      ),
      onTap: () => context.push('/s/${Uri.encodeComponent(p.storeSlug!)}'),
      child: Row(
        children: [
          CircleAvatar(
            radius: 22,
            backgroundColor: SoukColors.line,
            backgroundImage: p.storeLogo == null
                ? null
                : NetworkImage(p.storeLogo!),
            child: p.storeLogo == null
                ? Text(
                    name.characters.first.toUpperCase(),
                    style: SoukText.displayName,
                  )
                : null,
          ),
          const SizedBox(width: SoukSpace.s3),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(l.soldBy.toUpperCase(), style: SoukText.eyebrow),
                Wrap(
                  spacing: SoukSpace.s2,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    Text(
                      name,
                      style: SoukText.displayName,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    if (p.storeVerified)
                      SoukBadge(
                        l.productVerified,
                        key: const Key('product-store-verified'),
                        status: 'approved',
                      ),
                  ],
                ),
                if (facts.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 2),
                    child: Wrap(
                      spacing: SoukSpace.s3,
                      runSpacing: 2,
                      children: facts,
                    ),
                  ),
              ],
            ),
          ),
          const Icon(Icons.chevron_right_rounded, color: SoukColors.emerald),
        ],
      ),
    );
  }

  Widget _fact(String text, {bool star = false, Key? key}) => Row(
    key: key,
    mainAxisSize: MainAxisSize.min,
    children: [
      if (star) ...[
        const FaIcon(
          FontAwesomeIcons.solidStar,
          size: 10,
          color: SoukColors.brass,
        ),
        const SizedBox(width: 3),
      ],
      Flexible(child: Text(text, style: SoukText.mono)),
    ],
  );
}

/// Web tabs under the product: Description | Specifications | Reviews | Q&A.
/// One panel is built at a time, so Reviews and Q&A load when opened.
class ProductTabs extends StatefulWidget {
  const ProductTabs(this.product, {super.key});

  final Product product;

  @override
  State<ProductTabs> createState() => _ProductTabsState();
}

class _ProductTabsState extends State<ProductTabs> {
  int _tab = 0;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = widget.product;
    final names = [l.description, l.productTabSpecs, l.reviews, l.productTabQa];
    const keys = ['description', 'specs', 'reviews', 'qa'];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        // Equal-width tabs (no inner scroller: the page scrolls; BM labels scale to fit).
        Row(children: [
          for (var i = 0; i < names.length; i++)
            Expanded(
              child: InkWell(
                key: Key('product-tab-${keys[i]}'),
                onTap: () => setState(() => _tab = i),
                child: Container(
                  constraints: const BoxConstraints(minHeight: 44),
                  padding: const EdgeInsets.symmetric(horizontal: SoukSpace.s1),
                  alignment: Alignment.center,
                  decoration: BoxDecoration(border: Border(bottom: BorderSide(color: i == _tab ? SoukColors.ink : SoukColors.line, width: 2))),
                  child: FittedBox(fit: BoxFit.scaleDown, child: Text(names[i], maxLines: 1, style: SoukText.label.copyWith(color: i == _tab ? SoukColors.ink : SoukColors.inkFaint))),
                ),
              ),
            ),
        ]),
        const SizedBox(height: SoukSpace.s4),
        switch (_tab) {
          0 =>
            p.description.isEmpty
                ? const SizedBox.shrink()
                // Seller rich text. Core package only: links render as text, never open.
                : HtmlWidget(p.description, textStyle: SoukText.bodySoft),
          1 => SpecsTable(p),
          2 => ProductReviews(slug: p.slug),
          _ => QuestionsPanel(slug: p.slug),
        },
      ],
    );
  }
}

/// Web Specifications tab: Brand, Condition, Weight, Category.
class SpecsTable extends StatelessWidget {
  const SpecsTable(this.product, {super.key});

  final Product product;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = product;
    final rows = [
      (l.productSpecBrand, p.specBrand ?? l.productNoBrand),
      if (p.specCondition != null) (l.productSpecCondition, p.specCondition!),
      if (p.specWeightGrams != null)
        (
          l.productSpecWeight,
          '${NumberFormat.decimalPattern().format(p.specWeightGrams)} g',
        ),
      if (p.specCategory != null) (l.productSpecCategory, p.specCategory!),
    ];
    return Column(
      key: const Key('product-specs'),
      children: [
        for (final (label, value) in rows)
          Container(
            padding: const EdgeInsets.symmetric(vertical: SoukSpace.s3),
            decoration: const BoxDecoration(
              border: Border(bottom: BorderSide(color: SoukColors.line)),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SizedBox(
                  width: 120,
                  child: Text(
                    label,
                    style: SoukText.label.copyWith(color: SoukColors.inkFaint),
                  ),
                ),
                Expanded(child: Text(value, style: SoukText.body)),
              ],
            ),
          ),
      ],
    );
  }
}

/// Horizontal product strip (web: heading + one scrolling row of cards). Hides
/// itself while loading, on error and when empty, like the web components.
/// Key `<keyPrefix>-strip`; cards keep their `product-card-<slug>` key.
class ProductStrip extends StatelessWidget {
  const ProductStrip({super.key, required this.keyPrefix, required this.title, required this.items});

  final String keyPrefix;
  final String title;
  final List<Product> items;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) return const SizedBox.shrink();
    final l = context.l10n;
    return Column(
      key: Key('$keyPrefix-strip'),
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SizedBox(height: SoukSpace.s6),
        SoukSectionHeading(title),
        const SizedBox(height: SoukSpace.s3),
        SizedBox(
          height: 250,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            itemCount: items.length,
            separatorBuilder: (_, _) => const SizedBox(width: SoukSpace.s3),
            itemBuilder: (_, i) => SizedBox(
              width: 150,
              child: ProductCard(items[i], soldLabel: l.sold, onTap: () => context.push('/p/${Uri.encodeComponent(items[i].slug)}')),
            ),
          ),
        ),
      ],
    );
  }
}
