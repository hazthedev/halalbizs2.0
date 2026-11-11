import 'package:collection/collection.dart';
import 'package:flutter/material.dart';

import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import 'models.dart';

/// Opens the variant picker over the product page. Every pick and quantity
/// change is reported through [onChanged] (the page row follows along);
/// resolves true when the shopper pressed Add to cart with a full pick.
Future<bool> showVariantSheet(
  BuildContext context, {
  required Product product,
  required Map<int, int> picks,
  required int qty,
  required void Function(Map<int, int> picks, int qty) onChanged,
}) async =>
    await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => VariantSheet(product: product, picks: picks, qty: qty, onChanged: onChanged),
    ) ??
    false;

/// Header (thumbnail, price, stock, close), one chip row per option type
/// (web ProductDetail rules: one pick per row, a value with no in-stock
/// variant for the other picks is disabled), quantity, and a pinned button
/// that waits for every row.
class VariantSheet extends StatefulWidget {
  const VariantSheet({super.key, required this.product, required this.picks, required this.qty, required this.onChanged});

  final Product product;
  final Map<int, int> picks;
  final int qty;
  final void Function(Map<int, int> picks, int qty) onChanged;

  @override
  State<VariantSheet> createState() => _VariantSheetState();
}

class _VariantSheetState extends State<VariantSheet> {
  late final Map<int, int> _picks = {...widget.picks};
  late int _qty = widget.qty;

  void _pick(int groupId, int valueId) {
    setState(() {
      // Same as the web: tapping the picked value again clears that row.
      if (_picks[groupId] == valueId) {
        _picks.remove(groupId);
      } else {
        _picks[groupId] = valueId;
      }
      final v = widget.product.variantFor(_picks);
      if (v != null && v.stock > 0 && _qty > v.stock) _qty = v.stock;
    });
    widget.onChanged({..._picks}, _qty);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final p = widget.product;
    final groups = p.optionGroups;
    final variant = p.variantFor(_picks);
    final missing = groups.firstWhereOrNull((g) => !_picks.containsKey(g.id));
    final canConfirm = variant != null && variant.inStock;
    final regular = variant?.regularPriceSen;
    final images = p.images;
    final large = p.largeImages;

    return Column(
      key: const Key('picker-sheet'),
      mainAxisSize: MainAxisSize.min,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(SoukSpace.page, SoukSpace.s4, SoukSpace.s2, SoukSpace.s3),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Semantics(
                button: true,
                label: l.productViewImage,
                child: GestureDetector(
                  key: const Key('picker-thumb'),
                  onTap: () => showImageViewer(context, large.firstOrNull),
                  child: SizedBox(width: 72, height: 72, child: ProductImage(images.firstOrNull, radius: SoukRadius.control)),
                ),
              ),
              const SizedBox(width: SoukSpace.s3),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Wrap(
                      crossAxisAlignment: WrapCrossAlignment.end,
                      spacing: SoukSpace.s2,
                      children: [
                        PriceText(variant?.priceSen ?? p.lowestPriceSen, key: const Key('picker-price'), large: true),
                        if (regular != null) PriceText(regular, key: const Key('picker-regular-price'), strike: true),
                      ],
                    ),
                    if (variant != null) ...[const SizedBox(height: 2), Text(l.productStock(variant.stock), style: SoukText.mono)],
                  ],
                ),
              ),
              IconButton(
                key: const Key('picker-close'),
                tooltip: l.close,
                icon: const Icon(Icons.close_rounded),
                onPressed: () => Navigator.of(context).pop(false),
              ),
            ],
          ),
        ),
        const Divider(height: 1),
        Flexible(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(SoukSpace.page, SoukSpace.s4, SoukSpace.page, SoukSpace.s4),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (final g in groups) ...[
                  Text(g.name.isEmpty ? l.variant : g.name, style: SoukText.label),
                  const SizedBox(height: SoukSpace.s2),
                  Wrap(
                    spacing: SoukSpace.s2,
                    runSpacing: SoukSpace.s2,
                    children: [
                      for (final v in g.values)
                        Builder(
                          builder: (_) {
                            final ok = p.isAvailable(_picks, g.id, v.id);
                            return SoukChip(
                              key: Key('picker-value-${v.id}'),
                              label: v.value,
                              selected: _picks[g.id] == v.id,
                              enabled: ok,
                              onTap: ok ? () => _pick(g.id, v.id) : null,
                            );
                          },
                        ),
                    ],
                  ),
                  const SizedBox(height: SoukSpace.s5),
                ],
                Row(
                  children: [
                    Expanded(child: Text(l.quantity, style: SoukText.label)),
                    QtyStepper(
                      value: _qty,
                      max: variant?.stock,
                      enabled: canConfirm,
                      onChanged: (q) {
                        setState(() => _qty = q);
                        widget.onChanged({..._picks}, q);
                      },
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(SoukSpace.page, SoukSpace.s2, SoukSpace.page, SoukSpace.s4),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (missing != null) ...[
                Text(
                  l.productChoose(missing.name.isEmpty ? l.variant.toLowerCase() : missing.name.toLowerCase()),
                  key: const Key('picker-hint'),
                  textAlign: TextAlign.center,
                  style: SoukText.meta,
                ),
                const SizedBox(height: SoukSpace.s2),
              ],
              SoukButton(
                key: const Key('picker-confirm'),
                label: variant != null && !variant.inStock ? l.outOfStock : l.addToCart,
                variant: SoukButtonVariant.primary,
                expand: true,
                requiresOnline: true,
                onPressed: canConfirm ? () => Navigator.of(context).pop(true) : null,
              ),
            ],
          ),
        ),
      ],
    );
  }
}

/// Full-screen, pinch-to-zoom view of one product image.
Future<void> showImageViewer(BuildContext context, String? url) => showDialog<void>(
  context: context,
  useSafeArea: false,
  builder: (ctx) => Dialog.fullscreen(
    backgroundColor: SoukColors.emeraldNight,
    child: Stack(
      children: [
        Positioned.fill(
          child: InteractiveViewer(
            key: const Key('image-viewer'),
            maxScale: 4,
            child: Center(child: ProductImage(url, fit: BoxFit.contain, radius: 0)),
          ),
        ),
        SafeArea(
          child: IconButton(
            key: const Key('image-viewer-close'),
            tooltip: ctx.l10n.close,
            icon: const Icon(Icons.close_rounded, color: SoukColors.onDark),
            onPressed: () => Navigator.of(ctx).pop(),
          ),
        ),
      ],
    ),
  ),
);
