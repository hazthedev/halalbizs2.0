import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/api/api_client.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import 'orders_api.dart';

/// Swapped by tests.
ImagePicker orderReviewPicker = ImagePicker();

/// Web ReviewOrder limit: up to 5 photos per review (each image, max 4 MB:
/// the API says so back in `items.N.photos` when one is too big).
const reviewMaxPhotos = 5;

/// `/orders/:id/review` — 1–5 stars + optional body per unreviewed item,
/// optional seller rating + comment; `POST orders/{id}/review`.
class OrderReviewScreen extends ConsumerStatefulWidget {
  const OrderReviewScreen({super.key, required this.id});

  final int id;

  @override
  ConsumerState<OrderReviewScreen> createState() => _OrderReviewScreenState();
}

class _OrderReviewScreenState extends ConsumerState<OrderReviewScreen> {
  final _ratings = <int, int>{};
  final _bodies = <int, TextEditingController>{};
  final _photos = <int, List<(XFile, Uint8List)>>{};
  int? _sellerRating;
  final _sellerComment = TextEditingController();
  bool _busy = false;
  Object? _error;

  @override
  void dispose() {
    for (final c in _bodies.values) {
      c.dispose();
    }
    _sellerComment.dispose();
    super.dispose();
  }

  TextEditingController _body(int itemId) => _bodies.putIfAbsent(itemId, TextEditingController.new);

  Future<void> _pick(int itemId) async {
    final have = _photos[itemId] ?? [];
    final remaining = reviewMaxPhotos - have.length;
    if (remaining <= 0) return;
    try {
      final files = (await orderReviewPicker.pickMultiImage(imageQuality: 80, limit: remaining)).take(remaining);
      final added = [for (final f in files) (f, await f.readAsBytes())];
      if (mounted) setState(() => _photos[itemId] = [...have, ...added]);
    } catch (e) {
      if (mounted) showApiError(context, e);
    }
  }

