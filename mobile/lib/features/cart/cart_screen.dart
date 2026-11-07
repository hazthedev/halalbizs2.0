import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:go_router/go_router.dart';

import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import '../checkout/checkout_screen.dart';
import '../checkout/done_screen.dart';
import '../checkout/pay_screen.dart';
import 'cart_api.dart';

/// Absolute routes that share the Cart tab's branch.
List<RouteBase> get cartSubRoutes => [
      GoRoute(path: '/checkout', builder: (_, _) => const CheckoutScreen(), routes: [
        GoRoute(
          path: 'pay/:orderNo',
          builder: (_, s) => PayScreen(orderNo: s.pathParameters['orderNo']!, initialUrl: s.uri.queryParameters['url']),
        ),
        GoRoute(path: 'done/:orderNo', builder: (_, s) => DoneScreen(orderNo: s.pathParameters['orderNo']!)),
      ]),
    ];

/// `/cart` — lines grouped by store, select per line / store / all, qty,
/// remove, selected subtotal and the one emerald CTA: Checkout.
class CartScreen extends ConsumerStatefulWidget {
  const CartScreen({super.key});

  @override
  ConsumerState<CartScreen> createState() => _CartScreenState();
}

class _CartScreenState extends ConsumerState<CartScreen> {
  bool _busy = false;

  Future<void> _write(Future<void> Function(CartController c) op) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await op(ref.read(cartProvider.notifier));
    } catch (e) {
      if (mounted) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _refresh() async {
    try {
      await ref.read(cartProvider.notifier).refresh();
    } catch (_) {
      // surfaced by the ErrorPane
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final async = ref.watch(cartProvider);
    final cart = async.value;
    final cached = cart?.fromCache ?? false;
    final canWrite = !cached && !_busy;
    final purchasing = cart?.purchasingEnabled ?? true;
    final selected = cart?.selectedCount ?? 0;

    return SoukPage(
      title: l.tabCart,
      brandMark: true,
      showBack: false,
      scrollable: true,
      onRefresh: _refresh,
      fromCache: cached,
      cachedAt: cart?.fetchedAt,
      bottomBar: cart == null || cart.isEmpty
          ? null
          : Row(children: [
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
                  Text(l.cartSelectedSubtotal(selected).toUpperCase(), style: SoukText.eyebrow),
                  PriceText(cart.selectedSubtotalSen, key: const Key('cart-subtotal'), large: true),
                ]),
              ),
              SoukButton(
                key: const Key('cart-checkout'),
                label: l.cartCheckout,
                icon: FontAwesomeIcons.arrowRight,
                variant: SoukButtonVariant.primary,
                requiresOnline: true,
                disabledReason: !purchasing
                    ? l.cartPurchasingPaused
                    : selected == 0
                        ? l.cartSelectToCheckout
                        : null,
                onPressed: () => context.push('/checkout'),
              ),
            ]),
      body: async.when(
        skipLoadingOnRefresh: true,
        loading: () => const SoukSkeleton.list(rows: 4),
        error: (e, _) => ErrorPane(error: e, onRetry: _refresh, retryLabel: l.retry),
        data: (cart) {
          if (cart.isEmpty) {
            return SoukEmptyState(
              key: const Key('cart-empty'),
              title: l.cartEmptyTitle,
              message: l.cartEmptyBody,
              action: SoukButton(
                key: const Key('cart-continue'),
                label: l.cartContinueShopping,
                variant: SoukButtonVariant.primary,
                onPressed: () => context.go('/home'),
              ),
            );
          }
          return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            if (!purchasing) ...[
              SoukAlert(l.cartPurchasingPaused, key: const Key('cart-purchasing-paused'), status: 'warn'),
              const SizedBox(height: SoukSpace.s3),
            ],
            _SelectRow(
              key: const Key('cart-select-all'),
              label: l.cartSelectAll,
              trailing: l.cartItemCount(cart.itemCount),
              value: cart.allSelected,
              enabled: canWrite,
              onChanged: (v) => _write((c) => c.selectAll(v)),
            ),
            for (final g in cart.groups) ...[
              const SizedBox(height: SoukSpace.s3),
              SoukCard(
                key: Key('cart-store-${g.storeId}'),
                padding: const EdgeInsets.fromLTRB(SoukSpace.s2, SoukSpace.s1, SoukSpace.s4, SoukSpace.s3),
                child: Column(children: [
                  _SelectRow(
                    key: Key('cart-store-select-${g.storeId}'),
                    label: g.storeName,
                    icon: FontAwesomeIcons.store,
                    value: g.allSelected,
                    enabled: canWrite,
                    onChanged: (v) => _write((c) => c.selectAll(v, storeId: g.storeId)),
                    onLabelTap: g.storeSlug == null ? null : () => context.push('/s/${g.storeSlug}'),
                  ),
                  for (final line in g.lines)
                    _LineRow(
                      line: line,
                      enabled: canWrite,
                      onSelected: (v) => _write((c) => c.setSelected(line.variantId, v)),
                      onQty: (q) => _write((c) => c.setQty(line.variantId, q)),
                      onRemove: () => _write((c) => c.remove(line.variantId)),
                      onTap: line.productSlug == null ? null : () => context.push('/p/${line.productSlug}'),
                    ),
                ]),
              ),
            ],
          ]);
        },
      ),
    );
  }
}

