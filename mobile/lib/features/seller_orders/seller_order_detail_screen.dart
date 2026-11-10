import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/api/api_client.dart';
import '../../core/api/json.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import '../seller_shared/models.dart';
import '../seller_shared/seller_api.dart';
import '../seller_shared/seller_kit.dart';

/// `/seller/orders/:id` — buyer, items, totals, payment, tracking, return
/// request, history, and the fulfilment action for the current state.
class SellerOrderDetailScreen extends ConsumerStatefulWidget {
  const SellerOrderDetailScreen({super.key, required this.id});

  final int id;

  @override
  ConsumerState<SellerOrderDetailScreen> createState() => _SellerOrderDetailScreenState();
}

class _SellerOrderDetailScreenState extends ConsumerState<SellerOrderDetailScreen> {
  bool _busy = false;

  Future<void> _reload() async {
    ref.invalidate(sellerOrderProvider(widget.id));
    try {
      await ref.read(sellerOrderProvider(widget.id).future);
    } catch (_) {
      // surfaced by the error pane
    }
  }

  /// One-shot action (pack, delivered, return/accept, return/received).
  /// A 409 `invalid_transition` shows the server's message and reloads.
  /// [confirm] asks first (money-moving: accepting a return).
  Future<void> _run(String action, {String? confirm}) async {
    final l = context.l10n;
    if (confirm != null) {
      final ok = await showSoukConfirm(context, title: l.sellerAcceptReturn, message: confirm, danger: true);
      if (!ok || !mounted) return;
    }
    setState(() => _busy = true);
    final messenger = ScaffoldMessenger.of(context);
    try {
      await ref.read(sellerApiProvider).orderAction(widget.id, action);
      if (!mounted) return;
      ref.read(sellerRefreshProvider.notifier).bump();
      messenger
        ..clearSnackBars()
        ..showSnackBar(SnackBar(content: Text(l.sellerOrderUpdated)));
    } catch (e) {
      if (mounted) showSellerError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
    await _reload();
  }

  Future<void> _sheet(Widget sheet, String title) async {
    final ok = await showSellerSheet<bool>(context, title: title, child: sheet);
    if (ok == null || !mounted) return;
    if (ok) ref.read(sellerRefreshProvider.notifier).bump();
    await _reload();
  }

  void _ship(SellerOrder o) {
    ref.invalidate(sellerCouriersProvider); // the list is cheap; never ship with a stale one
    _sheet(_ShipSheet(orderId: o.id), context.l10n.sellerShipTitle);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final async = ref.watch(sellerOrderProvider(widget.id));
    final o = async.value?.data;

    return SoukPage(
      title: o?.subOrderNo ?? l.sellerOrder,
      scrollable: true,
      onRefresh: _reload,
      fromCache: async.value?.fromCache ?? false,
      cachedAt: async.value?.fetchedAt,
      bottomBar: o == null ? null : _actions(l, o),
      body: async.when(
        skipLoadingOnRefresh: true,
        loading: () => const SoukSkeleton.list(rows: 6),
        error: (e, _) => SellerErrorPane(error: e, onRetry: _reload),
        data: (r) => _Body(r.data),
      ),
    );
  }

  Widget? _actions(AppLocalizations l, SellerOrder o) {
    final rr = o.returnRequest;
    final rrStatus = asStr(rr?['status']);
    Widget primary(String key, String label, VoidCallback onTap) => Expanded(
          child: SoukButton(
            key: Key(key),
            label: label,
            variant: SoukButtonVariant.primary,
            expand: true,
            busy: _busy,
            requiresOnline: true,
            onPressed: onTap,
          ),
        );
    if (rrStatus == 'accepted') {
      return Row(children: [primary('seller-order-received', l.sellerConfirmReceived, () => _run('return/received'))]);
    }
    return switch (o.status) {
      'confirmed' => Row(children: [primary('seller-order-pack', l.sellerPack, () => _run('pack'))]),
      'processing' => Row(children: [
          primary('seller-order-ship', l.sellerShip, () => _ship(o)),
        ]),
      'shipped' => Row(children: [primary('seller-order-delivered', l.sellerMarkDelivered, () => _run('delivered'))]),
      'return_requested' when rrStatus == 'requested' => Row(children: [
          Expanded(
            child: SoukButton(
              key: const Key('seller-order-dispute'),
              label: l.sellerDisputeReturn,
              expand: true,
              requiresOnline: true,
              onPressed: _busy ? null : () => _sheet(_DisputeSheet(orderId: o.id), l.sellerDisputeTitle),
            ),
          ),
          const SizedBox(width: SoukSpace.s3),
          primary('seller-order-accept', l.sellerAcceptReturn, () => _run('return/accept', confirm: l.sellerAcceptReturnConfirm)),
        ]),
      _ => null,
    };
  }
}

class _Body extends StatelessWidget {
  const _Body(this.o);

