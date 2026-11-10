import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:go_router/go_router.dart';

import '../../core/auth/session.dart';
import '../../core/widgets/kit/buttons.dart';
import '../../core/widgets/kit/common.dart';
import '../../core/theme/souk_theme.dart';
import '../../l10n/l10n.dart';
import '../catalog/models.dart';
import 'account_api.dart';

/// Heart toggle for one product (Key `product-wishlist`). Saved state is read
/// from `GET wishlist` (the web marks hearts the same way, from the user's
/// wishlisted ids); a tap is `POST wishlist/{product}`. Guests go to login,
/// as Add to cart does.
class WishlistHeart extends ConsumerStatefulWidget {
  const WishlistHeart(this.product, {super.key});

  final Product product;

  @override
  ConsumerState<WishlistHeart> createState() => _WishlistHeartState();
}

class _WishlistHeartState extends ConsumerState<WishlistHeart> {
  bool _busy = false;

  Future<void> _toggle() async {
    final p = widget.product;
    if (ref.read(sessionProvider) is! SignedIn) {
      context.push('/login?from=${Uri.encodeComponent('/p/${p.slug}')}');
      return;
    }
    setState(() => _busy = true);
    try {
      await ref.read(accountApiProvider).toggleWishlist(p.id);
      // The list is the truth for every heart and the wishlist screen.
      ref.invalidate(wishlistProvider);
      await ref.read(wishlistProvider.future);
    } catch (e) {
      if (mounted) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final signedIn = ref.watch(sessionProvider) is SignedIn;
    final saved = signedIn && (ref.watch(wishlistProvider).value?.any((w) => w.id == widget.product.id) ?? false);
    return SoukIconButton(
      key: const Key('product-wishlist'),
      icon: saved ? FontAwesomeIcons.solidHeart : FontAwesomeIcons.heart,
      label: saved ? l.accountRemoveFromWishlist : l.productAddToWishlist,
      color: saved ? SoukColors.danger : null,
      onPressed: _busy ? null : _toggle,
    );
  }
}
