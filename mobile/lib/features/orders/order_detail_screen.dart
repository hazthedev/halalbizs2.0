import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/api/json.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import '../../core/api/api_client.dart';
import '../cart/cart_api.dart';
import '../checkout/checkout_api.dart';
import '../checkout/checkout_screen.dart' show paymentMethodLabel;
import 'orders_api.dart';

/// Translated label for a `payment.status` code (`PaymentStatus` enum).
String paymentStatusLabel(AppLocalizations l, String status) => switch (status) {
      'pending' => l.ordersPaymentStatusPending,
      'paid' => l.ordersPaymentStatusPaid,
      'failed' => l.ordersPaymentStatusFailed,
      'expired' => l.ordersPaymentStatusExpired,
      'refunded' => l.ordersPaymentStatusRefunded,
      _ => status,
    };

/// Translated label for a `return_request.status` code (`ReturnStatus` enum).
String returnStatusLabel(AppLocalizations l, String status) => switch (status) {
      'requested' => l.ordersReturnStatusRequested,
      'accepted' => l.ordersReturnStatusAccepted,
      'disputed' => l.ordersReturnStatusDisputed,
      'escalated' => l.ordersReturnStatusEscalated,
      'refunded' => l.ordersReturnStatusRefunded,
      'rejected' => l.ordersReturnStatusRejected,
      _ => status,
    };

/// `/orders/:id` — status timeline, items, address, payment, tracking and
/// the actions `can{}` allows (cancel, confirm received, return, review).
class OrderDetailScreen extends ConsumerStatefulWidget {
  const OrderDetailScreen({super.key, required this.id});

  final int id;

