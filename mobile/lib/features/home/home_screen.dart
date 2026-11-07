import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import '../catalog/catalog_api.dart';
import '../catalog/models.dart';

/// Home tab: categories strip + featured product grid. Both reads are
/// offline-first; the cached strip shows when either came from a snapshot.
class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  Future<void> _refresh(WidgetRef ref) async {
    ref.invalidate(featuredProductsProvider);
    ref.invalidate(categoriesProvider);
    try {
      await ref.read(featuredProductsProvider.future);
    } catch (_) {
      // surfaced by the ErrorPane
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final products = ref.watch(featuredProductsProvider);
    final categories = ref.watch(categoriesProvider);
    final page = products.value;
    final fromCache = (page?.fromCache ?? false) || (categories.value?.fromCache ?? false);

    return SoukPage(
      title: l.appName,
      brandMark: true,
      scrollable: true,
      onRefresh: () => _refresh(ref),
      fromCache: fromCache,
      cachedAt: page?.fetchedAt ?? categories.value?.fetchedAt,
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          categories.when(
            skipLoadingOnRefresh: true,
            loading: () => const SoukSkeleton.block(height: SoukSpace.minTap),
            error: (_, _) => const SizedBox.shrink(),
            data: (c) => _CategoryStrip(c.data),
          ),
          const SizedBox(height: SoukSpace.s5),
          SoukSectionHeading(l.featured, linkLabel: l.viewAll, onLink: () => context.go('/search')),
          const SizedBox(height: SoukSpace.s3),
          products.when(
            skipLoadingOnRefresh: true,
            loading: () => const SoukSkeleton.cards(rows: 4),
            error: (e, _) => ErrorPane(error: e, onRetry: () => _refresh(ref), retryLabel: l.retry),
            data: (p) => p.items.isEmpty
                ? SoukEmptyState(
                    key: const Key('home-empty'),
                    title: l.noProductsYet,
                    message: l.noProductsBody,
                    action: SoukButton(label: l.retry, onPressed: () => _refresh(ref)),
                  )
                : ProductGrid(p.items, soldLabel: l.sold, onTap: (p) => context.push('/p/${Uri.encodeComponent(p.slug)}')),
          ),
        ],
      ),
    );
  }
}

/// Horizontal category chips → `/c/:slug`.
class _CategoryStrip extends StatelessWidget {
  const _CategoryStrip(this.categories);

  final List<Category> categories;

  @override
  Widget build(BuildContext context) {
    if (categories.isEmpty) return const SizedBox.shrink();
    return SizedBox(
      height: SoukSpace.minTap,
      child: ListView.separated(
        key: const Key('home-categories'),
        scrollDirection: Axis.horizontal,
        itemCount: categories.length,
        separatorBuilder: (_, _) => const SizedBox(width: SoukSpace.s2),
        itemBuilder: (_, i) => SoukChip(
          key: Key('home-category-${categories[i].slug}'),
          label: categories[i].name,
          selected: false,
          onTap: () => context.push('/c/${Uri.encodeComponent(categories[i].slug)}'),
        ),
      ),
    );
  }
}