  final SellerOrder o;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final a = o.address;
    // "Ship to" is the address snapshot; "Buyer" is the account (as in the list).
    final addressLines = [
      asStr(a['recipient_name']),
      asStr(a['phone']),
      asStr(a['line1']),
      asStr(a['line2']),
      [
        [asStr(a['postcode']), asStr(a['city'])].nonNulls.where((s) => s.isNotEmpty).join(' '),
        asStr(a['state']),
      ].nonNulls.where((s) => s.isNotEmpty).join(', '),
    ].nonNulls.where((s) => s.isNotEmpty).toList();
    final buyerLines = [o.buyerName, o.buyerPhone].nonNulls.where((s) => s.isNotEmpty).toList();
    Widget box(String key, List<String> lines) => SizedBox(
          width: double.infinity,
          child: SoukCard(
            key: Key(key),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              for (final line in lines) Text(line, style: SoukText.body),
            ]),
          ),
        );
    final tracking = o.tracking;
    final trackingNo = asStr(tracking['no']);
    // Only an https link opens the browser; anything else stays text.
    final trackingUri = Uri.tryParse(asStr(tracking['url']) ?? '');
    final trackingUrl = trackingUri != null && trackingUri.scheme == 'https' ? trackingUri : null;
    final rr = o.returnRequest;
    final paymentLabel = switch (o.paymentMethod) {
      'cod' => l.sellerPaymentCod,
      null => '',
      _ => l.sellerPaymentOnline,
    };

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        Expanded(child: Text(sellerDate(context, o.placedAt), style: SoukText.meta)),
        SoukBadge(o.statusLabel, key: const Key('seller-order-status'), status: o.status),
      ]),
      if (o.orderNo != null) Text('${l.sellerOrder} ${o.orderNo}', style: SoukText.monoLabel),
      const SizedBox(height: SoukSpace.s4),
      if (rr != null) ...[
        SoukCard(
          key: const Key('seller-order-return'),
          color: SoukColors.dangerTint,
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Expanded(child: Text(l.sellerReturnRequest, style: SoukText.displayName)),
              SoukBadge(asStr(rr['status_label']) ?? '', status: asStr(rr['status']) ?? 'danger'),
            ]),
            if (asStr(rr['reason']) != null) ...[
              const SizedBox(height: SoukSpace.s2),
              Text(asStr(rr['reason'])!, style: SoukText.label),
            ],
            if (asStr(rr['description']) != null) ...[
              const SizedBox(height: SoukSpace.s1),
              Text(asStr(rr['description'])!, style: SoukText.bodySoft),
            ],
            if (asStr(rr['respond_by']) != null) ...[
              const SizedBox(height: SoukSpace.s2),
              Text(l.sellerRespondBy(sellerDate(context, asStr(rr['respond_by']))), style: SoukText.meta.copyWith(color: SoukColors.danger)),
            ],
          ]),
        ),
        const SizedBox(height: SoukSpace.s4),
      ],
      Text(l.sellerBuyer, style: SoukText.displayH3),
      const SizedBox(height: SoukSpace.s2),
      box('seller-order-buyer', buyerLines),
      if (addressLines.isNotEmpty) ...[
        const SizedBox(height: SoukSpace.s5),
        Text(l.sellerShipTo, style: SoukText.displayH3),
        const SizedBox(height: SoukSpace.s2),
        box('seller-order-ship-to', addressLines),
      ],
      const SizedBox(height: SoukSpace.s5),
      Text(l.sellerItems, style: SoukText.displayH3),
      const SizedBox(height: SoukSpace.s2),
      SoukCard(
        padding: EdgeInsets.zero,
        child: Column(children: [
          for (final (i, it) in o.items.indexed)
            Padding(
              padding: EdgeInsets.fromLTRB(SoukSpace.s3, i == 0 ? SoukSpace.s3 : 0, SoukSpace.s3, SoukSpace.s3),
              child: Row(children: [
                SizedBox(width: 56, height: 56, child: ProductImage(it.productImage, radius: SoukRadius.control)),
                const SizedBox(width: SoukSpace.s3),
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(it.name, style: SoukText.displayName, maxLines: 2, overflow: TextOverflow.ellipsis),
                    Text('${it.variantName == null ? '' : '${it.variantName} · '}× ${it.qty}', style: SoukText.meta),
                  ]),
                ),
                const SizedBox(width: SoukSpace.s2),
                PriceText(it.lineTotalSen),
              ]),
            ),
        ]),
      ),
      const SizedBox(height: SoukSpace.s5),
      SoukCard(
        key: const Key('seller-order-totals'),
        child: Column(children: [
          SellerLine(l.sellerSubtotal, PriceText(o.subtotalSen)),
          SellerLine(l.sellerShippingFee, PriceText(o.shippingSen)),
          if (o.discountSen > 0) SellerLine(l.sellerDiscount, PriceText(-o.discountSen)),
          if (o.taxSen > 0) SellerLine(l.sellerTax, PriceText(o.taxSen)),
          const Divider(height: SoukSpace.s3),
          SellerLine(l.sellerTotal, PriceText(o.totalSen, large: true), strong: true),
          if (o.commissionSen != null || o.commissionRate != null)
            SellerLine(
              '${l.sellerCommission}${o.commissionRate == null ? '' : ' (${o.commissionRate}%)'}',
              o.commissionSen == null ? const Text('—', style: SoukText.mono) : PriceText(-o.commissionSen!),
            ),
          if (o.commissionSen != null && !o.commissionSettled)
            Align(alignment: Alignment.centerRight, child: Text(l.sellerCommissionEstimated, style: SoukText.meta)),
          SellerLine(
            l.sellerPayment,
            Row(mainAxisSize: MainAxisSize.min, children: [
              Text(paymentLabel, style: SoukText.small),
              if (o.paymentStatus != null) ...[
                const SizedBox(width: SoukSpace.s2),
                SoukBadge(sellerStatusLabel(l, o.paymentStatus!), status: o.paymentStatus),
              ],
            ]),
          ),
        ]),
      ),
      if (trackingNo != null) ...[
        const SizedBox(height: SoukSpace.s5),
        Text(l.sellerTracking, style: SoukText.displayH3),
        const SizedBox(height: SoukSpace.s2),
        SoukCard(
          key: const Key('seller-order-tracking'),
          child: Row(children: [
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(asStr(tracking['courier']) ?? '', style: SoukText.label),
                Text(trackingNo, style: SoukText.mono),
                if (asStr(tracking['status']) != null) Text(asStr(tracking['status'])!, style: SoukText.meta),
              ]),
            ),
            if (trackingUrl != null)
              TextButton.icon(
                onPressed: () => launchUrl(trackingUrl, mode: LaunchMode.externalApplication),
                icon: const FaIcon(FontAwesomeIcons.arrowUpRightFromSquare, size: 13),
                label: Text(l.sellerTrack),
              ),
          ]),
        ),
      ],
      if (o.history.isNotEmpty) ...[
        const SizedBox(height: SoukSpace.s5),
        Text(l.sellerHistory, style: SoukText.displayH3),
        const SizedBox(height: SoukSpace.s2),
        SoukCard(
          key: const Key('seller-order-history'),
          child: Column(children: [
            for (final h in o.history.reversed)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: SoukSpace.s1),
                child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const Padding(
                    padding: EdgeInsets.only(top: 6),
                    child: KhatamMark(size: 12, color: SoukColors.brass),
                  ),
                  const SizedBox(width: SoukSpace.s3),
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(sellerStatusLabel(l, asStr(h['status']) ?? ''), style: SoukText.label),
                      Text(
                        [sellerDate(context, asStr(h['at'])), asStr(h['actor'])].nonNulls.where((s) => s.isNotEmpty).join(' · '),
                        style: SoukText.meta,
                      ),
                      if (asStr(h['note']) != null) Text(asStr(h['note'])!, style: SoukText.small),
                    ]),
                  ),
                ]),
              ),
          ]),
        ),
      ],
    ]);
  }
}

