import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_widget_from_html_core/flutter_widget_from_html_core.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import 'catalog_api.dart';
import 'category_screen.dart';
import 'follow_button.dart';
import 'live.dart';
import 'models.dart';
import 'paged.dart';

/// `/s/:slug` — banner, store header (logo, name, blurb + About sheet,
/// rating, product count, joined), then the product grid with sort/filter
/// and the next page on scroll.
class StoreScreen extends ConsumerStatefulWidget {
  const StoreScreen({super.key, required this.slug});

  final String slug;

  @override
  ConsumerState<StoreScreen> createState() => _StoreScreenState();
}

class _StoreScreenState extends ConsumerState<StoreScreen> {
  var _filter = const ProductFilter();
  late final _list = PagedList<Product>(
    (page) async => (await ref.read(catalogApiProvider).storeProducts(widget.slug, filter: _filter, page: page)).paged,
  )..reload();

  @override
  void dispose() {
    _list.dispose();
    super.dispose();
  }

  Future<void> _refresh() async {
    ref.invalidate(storeProvider(widget.slug));
    await _list.reload();
  }

  void _about(Store s) => showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    builder: (context) => DraggableScrollableSheet(
      expand: false,
      initialChildSize: .6,
      builder: (context, scroll) => ListView(
        key: const Key('store-about-sheet'),
        controller: scroll,
        padding: const EdgeInsets.all(SoukSpace.page),
        children: [
          Text(context.l10n.storeAbout, style: SoukText.displayH3),
          const SizedBox(height: SoukSpace.s3),
          // Seller rich text. Core package only: links render as text, never open.
          HtmlWidget(s.description!, textStyle: SoukText.bodySoft),
        ],
      ),
    ),
  );

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final s = ref.watch(storeProvider(widget.slug)).value?.data;

    return ListenableBuilder(
      listenable: _list,
      builder: (context, _) {
        // Until GET stores/{slug} answers, the name comes from the products' store summary.
        final name = (s?.json['name'] != null ? s!.name : null) ?? _list.items.firstOrNull?.storeName ?? widget.slug;
        final joined = s?.joinedAt == null
            ? null
            : DateFormat('MMM yyyy', Localizations.localeOf(context).toString()).format(s!.joinedAt!.toLocal());
        final count = s?.productCount ?? _list.last?.meta.total ?? _list.items.length;
        return SoukPage(
          title: name,
          scrollable: true,
          onRefresh: _refresh,
          onScrollEnd: _list.autoMore,
          fromCache: _list.last?.fromCache ?? false,
          cachedAt: _list.last?.fetchedAt,
          body: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (s?.banner != null) ...[
                AspectRatio(key: const Key('store-banner'), aspectRatio: 3, child: ProductImage(s!.banner)),
                const SizedBox(height: SoukSpace.s3),
              ],
              SoukCard(
                key: const Key('store-header'),
                child: Row(
                  children: [
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
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(name, style: SoukText.displayH3, maxLines: 2, overflow: TextOverflow.ellipsis),
                          if (s?.storeLive ?? false)
                            Padding(
                              padding: const EdgeInsets.only(top: SoukSpace.s1),
                              child: Align(alignment: Alignment.centerLeft, child: LiveBadge(s!.liveSlug!, key: const Key('store-live'))),
                            ),
                          if (s?.descriptionPlain?.isNotEmpty ?? false)
                            InkWell(
                              key: const Key('store-about'),
                              onTap: () => _about(s),
                              child: Padding(
                                padding: const EdgeInsets.only(top: 2),
                                child: Text.rich(
                                  TextSpan(
                                    children: [
                                      TextSpan(text: '${s!.descriptionPlain!} '),
                                      TextSpan(
                                        text: l.storeAbout,
                                        style: SoukText.meta.copyWith(color: SoukColors.emerald, fontWeight: FontWeight.w600),
                                      ),
                                    ],
                                  ),
                                  style: SoukText.meta,
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                            ),
                          Padding(
                            padding: const EdgeInsets.only(top: SoukSpace.s1),
                            child: Wrap(
                              spacing: SoukSpace.s3,
                              runSpacing: 2,
                              crossAxisAlignment: WrapCrossAlignment.center,
                              children: [
                                if ((s?.ratingAvg ?? 0) > 0)
                                  Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      const FaIcon(FontAwesomeIcons.solidStar, size: 10, color: SoukColors.brass),
                                      const SizedBox(width: 3),
                                      Text(s!.ratingAvg!.toStringAsFixed(1), style: SoukText.mono),
                                    ],
                                  ),
                                Text('$count ${l.products.toLowerCase()}', key: const Key('store-count'), style: SoukText.mono),
                                if (joined != null) Text(l.storeJoined(joined), key: const Key('store-joined'), style: SoukText.mono),
                              ],
                            ),
                          ),
                          if (s != null) ...[
                            const SizedBox(height: SoukSpace.s2),
                            FollowButton(slug: widget.slug, store: s),
                          ],
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: SoukSpace.s5),
              PagedProductGrid(
                list: _list,
                filter: _filter,
                sorts: catalogSorts(l),
                onFilter: (f) {
                  setState(() => _filter = f);
                  _list.reload(clear: true);
                },
                empty: SoukEmptyState(
                  key: const Key('store-empty'),
                  title: l.noProductsYet,
                  message: l.noProductsBody,
                  action: SoukButton(label: l.backToHome, onPressed: () => context.go('/home')),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}