  Future<void> _submit(List<OrderItem> items) async {
    final l = context.l10n;
    final missing = items.where((it) => (_ratings[it.id] ?? 0) == 0).toList();
    if (missing.isNotEmpty) {
      setState(() => _error = ApiException('validation_failed', l.ordersRateEveryItem, status: 422));
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    final router = GoRouter.of(context);
    final messenger = ScaffoldMessenger.of(context);
    try {
      final comment = _sellerComment.text.trim();
      await ref.read(ordersApiProvider).submitReview(
            widget.id,
            items: [
              for (final it in items)
                {
                  'order_item_id': it.id,
                  'rating': _ratings[it.id],
                  if (_body(it.id).text.trim().isNotEmpty) 'body': _body(it.id).text.trim(),
                  if ((_photos[it.id] ?? []).isNotEmpty) 'photos': [for (final p in _photos[it.id]!) p.$1],
                },
            ],
            sellerRating: _sellerRating,
            sellerComment: comment.isEmpty ? null : comment,
          );
      if (!mounted) return;
      ref.invalidate(orderDetailProvider(widget.id));
      messenger
        ..clearSnackBars()
        ..showSnackBar(SnackBar(content: Text(l.ordersReviewSubmitted)));
      router.pop();
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e);
      showApiError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final async = ref.watch(orderDetailProvider(widget.id));
    final items = async.value?.data.items.where((it) => !it.reviewed).toList() ?? const <OrderItem>[];

    return SoukPage(
      title: l.ordersWriteReview,
      scrollable: true,
      bottomBar: items.isEmpty
          ? null
          : SoukButton(
              key: const Key('review-submit'),
              label: l.ordersReviewSubmit,
              variant: SoukButtonVariant.primary,
              expand: true,
              requiresOnline: true,
              busy: _busy,
              onPressed: () => _submit(items),
            ),
      body: async.when(
        loading: () => const SoukSkeleton.list(rows: 3),
        error: (e, _) => ErrorPane(error: e, onRetry: () => ref.invalidate(orderDetailProvider(widget.id)), retryLabel: l.retry),
        data: (r) => items.isEmpty
            ? SoukEmptyState(title: l.ordersNothingToReview, action: SoukButton(label: l.backToHome, onPressed: () => context.go('/orders')))
            : Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                if (_error is ApiException && (_error! as ApiException).fields.isEmpty) ...[
                  SoukAlert(apiMessage(context, _error!), status: 'danger'),
                  const SizedBox(height: SoukSpace.s3),
                ],
                for (var i = 0; i < items.length; i++) ...[
                  SoukCard(
                    key: Key('review-item-${items[i].id}'),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                      Row(children: [
                        SizedBox(width: 48, height: 48, child: ProductImage(items[i].image, radius: SoukRadius.control)),
                        const SizedBox(width: SoukSpace.s3),
                        Expanded(child: Text(items[i].name, style: SoukText.displayName, maxLines: 2, overflow: TextOverflow.ellipsis)),
                      ]),
                      const SizedBox(height: SoukSpace.s3),
                      StarRow(
                        keyPrefix: 'review-star-${items[i].id}',
                        value: _ratings[items[i].id] ?? 0,
                        onChanged: (v) => setState(() => _ratings[items[i].id] = v),
                      ),
                      const SizedBox(height: SoukSpace.s3),
                      SoukTextField(
                        label: l.ordersReviewBody,
                        fieldKey: Key('review-body-${items[i].id}'),
                        controller: _body(items[i].id),
                        maxLines: 3,
                        hint: l.ordersReviewBodyHint,
                        error: fieldError(_error, 'items.$i.body'),
                      ),
                      const SizedBox(height: SoukSpace.s3),
                      Text(l.ordersPhotos, style: SoukText.label),
                      const SizedBox(height: 6),
                      Wrap(spacing: SoukSpace.s2, runSpacing: SoukSpace.s2, children: [
                        for (var n = 0; n < (_photos[items[i].id] ?? []).length; n++)
                          Stack(children: [
                            ClipRRect(
                              borderRadius: BorderRadius.circular(SoukRadius.control),
                              child: Image.memory(_photos[items[i].id]![n].$2, width: 72, height: 72, fit: BoxFit.cover),
                            ),
                            Positioned(
                              right: 0,
                              top: 0,
                              child: InkWell(
                                key: Key('review-photo-remove-${items[i].id}-$n'),
                                onTap: () => setState(() => _photos[items[i].id]!.removeAt(n)),
                                child: Container(
                                  decoration: const BoxDecoration(color: SoukColors.overlay, shape: BoxShape.circle),
                                  padding: const EdgeInsets.all(4),
                                  child: const Icon(Icons.close_rounded, size: 14, color: Colors.white),
                                ),
                              ),
                            ),
                          ]),
                        if ((_photos[items[i].id] ?? []).length < reviewMaxPhotos)
                          InkWell(
                            key: Key('review-photo-add-${items[i].id}'),
                            onTap: () => _pick(items[i].id),
                            borderRadius: BorderRadius.circular(SoukRadius.control),
                            child: Container(
                              width: 72,
                              height: 72,
                              decoration: BoxDecoration(
                                color: SoukColors.cream,
                                borderRadius: BorderRadius.circular(SoukRadius.control),
                                border: Border.all(color: SoukColors.lineStrong),
                              ),
                              child: const Icon(Icons.add_a_photo_outlined, color: SoukColors.inkFaint),
                            ),
                          ),
                      ]),
                      const SizedBox(height: SoukSpace.s1),
                      Text(fieldErrorAt(_error, 'items.$i.photos') ?? l.ordersPhotosHelp(reviewMaxPhotos),
                          style: fieldErrorAt(_error, 'items.$i.photos') != null ? SoukText.error : SoukText.meta),
                    ]),
                  ),
                  const SizedBox(height: SoukSpace.s3),
                ],
                SoukCard(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                    Text(l.ordersSellerRating, style: SoukText.displayName),
                    Text(l.ordersOptional, style: SoukText.meta),
                    const SizedBox(height: SoukSpace.s3),
                    StarRow(keyPrefix: 'review-seller-star', value: _sellerRating ?? 0, onChanged: (v) => setState(() => _sellerRating = v)),
                    const SizedBox(height: SoukSpace.s3),
                    SoukTextField(
                      label: l.ordersSellerComment,
                      fieldKey: const Key('review-seller-comment'),
                      controller: _sellerComment,
                      maxLines: 3,
                      error: fieldError(_error, 'seller_comment'),
                    ),
                  ]),
                ),
              ]),
      ),
    );
  }
}

/// Five tappable stars (brass = ornament; the rating itself is not an action).
class StarRow extends StatelessWidget {
  const StarRow({super.key, required this.value, required this.onChanged, this.keyPrefix = 'star'});

  final int value;
  final ValueChanged<int> onChanged;
  final String keyPrefix;

  @override
  Widget build(BuildContext context) => Row(children: [
        for (var n = 1; n <= 5; n++)
          IconButton(
            key: Key('$keyPrefix-$n'),
            tooltip: context.l10n.ordersReviewStars(n),
            onPressed: () => onChanged(n),
            style: IconButton.styleFrom(minimumSize: const Size(SoukSpace.minTap, SoukSpace.minTap)),
            icon: FaIcon(n <= value ? FontAwesomeIcons.solidStar : FontAwesomeIcons.star, size: 22, color: n <= value ? SoukColors.brass : SoukColors.lineStrong),
          ),
      ]);
}