/// `POST seller/orders/{id}/ship {courier, courier_other?, tracking_no}`.
/// Pops true on success, false after a non-validation failure (so the
/// caller reloads), null on cancel.
class _ShipSheet extends ConsumerStatefulWidget {
  const _ShipSheet({required this.orderId});

  final int orderId;

  @override
  ConsumerState<_ShipSheet> createState() => _ShipSheetState();
}

class _ShipSheetState extends ConsumerState<_ShipSheet> {
  String? _courier;
  final _other = TextEditingController();
  final _tracking = TextEditingController();
  Map<String, String> _local = const {};
  ApiException? _remote;
  bool _busy = false;

  @override
  void dispose() {
    _other.dispose();
    _tracking.dispose();
    super.dispose();
  }

  String? _err(String path) => _local[path] ?? fieldError(_remote, path);

  Future<void> _submit() async {
    final l = context.l10n;
    final tracking = _tracking.text.trim();
    final local = <String, String>{
      if (_courier == null) 'courier': l.fieldRequired,
      if (_courier == 'other' && _other.text.trim().isEmpty) 'courier_other': l.fieldRequired,
      if (tracking.length < 6 || tracking.length > 64) 'tracking_no': l.sellerTrackingNoLength,
    };
    setState(() {
      _local = local;
      _remote = null;
    });
    if (local.isNotEmpty) return;
    setState(() => _busy = true);
    try {
      await ref.read(sellerApiProvider).orderAction(widget.orderId, 'ship', body: {
        'courier': _courier,
        if (_courier == 'other') 'courier_other': _other.text.trim(),
        'tracking_no': tracking,
      });
      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      if (!mounted) return;
      if (e.isValidation) {
        setState(() => _remote = e);
      } else {
        showSellerError(context, e);
        Navigator.of(context).pop(false);
      }
    } catch (e) {
      if (!mounted) return;
      showSellerError(context, e);
      Navigator.of(context).pop(false);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final couriers = ref.watch(sellerCouriersProvider).value ?? const <String>[];
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      SoukDropdown<String>(
        key: const Key('seller-ship-courier'),
        label: l.sellerCourier,
        required: true,
        hint: l.sellerCourier,
        options: {for (final c in couriers) c: c, 'other': l.sellerCourierOther},
        value: _courier,
        error: _err('courier'),
        onChanged: (v) => setState(() => _courier = v),
      ),
      if (_courier == 'other') ...[
        const SizedBox(height: SoukSpace.s3),
        SoukTextField(
          fieldKey: const Key('seller-ship-courier-other'),
          label: l.sellerCourierName,
          required: true,
          controller: _other,
          error: _err('courier_other'),
        ),
      ],
      const SizedBox(height: SoukSpace.s3),
      SoukTextField(
        fieldKey: const Key('seller-ship-tracking'),
        label: l.sellerTrackingNo,
        required: true,
        controller: _tracking,
        error: _err('tracking_no'),
        help: l.sellerTrackingNoLength,
        autocorrect: false,
        textInputAction: TextInputAction.done,
        onSubmitted: (_) => _submit(),
      ),
      const SizedBox(height: SoukSpace.s5),
      SoukButton(
        key: const Key('seller-ship-submit'),
        label: l.sellerShip,
        variant: SoukButtonVariant.primary,
        expand: true,
        busy: _busy,
        requiresOnline: true,
        onPressed: _submit,
      ),
    ]);
  }
}

