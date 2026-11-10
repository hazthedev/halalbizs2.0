import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:go_router/go_router.dart';

import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import 'catalog_api.dart';

/// `/s/:slug` — store header + product grid.
class StoreScreen extends ConsumerWidget {
  const StoreScreen({super.key, required this.slug});

  final String slug;

  Future<void> _refresh(WidgetRef ref) async {
    ref.invalidate(storeProvider(slug));
    ref.invalidate(storeProductsProvider(slug));
    try {
      await ref.read(storeProductsProvider(slug).future);
    } catch (_) {
      // surfaced by the ErrorPane
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final store = ref.watch(storeProvider(slug));
    final products = ref.watch(storeProductsProvider(slug));
    final s = store.value?.data;
    // Until GET stores/{slug} exists the name comes from the products' store summary.
    final name = (s?.json['name'] != null ? s!.name : null) ??
        products.value?.items.firstOrNull?.storeName ??
        slug;

    return SoukPage(
      title: name,
      scrollable: true,
      onRefresh: () => _refresh(ref),
      fromCache: products.value?.fromCache ?? false,
      cachedAt: products.value?.fetchedAt,
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SoukCard(
            key: const Key('store-header'),
            child: Row(children: [
              Container(
                width: 56,
                height: 56,
                decoration: BoxDecoration(
                  color: SoukColors.cream,
                  borderRadius: BorderRadius.circular(SoukRadius.control),
                  border: Border.all(color: SoukColors.line),
                ),
                child: s?.logo == null
                    ? const Center(child: FaIcon(FontAwesomeIcons.store, size: 20, color: SoukColors.brassDeep))
                    : ProductImage(s!.logo, radius: SoukRadius.control),
              ),
              const SizedBox(width: SoukSpace.s3),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(name, style: SoukText.displayH3, maxLines: 2, overflow: TextOverflow.ellipsis),
                  if (s?.descriptionPlain?.isNotEmpty ?? false)
                    Padding(
                      padding: const EdgeInsets.only(top: 2),
                      child: Text(s!.descriptionPlain!, style: SoukText.meta, maxLines: 2, overflow: TextOverflow.ellipsis),
                    ),
                  Padding(
                    padding: const EdgeInsets.only(top: SoukSpace.s1),
                    child: Row(children: [
                      if ((s?.ratingAvg ?? 0) > 0) ...[
                        const FaIcon(FontAwesomeIcons.solidStar, size: 10, color: SoukColors.brass),
                        const SizedBox(width: 3),
                        Text(s!.ratingAvg!.toStringAsFixed(1), style: SoukText.mono),
                        const SizedBox(width: SoukSpace.s3),
                      ],
                      Text('${s?.productCount ?? products.value?.meta.total ?? products.value?.items.length ?? 0} ${l.products.toLowerCase()}',
                          style: SoukText.mono),
                    ]),
                  ),
                ]),
              ),
            ]),
          ),
          const SizedBox(height: SoukSpace.s5),
          products.when(
            skipLoadingOnRefresh: true,
            loading: () => const SoukSkeleton.cards(rows: 4),
            error: (e, _) => ErrorPane(error: e, onRetry: () => _refresh(ref), retryLabel: l.retry),
            data: (p) => p.items.isEmpty
                ? SoukEmptyState(
                    key: const Key('store-empty'),
                    title: l.noProductsYet,
                    message: l.noProductsBody,
                    action: SoukButton(label: l.backToHome, onPressed: () => context.go('/home')),
                  )
                : ProductGrid(p.items, soldLabel: l.sold, onTap: (p) => context.push('/p/${Uri.encodeComponent(p.slug)}')),
          ),
        ],
      ),
    );
  }
}