  @override
  ConsumerState<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends ConsumerState<OrderDetailScreen> {
  bool _busy = false;

  /// Outlives the cancel dialog's exit animation; disposed with the screen.
  final _cancelReason = TextEditingController();

  @override
  void dispose() {
    _cancelReason.dispose();
    super.dispose();
  }

  Future<void> _refresh() async {
    ref.invalidate(orderDetailProvider(widget.id));
    ref.invalidate(orderBucketsProvider);
    try {
      await ref.read(orderDetailProvider(widget.id).future);
    } catch (_) {
      // surfaced by the ErrorPane
    }
  }

  Future<void> _run(Future<SubOrder> Function() op, String doneMessage) async {
    setState(() => _busy = true);
    try {
      await op();
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..clearSnackBars()
        ..showSnackBar(SnackBar(content: Text(doneMessage)));
      await _refresh();
    } catch (e) {
      if (mounted) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _cancel() async {
    final l = context.l10n;
    final reason = _cancelReason..clear();
    final ok = await showDialog<bool>(
      context: context,
      useRootNavigator: true,
      builder: (ctx) => AlertDialog(
        title: Text(l.ordersCancelTitle),
        content: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Text(l.ordersCancelBody, style: SoukText.bodySoft),
          const SizedBox(height: SoukSpace.s4),
          SoukTextField(label: l.ordersReason, fieldKey: const Key('orders-cancel-reason'), controller: reason, maxLines: 3),
        ]),
        actionsPadding: const EdgeInsets.fromLTRB(SoukSpace.s4, 0, SoukSpace.s4, SoukSpace.s4),
        actions: [
          SoukButton(key: const Key('souk-confirm-cancel'), label: l.ordersKeep, variant: SoukButtonVariant.ghost, onPressed: () => Navigator.of(ctx).pop(false)),
          SoukButton(key: const Key('souk-confirm-ok'), label: l.ordersCancelConfirm, variant: SoukButtonVariant.danger, onPressed: () => Navigator.of(ctx).pop(true)),
        ],
      ),
    );
    final text = reason.text.trim();
    if (ok != true || !mounted) return;
    await _run(() => ref.read(ordersApiProvider).cancel(widget.id, reason: text.isEmpty ? null : text), l.ordersCancelled);
  }

  Future<void> _confirmReceived() async {
    final l = context.l10n;
    final ok = await showSoukConfirm(context,
        title: l.ordersReceivedTitle, message: l.ordersReceivedBody, confirmLabel: l.ordersConfirmReceived, cancelLabel: l.cancel);
    if (!ok || !mounted) return;
    await _run(() => ref.read(ordersApiProvider).confirmReceived(widget.id), l.ordersReceivedDone);
  }

  /// Same hand-off as checkout: the API's fresh `retry_url` opens in the in-app browser from the pay screen.
  Future<void> _payNow(SubOrder o) async {
    final l = context.l10n;
    setState(() => _busy = true);
    try {
      final s = await ref.read(checkoutApiProvider).payment(o.orderNo);
      if (!mounted) return;
      if (s.paid) {
        await _refresh();
      } else if (s.retryUrl == null) {
        ScaffoldMessenger.of(context)
          ..clearSnackBars()
          ..showSnackBar(SnackBar(content: Text(l.checkoutPayExpiredBody)));
      } else {
        await context.push('/checkout/pay/${Uri.encodeComponent(o.orderNo)}', extra: s.retryUrl);
        if (mounted) await _refresh();
      }
    } catch (e) {
      if (mounted) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  /// Re-adds each item through the cart's add; a deleted or sold-out variant is skipped and counted.
  Future<void> _buyAgain(SubOrder o) async {
    final l = context.l10n;
    final messenger = ScaffoldMessenger.of(context);
    final router = GoRouter.of(context);
    setState(() => _busy = true);
    var added = 0;
    var skipped = 0;
    try {
      for (final it in o.items) {
        final v = it.variantId;
        if (v == null) {
          skipped++;
          continue;
        }
        try {
          await ref.read(cartProvider.notifier).add(variantId: v, qty: it.qty);
          added++;
        } on ApiException catch (e) {
          if (!(e.isValidation || e.isNotFound || e.status == 409)) rethrow;
          skipped++;
        }
      }
      if (!mounted) return;
      messenger
        ..clearSnackBars()
        ..showSnackBar(SnackBar(
            content: Text(added == 0
                ? l.ordersBuyAgainNone
                : skipped == 0
                    ? l.ordersBuyAgainDone(added)
                    : l.ordersBuyAgainSkipped(added, skipped))));
      if (added > 0) router.go('/cart');
    } catch (e) {
      if (mounted) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final async = ref.watch(orderDetailProvider(widget.id));
    final o = async.value?.data;

    return SoukPage(
      title: o == null ? l.ordersOrder : l.ordersOrderNo(o.subOrderNo),
      scrollable: true,
      onRefresh: _refresh,
      fromCache: async.value?.fromCache ?? false,
      cachedAt: async.value?.fetchedAt,
      bottomBar: o == null || !(o.canCancel || o.canConfirmReceived || o.canReturn || o.canReview || o.canPayNow || o.canBuyAgain)
          ? null
          : Wrap(spacing: SoukSpace.s2, runSpacing: SoukSpace.s2, alignment: WrapAlignment.end, children: [
              if (o.canBuyAgain)
                SoukButton(key: const Key('order-buy-again'), label: l.ordersBuyAgain, icon: FontAwesomeIcons.rotateRight, variant: SoukButtonVariant.primary, requiresOnline: true, busy: _busy, onPressed: () => _buyAgain(o)),
              if (o.canPayNow)
                SoukButton(key: const Key('order-pay-now'), label: l.ordersPayNow, icon: FontAwesomeIcons.creditCard, variant: SoukButtonVariant.primary, requiresOnline: true, busy: _busy, onPressed: () => _payNow(o)),
              if (o.canCancel)
                SoukButton(key: const Key('order-cancel'), label: l.ordersCancelOrder, variant: SoukButtonVariant.secondary, requiresOnline: true, busy: _busy, onPressed: _cancel),
              if (o.canReturn)
                SoukButton(key: const Key('order-return'), label: l.ordersRequestReturn, variant: SoukButtonVariant.secondary, requiresOnline: true, onPressed: () => context.push('/orders/${o.id}/return')),
              if (o.canConfirmReceived)
                SoukButton(key: const Key('order-confirm-received'), label: l.ordersConfirmReceived, icon: FontAwesomeIcons.check, variant: SoukButtonVariant.primary, requiresOnline: true, busy: _busy, onPressed: _confirmReceived),
              if (o.canReview)
                SoukButton(key: const Key('order-review'), label: l.ordersWriteReview, icon: FontAwesomeIcons.star, variant: SoukButtonVariant.primary, requiresOnline: true, onPressed: () => context.push('/orders/${o.id}/review')),
            ]),
      body: async.when(
        skipLoadingOnRefresh: true,
        loading: () => const SoukSkeleton.list(rows: 5),
        error: (e, _) => ErrorPane(error: e, onRetry: _refresh, retryLabel: l.retry),
        data: (r) => _Detail(r.data),
      ),
    );
  }
}

class _Detail extends StatelessWidget {
  const _Detail(this.o);

  final SubOrder o;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final a = o.address;
    final t = o.tracking;
    final rr = o.returnRequest;
    final trackingUrl = asStr(t?['url']);
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      Row(children: [
        // The checkout's payment number: one payment can cover several shop orders.
        Expanded(child: Text(l.ordersPaymentNo(o.orderNo), style: SoukText.mono)),
        SoukBadge(o.statusLabel, key: const Key('order-status'), status: o.status),
      ]),
      if (o.placedAt != null) Text(l.ordersPlacedAt(soukDateTime(context, o.placedAt!)), style: SoukText.meta),
      if (rr != null) ...[
        const SizedBox(height: SoukSpace.s3),
        SoukAlert('${l.ordersReturnRequest}: ${returnStatusLabel(l, asStr(rr['status']) ?? '')}${rr['reason'] == null ? '' : ' · ${rr['reason']}'}', status: 'warn'),
      ],
      // Timeline
      if (o.history.isNotEmpty) ...[
        const SizedBox(height: SoukSpace.s4),
        SoukCard(
          key: const Key('order-timeline'),
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            for (var i = 0; i < o.history.length; i++) _TimelineRow(o.history[i], last: i == o.history.length - 1),
          ]),
        ),
      ],
      // Items
      const SizedBox(height: SoukSpace.s4),
      SoukCard(
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Row(children: [
            const FaIcon(FontAwesomeIcons.store, size: 14, color: SoukColors.inkFaint),
            const SizedBox(width: SoukSpace.s2),
            Expanded(child: Text(o.storeName, style: SoukText.displayName, maxLines: 1, overflow: TextOverflow.ellipsis)),
            if (o.storeSlug != null)
              TextButton(onPressed: () => context.push('/s/${o.storeSlug}'), child: Text(l.visitStore)),
          ]),
          const SizedBox(height: SoukSpace.s2),
          for (final it in o.items)
            Padding(
              padding: const EdgeInsets.only(bottom: SoukSpace.s3),
              child: Row(children: [
                SizedBox(width: 48, height: 48, child: ProductImage(it.image, radius: SoukRadius.control)),
                const SizedBox(width: SoukSpace.s3),
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(it.name, style: SoukText.small, maxLines: 2, overflow: TextOverflow.ellipsis),
                    Text('${it.variantLabel == null ? '' : '${it.variantLabel} · '}× ${it.qty}', style: SoukText.meta),
                  ]),
                ),
                PriceText(it.lineTotalSen),
              ]),
            ),
          const Divider(height: SoukSpace.s4),
          _Row(l.checkoutSubtotal, o.subtotalSen),
          _Row(l.checkoutShipping, o.shippingSen),
          if (o.discountSen > 0) _Row(l.checkoutShopDiscount, -o.discountSen),
          _Row(l.ordersTotal, o.totalSen, bold: true),
        ]),
      ),
      // Address + payment
      const SizedBox(height: SoukSpace.s4),
      SoukCard(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(l.checkoutDeliverTo.toUpperCase(), style: SoukText.eyebrow),
          const SizedBox(height: SoukSpace.s1),
          if (a == null)
            Text('—', style: SoukText.bodySoft)
          else ...[
            Text('${asStr(a['recipient_name']) ?? ''} · ${asStr(a['phone']) ?? ''}', style: SoukText.label),
            Text(
              [a['line1'], a['line2'], '${a['postcode'] ?? ''} ${a['city'] ?? ''}'.trim(), a['state']]
                  .where((s) => s != null && '$s'.isNotEmpty)
                  .join(', '),
              style: SoukText.bodySoft,
            ),
          ],
          const SizedBox(height: SoukSpace.s3),
          Text(l.checkoutPaymentMethod.toUpperCase(), style: SoukText.eyebrow),
          const SizedBox(height: SoukSpace.s1),
          Row(children: [
            Expanded(child: Text(paymentMethodLabel(l, o.paymentMethod), style: SoukText.body)),
            SoukBadge(paymentStatusLabel(l, o.paymentStatus), status: o.paymentStatus),
          ]),
          if (t != null) ...[
            const SizedBox(height: SoukSpace.s3),
            Text(l.ordersTracking.toUpperCase(), style: SoukText.eyebrow),
            const SizedBox(height: SoukSpace.s1),
            Row(children: [
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(asStr(t['courier']) ?? '', style: SoukText.body),
                  Text(asStr(t['no']) ?? '', key: const Key('order-tracking-no'), style: SoukText.mono),
                ]),
              ),
              if (trackingUrl != null)
                SoukButton(
                  key: const Key('order-track'),
                  label: l.ordersTrack,
                  icon: FontAwesomeIcons.arrowUpRightFromSquare,
                  compact: true,
                  onPressed: () => launchUrl(Uri.parse(trackingUrl), mode: LaunchMode.externalApplication),
                ),
            ]),
          ],
        ]),
      ),
    ]);
  }
}