/// `POST seller/orders/{id}/return/dispute {reason}` (10–1000 chars).
class _DisputeSheet extends ConsumerStatefulWidget {
  const _DisputeSheet({required this.orderId});

  final int orderId;

  @override
  ConsumerState<_DisputeSheet> createState() => _DisputeSheetState();
}

class _DisputeSheetState extends ConsumerState<_DisputeSheet> {
  final _reason = TextEditingController();
  String? _error;
  bool _busy = false;

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final l = context.l10n;
    final reason = _reason.text.trim();
    if (reason.length < 10) {
      setState(() => _error = l.sellerDisputeReasonMin);
      return;
    }
    setState(() => _error = null);
    final ok = await showSoukConfirm(context, title: l.sellerDisputeTitle, message: l.sellerDisputeConfirm, danger: true);
    if (!ok || !mounted) return;
    setState(() => _busy = true);
    try {
      await ref.read(sellerApiProvider).orderAction(widget.orderId, 'return/dispute', body: {'reason': reason});
      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      if (!mounted) return;
      if (e.isValidation) {
        setState(() => _error = fieldError(e, 'reason') ?? e.message);
      } else {
        showSellerError(context, e);
        Navigator.of(context).pop(false);
      }
    } catch (e) {
      if (!mounted) return;
      showSellerError(context, e);
      Navigator.of(context).pop(false);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      SoukTextField(
        fieldKey: const Key('seller-dispute-reason'),
        label: l.sellerDisputeReason,
        required: true,
        controller: _reason,
        maxLines: 4,
        error: _error,
        help: l.sellerDisputeReasonMin,
      ),
      const SizedBox(height: SoukSpace.s5),
      SoukButton(
        key: const Key('seller-dispute-submit'),
        label: l.sellerSubmit,
        variant: SoukButtonVariant.primary,
        expand: true,
        busy: _busy,
        requiresOnline: true,
        onPressed: _submit,
      ),
    ]);
  }
}