class _SelectRow extends StatelessWidget {
  const _SelectRow({
    super.key,
    required this.label,
    required this.value,
    required this.onChanged,
    this.enabled = true,
    this.icon,
    this.trailing,
    this.onLabelTap,
  });

  final String label;
  final bool value;
  final ValueChanged<bool> onChanged;
  final bool enabled;
  final FaIconData? icon;
  final String? trailing;
  final VoidCallback? onLabelTap;

  @override
  Widget build(BuildContext context) => Row(children: [
        Checkbox(value: value, activeColor: SoukColors.emerald, onChanged: enabled ? (v) => onChanged(v ?? false) : null),
        if (icon != null) ...[FaIcon(icon, size: 14, color: SoukColors.inkFaint), const SizedBox(width: SoukSpace.s2)],
        Expanded(
          child: GestureDetector(
            onTap: onLabelTap,
            child: Text(label, style: SoukText.label, maxLines: 1, overflow: TextOverflow.ellipsis),
          ),
        ),
        if (trailing != null) Text(trailing!, style: SoukText.mono),
        if (onLabelTap != null) const Icon(Icons.chevron_right_rounded, color: SoukColors.inkFaint),
      ]);
}

class _LineRow extends StatelessWidget {
  const _LineRow({
    required this.line,
    required this.enabled,
    required this.onSelected,
    required this.onQty,
    required this.onRemove,
    this.onTap,
  });

  final CartLine line;
  final bool enabled;
  final ValueChanged<bool> onSelected;
  final ValueChanged<int> onQty;
  final VoidCallback onRemove;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final canEdit = enabled && line.available;
    return Padding(
      key: Key('cart-line-${line.variantId}'),
      padding: const EdgeInsets.only(top: SoukSpace.s2),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Checkbox(
          key: Key('cart-line-select-${line.variantId}'),
          value: line.selected && line.available,
          activeColor: SoukColors.emerald,
          onChanged: canEdit ? (v) => onSelected(v ?? false) : null,
        ),
        GestureDetector(
          onTap: onTap,
          child: SizedBox(width: 56, height: 56, child: ProductImage(line.thumb, radius: SoukRadius.control)),
        ),
        const SizedBox(width: SoukSpace.s3),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Expanded(
                child: GestureDetector(
                  onTap: onTap,
                  child: Text(line.name, style: SoukText.displayName, maxLines: 2, overflow: TextOverflow.ellipsis),
                ),
              ),
              SizedBox(
                width: 32,
                height: 32,
                child: IconButton(
                  key: Key('cart-line-remove-${line.variantId}'),
                  tooltip: l.cartRemove,
                  padding: EdgeInsets.zero,
                  iconSize: 15,
                  color: SoukColors.inkFaint,
                  onPressed: enabled ? onRemove : null,
                  icon: const FaIcon(FontAwesomeIcons.trashCan),
                ),
              ),
            ]),
            if (line.variantLabel != null) Text(line.variantLabel!, style: SoukText.meta),
            const SizedBox(height: SoukSpace.s1),
            Row(children: [
              Expanded(
                child: Wrap(spacing: SoukSpace.s2, crossAxisAlignment: WrapCrossAlignment.center, children: [
                  PriceText(line.unitPriceSen),
                  if (line.onSale) PriceText(line.listPriceSen, strike: true),
                ]),
              ),
              if (!line.available)
                SoukBadge(l.cartUnavailable, status: 'danger')
              else
                QtyStepper(value: line.qty, max: line.stock, enabled: canEdit, onChanged: onQty),
            ]),
          ]),
        ),
      ]),
    );
  }
}