class _Row extends StatelessWidget {
  const _Row(this.label, this.sen, {this.bold = false});

  final String label;
  final int sen;
  final bool bold;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 2),
        child: Row(children: [
          Expanded(child: Text(label, style: bold ? SoukText.label : SoukText.small)),
          PriceText(sen, style: bold ? SoukText.price.copyWith(color: SoukColors.emerald) : SoukText.price.copyWith(fontSize: 13.5)),
        ]),
      );
}

class _TimelineRow extends StatelessWidget {
  const _TimelineRow(this.h, {required this.last});

  final Map<String, dynamic> h;
  final bool last;

  @override
  Widget build(BuildContext context) {
    final at = asDate(h['at']);
    final note = asStr(h['note']);
    final c = soukStatusColor(asStr(h['status']) ?? '');
    return IntrinsicHeight(
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Column(children: [
          Container(
            width: 12,
            height: 12,
            margin: const EdgeInsets.only(top: 4),
            decoration: BoxDecoration(color: last ? SoukColors.emerald : SoukColors.lineStrong, shape: BoxShape.circle),
          ),
          if (!last) const Expanded(child: VerticalDivider(width: 12, thickness: 1, color: SoukColors.line)),
        ]),
        const SizedBox(width: SoukSpace.s3),
        Expanded(
          child: Padding(
            padding: EdgeInsets.only(bottom: last ? 0 : SoukSpace.s3),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(asStr(h['label']) ?? asStr(h['status']) ?? '', style: SoukText.label.copyWith(color: last ? c.fg : SoukColors.ink)),
              if (at != null) Text(soukDateTime(context, at), style: SoukText.meta),
              if (note != null) Text(note, style: SoukText.meta),
            ]),
          ),
        ),
      ]),
    );
  }
}
