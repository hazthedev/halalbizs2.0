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
          SoukSectionHeading(l.featured, linkLabel: l.viewAll, onLink: () => context.push('/featured')),
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

/// `/featured` — Home's "View all": the same `GET products?sort=top` list as
/// the Featured grid (page 1 shared with Home), plus "Load more" pages.
class FeaturedScreen extends ConsumerStatefulWidget {
  const FeaturedScreen({super.key});

  @override
  ConsumerState<FeaturedScreen> createState() => _FeaturedScreenState();
}

class _FeaturedScreenState extends ConsumerState<FeaturedScreen> {
  final _more = <ProductPage>[];
  bool _loading = false;

  Future<void> _refresh() async {
    setState(_more.clear);
    ref.invalidate(featuredProductsProvider);
    try {
      await ref.read(featuredProductsProvider.future);
    } catch (_) {
      // surfaced by the ErrorPane
    }
  }

  Future<void> _loadMore(int page) async {
    setState(() => _loading = true);
    try {
      final next = await ref.read(catalogApiProvider).products(sort: 'top', page: page);
      if (mounted) setState(() => _more.add(next));
    } catch (e) {
      if (mounted) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final async = ref.watch(featuredProductsProvider);
    return SoukPage(
      title: l.featured,
      scrollable: true,
      onRefresh: _refresh,
      fromCache: async.value?.fromCache ?? false,
      cachedAt: async.value?.fetchedAt,
      body: async.when(
        skipLoadingOnRefresh: true,
        loading: () => const SoukSkeleton.cards(rows: 4),
        error: (e, _) => ErrorPane(error: e, onRetry: _refresh, retryLabel: l.retry),
        data: (first) {
          final last = _more.lastOrNull ?? first;
          final items = [first, ..._more].expand((p) => p.items).toList();
          if (items.isEmpty) return SoukEmptyState(title: l.noProductsYet, message: l.noProductsBody);
          return Column(children: [
            ProductGrid(items, soldLabel: l.sold, onTap: (p) => context.push('/p/${Uri.encodeComponent(p.slug)}')),
            if (last.meta.hasMore) ...[
              const SizedBox(height: SoukSpace.s4),
              SoukButton(
                key: const Key('featured-load-more'),
                label: l.ordersLoadMore,
                busy: _loading,
                requiresOnline: true,
                onPressed: () => _loadMore(last.meta.page + 1),
              ),
            ],
          ]);
        },
      ),
    );
  }
}
