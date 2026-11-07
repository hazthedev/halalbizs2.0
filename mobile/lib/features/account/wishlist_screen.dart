import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:go_router/go_router.dart';

import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import 'account_api.dart';

/// `/account/wishlist` — saved products as a grid; the heart removes.
class WishlistScreen extends ConsumerStatefulWidget {
  const WishlistScreen({super.key});

  @override
  ConsumerState<WishlistScreen> createState() => _WishlistScreenState();
}

class _WishlistScreenState extends ConsumerState<WishlistScreen> {
  final _removing = <int>{};

  Future<void> _refresh() async {
    ref.invalidate(wishlistProvider);
    try {
      await ref.read(wishlistProvider.future);
    } catch (_) {}
  }

  Future<void> _remove(int productId) async {
    setState(() => _removing.add(productId));
    try {
      await ref.read(accountApiProvider).toggleWishlist(productId);
      if (!mounted) return;
      ref.invalidate(wishlistProvider);
    } catch (e) {
      if (mounted) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _removing.remove(productId));
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final async = ref.watch(wishlistProvider);
    return SoukPage(
      title: l.wishlist,
      scrollable: true,
      onRefresh: _refresh,
      body: async.when(
        skipLoadingOnRefresh: true,
        loading: () => const SoukSkeleton.cards(rows: 4),
        error: (e, _) => ErrorPane(error: e, onRetry: _refresh, retryLabel: l.retry),
        data: (items) => items.isEmpty
            ? SoukEmptyState(
                key: const Key('wishlist-empty'),
                title: l.accountWishlistEmpty,
                message: l.accountWishlistEmptyBody,
                action: SoukButton(label: l.cartContinueShopping, variant: SoukButtonVariant.primary, onPressed: () => context.go('/home')),
              )
            : GridView.builder(
                key: const Key('wishlist-grid'),
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                itemCount: items.length,
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 2, mainAxisSpacing: SoukSpace.s4, crossAxisSpacing: SoukSpace.s3, childAspectRatio: .64),
                itemBuilder: (_, i) {
                  final p = items[i];
                  return Stack(children: [
                    ProductCard(p, soldLabel: l.sold, onTap: () => context.push('/p/${p.slug}')),
                    Positioned(
                      top: 6,
                      right: 6,
                      child: Material(
                        color: SoukColors.surface.withValues(alpha: .92),
                        shape: const CircleBorder(side: BorderSide(color: SoukColors.line)),
                        child: SoukIconButton(
                          key: Key('wishlist-remove-${p.id}'),
                          icon: FontAwesomeIcons.solidHeart,
                          label: l.accountRemoveFromWishlist,
                          color: SoukColors.danger,
                          onPressed: _removing.contains(p.id) ? null : () => _remove(p.id),
                        ),
                      ),
                    ),
                  ]);
                },
              ),
      ),
    );
  }
}
