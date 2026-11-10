import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import 'catalog_api.dart';

/// `/c/:slug` — products filtered by category (`GET products?category=`).
class CategoryScreen extends ConsumerWidget {
  const CategoryScreen({super.key, required this.slug});

  final String slug;

  Future<void> _refresh(WidgetRef ref) async {
    ref.invalidate(categoryProductsProvider(slug));
    try {
      await ref.read(categoryProductsProvider(slug).future);
    } catch (_) {
      // surfaced by the ErrorPane
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final async = ref.watch(categoryProductsProvider(slug));
    final name = ref.watch(categoriesProvider).value?.data.where((c) => c.slug == slug).firstOrNull?.name;

    return SoukPage(
      title: name ?? l.category,
      scrollable: true,
      onRefresh: () => _refresh(ref),
      fromCache: async.value?.fromCache ?? false,
      cachedAt: async.value?.fetchedAt,
      body: async.when(
        skipLoadingOnRefresh: true,
        loading: () => const SoukSkeleton.cards(rows: 4),
        error: (e, _) => ErrorPane(error: e, onRetry: () => _refresh(ref), retryLabel: l.retry),
        data: (p) => p.items.isEmpty
            ? SoukEmptyState(
                key: const Key('category-empty'),
                title: l.noProductsYet,
                message: l.noProductsBody,
                action: SoukButton(label: l.backToHome, onPressed: () => context.go('/home')),
              )
            : ProductGrid(p.items, soldLabel: l.sold, onTap: (p) => context.push('/p/${Uri.encodeComponent(p.slug)}')),
      ),
    );
  }
}
