import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/auth/session.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import 'catalog_api.dart';
import 'models.dart';

/// Follow / Following toggle + public follower count (web FollowButton).
/// Optimistic: flips at once, reverts and shows the error if the API fails.
/// Guest tap goes to login; the owner of the shop sees the count only.
/// Keys `follow-button`, `follow-count`.
class FollowButton extends ConsumerStatefulWidget {
  const FollowButton({super.key, required this.slug, required this.store, this.returnTo});

  final String slug;
  final Store store;

  /// Where login returns to.
  final String? returnTo;

  @override
  ConsumerState<FollowButton> createState() => _FollowButtonState();
}

class _FollowButtonState extends ConsumerState<FollowButton> {
  late bool _following = widget.store.isFollowing;
  late int _count = widget.store.followersCount;
  bool _busy = false;

  @override
  void didUpdateWidget(FollowButton old) {
    super.didUpdateWidget(old);
    if (!_busy && (old.store.isFollowing != widget.store.isFollowing || old.store.followersCount != widget.store.followersCount)) {
      _following = widget.store.isFollowing;
      _count = widget.store.followersCount;
    }
  }

  Future<void> _toggle() async {
    if (ref.read(sessionProvider) is! SignedIn) {
      context.push('/login?from=${Uri.encodeComponent(widget.returnTo ?? '/s/${widget.slug}')}');
      return;
    }
    final was = (_following, _count);
    setState(() {
      _busy = true;
      _following = !was.$1;
      _count = (was.$2 + (was.$1 ? -1 : 1)).clamp(0, 1 << 31);
    });
    try {
      final api = ref.read(catalogApiProvider);
      final r = was.$1 ? await api.unfollowStore(widget.slug) : await api.followStore(widget.slug);
      if (!mounted) return;
      setState(() {
        _following = r.following;
        _count = r.count;
      });
      ref.invalidate(followedStoresProvider);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _following = was.$1;
        _count = was.$2;
      });
      showApiError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final own = ref.watch(profileProvider)?.store?['slug'] == widget.slug;
    return Wrap(crossAxisAlignment: WrapCrossAlignment.center, spacing: SoukSpace.s3, runSpacing: SoukSpace.s1, children: [
      if (!own) ...[
        SoukButton(
          key: const Key('follow-button'),
          label: _following ? l.following : l.follow,
          variant: _following ? SoukButtonVariant.secondary : SoukButtonVariant.primary,
          compact: true,
          requiresOnline: true,
          onPressed: _busy ? null : _toggle,
        ),
      ],
      Text(l.followersCount(_count), key: const Key('follow-count'), style: SoukText.mono),
    ]);
  }
}
