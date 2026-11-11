import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import '../account/inbox_actions.dart';
import '../catalog/catalog_api.dart';
import '../catalog/models.dart';
import '../catalog/product_extras.dart';

/// Home tab, all from one `GET home`: banner carousel, flash sale strip,
/// the Featured grid (category chips removed, Haze 11-11). Offline-first: the cached strip
/// shows when the payload came from the snapshot.
class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  Future<void> _refresh(WidgetRef ref) async {
    ref.invalidate(homeProvider);
    try {
      await ref.read(homeProvider.future);
    } catch (_) {
      // surfaced by the ErrorPane
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final home = ref.watch(homeProvider);
    final feed = home.value;

    return SoukPage(
      title: l.appName,
      brandMark: true,
      actions: const [InboxActions()],
      scrollable: true,
      onRefresh: () => _refresh(ref),
      fromCache: feed?.fromCache ?? false,
      cachedAt: feed?.fetchedAt,
      body: home.when(
        skipLoadingOnRefresh: true,
        loading: () => const Column(children: [
          SoukSkeleton.block(height: SoukSpace.minTap),
          SizedBox(height: SoukSpace.s5),
          SoukSkeleton.cards(rows: 4),
        ]),
        error: (e, _) => ErrorPane(error: e, onRetry: () => _refresh(ref), retryLabel: l.retry),
        data: (f) => Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (f.banners.isNotEmpty) ...[
              BannerCarousel(f.banners),
              const SizedBox(height: SoukSpace.s5),
            ],
            if (f.flashEndsAt != null && f.flashItems.isNotEmpty) ...[
              FlashSaleStrip(endsAt: f.flashEndsAt!, items: f.flashItems),
              const SizedBox(height: SoukSpace.s5),
            ],
            SoukSectionHeading(l.featured, linkLabel: l.viewAll, onLink: () => context.push('/featured')),
            const SizedBox(height: SoukSpace.s3),
            f.featured.isEmpty
                ? SoukEmptyState(
                    key: const Key('home-empty'),
                    title: l.noProductsYet,
                    message: l.noProductsBody,
                    action: SoukButton(label: l.retry, onPressed: () => _refresh(ref)),
                  )
                : ProductGrid(f.featured, soldLabel: l.sold, onTap: (p) => context.push('/p/${Uri.encodeComponent(p.slug)}')),
            ProductStrip(keyPrefix: 'recommended', title: l.recommendedForYou, items: ref.watch(recommendedProvider).value ?? const []),
          ],
        ),
      ),
    );
  }
}

/// Swipeable banners (web home: 3:1 artwork, paper dots, 6 s auto-advance,
/// none under reduced motion). Tap = the banner's `app_url` route; a banner
/// without one is not tappable. Key `home-banner-<id>`.
class BannerCarousel extends StatefulWidget {
  const BannerCarousel(this.banners, {super.key});

  final List<HomeBanner> banners;

  @override
  State<BannerCarousel> createState() => _BannerCarouselState();
}

class _BannerCarouselState extends State<BannerCarousel> {
  final _controller = PageController();
  Timer? _timer;
  int _page = 0;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _restart();
  }

  /// (Re)starts the 6 s timer; a swipe restarts it so it never jumps mid-read.
  void _restart() {
    _timer?.cancel();
    if (widget.banners.length < 2 || MediaQuery.disableAnimationsOf(context)) return;
    _timer = Timer.periodic(const Duration(seconds: 6), (_) {
      if (!_controller.hasClients) return;
      _controller.animateToPage((_page + 1) % widget.banners.length,
          duration: const Duration(milliseconds: 400), curve: Curves.easeInOut);
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final banners = widget.banners;
    return Semantics(
      label: context.l10n.homePromotions,
      container: true,
      child: AspectRatio(
        aspectRatio: 3,
        child: ClipRRect(
          borderRadius: BorderRadius.circular(SoukRadius.card),
          child: Stack(children: [
            PageView.builder(
              key: const Key('home-banners'),
              controller: _controller,
              itemCount: banners.length,
              onPageChanged: (i) {
                setState(() => _page = i);
                _restart();
              },
              itemBuilder: (context, i) {
                final b = banners[i];
                final link = b.appUrl;
                return Semantics(
                  container: true,
                  label: b.title,
                  image: true,
                  button: link != null,
                  child: GestureDetector(
                    key: Key('home-banner-${b.id}'),
                    onTap: link == null ? null : () => context.push(link),
                    child: ProductImage(b.image, radius: 0),
                  ),
                );
              },
            ),
            if (banners.length > 1)
              Positioned(
                left: 0,
                right: 0,
                bottom: SoukSpace.s3,
                child: ExcludeSemantics(
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      for (var i = 0; i < banners.length; i++)
                        AnimatedContainer(
                          key: Key('home-banner-dot-$i'),
                          duration: const Duration(milliseconds: 200),
                          margin: const EdgeInsets.symmetric(horizontal: 3),
                          width: i == _page ? 18 : 7,
                          height: 7,
                          decoration: BoxDecoration(
                            color: SoukColors.paper.withValues(alpha: i == _page ? 1 : .5),
                            borderRadius: BorderRadius.circular(SoukRadius.pill),
                          ),
                        ),
                    ],
                  ),
                ),
              ),
          ]),
        ),
      ),
    );
  }
}

