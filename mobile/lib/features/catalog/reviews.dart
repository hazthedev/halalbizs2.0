import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import 'catalog_api.dart';
import 'models.dart';
import 'paged.dart';
import 'variant_sheet.dart';

/// Product page block: "N reviews" + the newest 3 + "View all". Nothing when
/// the product has no reviews or the call fails (the page stays usable).
class ProductReviews extends ConsumerWidget {
  const ProductReviews({super.key, required this.slug});

  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final page = ref.watch(productReviewsProvider(slug)).value;
    if (page == null) return const SizedBox.shrink();
    if (page.items.isEmpty) return Text(l.productNoReviews, key: const Key('product-reviews-empty'), style: SoukText.small);
    final total = page.meta.total > 0 ? page.meta.total : page.items.length;
    return Column(
      key: const Key('product-reviews'),
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SoukSectionHeading(
          l.reviewsCount(total),
          linkLabel: total > 3 ? l.viewAll : null,
          onLink: () => context.push('/p/${Uri.encodeComponent(slug)}/reviews'),
        ),
        for (final r in page.items.take(3)) ReviewTile(r),
      ],
    );
  }
}

/// `/p/:slug/reviews`: every review, newest first, next page on scroll.
class ReviewsScreen extends ConsumerStatefulWidget {
  const ReviewsScreen({super.key, required this.slug});

  final String slug;

  @override
  ConsumerState<ReviewsScreen> createState() => _ReviewsScreenState();
}

class _ReviewsScreenState extends ConsumerState<ReviewsScreen> {
  late final _list = PagedList<Review>((page) => ref.read(catalogApiProvider).reviews(widget.slug, page: page))..reload();

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
        title: l.reviews,
        scrollable: true,
        onRefresh: _list.reload,
        onScrollEnd: _list.autoMore,
        fromCache: _list.last?.fromCache ?? false,
        cachedAt: _list.last?.fetchedAt,
        body: _list.error != null
            ? ErrorPane(error: _list.error!, onRetry: _list.reload, retryLabel: l.retry)
            : _list.last == null
                ? const SoukSkeleton.list(rows: 4)
                : Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                    for (final r in _list.items) ReviewTile(r),
                    LoadMoreButton(_list, key: const Key('reviews-load-more')),
                  ]),
      ),
    );
  }
}

/// One review: stars + date, buyer name (as the API masks it), text, photos.
class ReviewTile extends StatelessWidget {
  const ReviewTile(this.review, {super.key});

  final Review review;

  @override
  Widget build(BuildContext context) {
    final r = review;
    final date = r.createdAt == null ? null : DateFormat('d MMM yyyy', Localizations.localeOf(context).toString()).format(r.createdAt!.toLocal());
    return Container(
      key: Key('review-${r.id}'),
      padding: const EdgeInsets.symmetric(vertical: SoukSpace.s3),
      decoration: const BoxDecoration(border: Border(bottom: BorderSide(color: SoukColors.line))),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Row(key: Key('review-${r.id}-stars-${r.rating}'), children: [
            for (var i = 1; i <= 5; i++)
              Padding(
                padding: const EdgeInsets.only(right: 2),
                child: FaIcon(i <= r.rating ? FontAwesomeIcons.solidStar : FontAwesomeIcons.star, size: 11, color: SoukColors.brass),
              ),
          ]),
          const SizedBox(width: SoukSpace.s2),
          Expanded(child: Text(r.buyerName, style: SoukText.label, maxLines: 1, overflow: TextOverflow.ellipsis)),
          if (date != null) Text(date, style: SoukText.meta),
        ]),
        if (r.body.isNotEmpty) ...[
          const SizedBox(height: SoukSpace.s2),
          Text(r.body, style: SoukText.bodySoft),
        ],
        if (r.photos.isNotEmpty) ...[
          const SizedBox(height: SoukSpace.s2),
          Wrap(spacing: SoukSpace.s2, runSpacing: SoukSpace.s2, children: [
            for (var i = 0; i < r.photos.length; i++)
              Semantics(
                button: true,
                label: context.l10n.productViewImage,
                child: GestureDetector(
                  key: Key('review-${r.id}-photo-$i'),
                  onTap: () => showImageViewer(context, r.photos[i]),
                  child: SizedBox(width: 64, height: 64, child: ProductImage(r.photos[i], radius: SoukRadius.control)),
                ),
              ),
          ]),
        ],
      ]),
    );
  }
}
