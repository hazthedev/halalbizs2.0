import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import '../catalog/catalog_api.dart';
import '../catalog/models.dart';
import '../catalog/paged.dart';

/// `/account/following` — followed shops (`GET me/followed-stores`), newest
/// follow first, tap opens the shop; pages on with "Load more".
class FollowedShopsScreen extends ConsumerStatefulWidget {
  const FollowedShopsScreen({super.key});

  @override
  ConsumerState<FollowedShopsScreen> createState() => _FollowedShopsScreenState();
}

class _FollowedShopsScreenState extends ConsumerState<FollowedShopsScreen> {
  late final _list = PagedList<Store>((page) => ref.read(catalogApiProvider).followedStores(page: page))..reload();

  @override
  void dispose() {
    _list.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return ListenableBuilder(
      listenable: _list,
      builder: (context, _) => SoukPage(
        title: l.followedShops,
        scrollable: true,
        onRefresh: _list.reload,
        body: _list.error != null
            ? ErrorPane(error: _list.error!, onRetry: _list.reload, retryLabel: l.retry)
            : _list.loading && _list.items.isEmpty
                ? const SoukSkeleton.list(rows: 4)
                : _list.items.isEmpty
                    ? SoukEmptyState(key: const Key('followed-empty'), title: l.followedShopsEmptyTitle, message: l.followedShopsEmptyBody)
                    : Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                        SoukCard(
                          padding: EdgeInsets.zero,
                          child: Column(children: [
                            for (final s in _list.items)
                              SoukRow(
                                key: Key('followed-store-${s.slug}'),
                                label: s.name,
                                subtitle: l.followersCount(s.followersCount),
                                onTap: () => context.push('/s/${Uri.encodeComponent(s.slug)}'),
                              ),
                          ]),
                        ),
                        LoadMoreButton(_list),
                      ]),
      ),
    );
  }
}