/// Live flash sale (web `/flash-sale`): heading + "Ends in hh:mm:ss" pill,
/// then a row of deals (promo price, struck original). Hides itself when
/// the countdown reaches zero. Key `flash-item-<slug>`.
class FlashSaleStrip extends StatefulWidget {
  const FlashSaleStrip({super.key, required this.endsAt, required this.items});

  final DateTime endsAt;
  final List<FlashItem> items;

  @override
  State<FlashSaleStrip> createState() => _FlashSaleStripState();
}

class _FlashSaleStripState extends State<FlashSaleStrip> {
  late final Timer _tick;

  @override
  void initState() {
    super.initState();
    _tick = Timer.periodic(const Duration(seconds: 1), (_) => setState(() {}));
  }

  @override
  void dispose() {
    _tick.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final left = widget.endsAt.difference(DateTime.now());
    if (left <= Duration.zero) {
      _tick.cancel();
      return const SizedBox.shrink();
    }
    String two(int n) => n.toString().padLeft(2, '0');
    final clock = '${two(left.inHours)}:${two(left.inMinutes % 60)}:${two(left.inSeconds % 60)}';

    return Column(
      key: const Key('home-flash'),
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(children: [
          Expanded(child: Text(l.flashSale, style: SoukText.displayH3, maxLines: 1, overflow: TextOverflow.ellipsis)),
          Text(l.flashEndsIn, style: SoukText.meta.copyWith(color: SoukColors.inkSoft)),
          const SizedBox(width: SoukSpace.s2),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: SoukSpace.s2, vertical: SoukSpace.s1),
            decoration: BoxDecoration(
              color: SoukColors.emeraldNight,
              borderRadius: BorderRadius.circular(SoukRadius.control),
            ),
            child: Text(clock, key: const Key('flash-countdown'), style: SoukText.price.copyWith(fontSize: 13, color: SoukColors.onDark)),
          ),
        ]),
        const SizedBox(height: SoukSpace.s3),
        SizedBox(
          height: 228,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            itemCount: widget.items.length,
            separatorBuilder: (_, _) => const SizedBox(width: SoukSpace.s3),
            itemBuilder: (context, i) => _FlashCard(widget.items[i]),
          ),
        ),
      ],
    );
  }
}

class _FlashCard extends StatelessWidget {
  const _FlashCard(this.item);

  final FlashItem item;

  @override
  Widget build(BuildContext context) {
    final p = item.product;
    final original = item.originalSen;
    return SizedBox(
      width: 140,
      child: Semantics(
        button: true,
        label: '${p.name}, ${formatSen(item.priceSen)}',
        child: Material(
          key: Key('flash-item-${p.slug}'),
          color: Colors.transparent,
          child: InkWell(
            onTap: () => context.push('/p/${Uri.encodeComponent(p.slug)}'),
            borderRadius: BorderRadius.circular(SoukRadius.card),
            child: ExcludeSemantics(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  AspectRatio(
                    aspectRatio: 1,
                    child: Container(
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(SoukRadius.card),
                        border: Border.all(color: SoukColors.line),
                      ),
                      child: ProductImage(p.image),
                    ),
                  ),
                  const SizedBox(height: SoukSpace.s2),
                  Text(p.name, maxLines: 2, overflow: TextOverflow.ellipsis, style: SoukText.displayName.copyWith(fontSize: 14)),
                  const SizedBox(height: SoukSpace.s1),
                  PriceText(item.priceSen, style: SoukText.price.copyWith(color: SoukColors.emerald)),
                  if (original != null) PriceText(original, strike: true, style: SoukText.price.copyWith(fontSize: 12)),
                ],
              ),
            ),
          ),
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
