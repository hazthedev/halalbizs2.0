import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_client.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import '../seller_shared/models.dart';
import '../seller_shared/rm.dart';
import '../seller_shared/seller_api.dart';
import '../seller_shared/seller_kit.dart';

/// `/seller/earnings` tab root: balances, payout request, ledger (paged
/// through `GET seller/earnings?page=`), payouts.
class SellerEarningsScreen extends ConsumerStatefulWidget {
  const SellerEarningsScreen({super.key});

  @override
  ConsumerState<SellerEarningsScreen> createState() => _SellerEarningsScreenState();
}

class _SellerEarningsScreenState extends ConsumerState<SellerEarningsScreen> {
  /// Ledger pages after the first (which arrives with the provider).
  final _more = <LedgerEntry>[];
  int _page = 1;
  bool _loadingMore = false;

  Future<void> _refresh() async {
    _more.clear();
    _page = 1;
    ref.invalidate(sellerEarningsProvider);
    try {
      await ref.read(sellerEarningsProvider.future);
    } catch (_) {
      // surfaced by the error pane
    }
  }

  Future<void> _loadMore() async {
    setState(() => _loadingMore = true);
    try {
      final r = await ref.read(sellerApiProvider).earnings(page: _page + 1);
      if (!mounted) return;
      setState(() {
        _more.addAll(r.data.ledger);
        _page = SellerPage<void>(const [], meta: r.meta).page;
      });
    } catch (e) {
      if (mounted) showSellerError(context, e);
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _requestPayout(Earnings e) async {
    final l = context.l10n;
    final ok = await showSellerSheet<bool>(
      context,
      title: l.sellerRequestPayout,
      child: _PayoutSheet(minSen: e.payoutMinSen, availableSen: e.availableSen),
    );
    if (ok == true && mounted) {
      ScaffoldMessenger.of(context)
        ..clearSnackBars()
        ..showSnackBar(SnackBar(content: Text(l.sellerPayoutRequested)));
      ref.read(sellerRefreshProvider.notifier).bump(); // the dashboard's available balance
      await _refresh();
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final async = ref.watch(sellerEarningsProvider);
    final lastPage = SellerPage<void>(const [], meta: async.value?.meta).lastPage;

    return SoukPage(
      title: l.sellerTabEarnings,
      actions: const [SellerBackToBuying()],
      showBack: false,
      scrollable: true,
      onRefresh: _refresh,
      fromCache: async.value?.fromCache ?? false,
      cachedAt: async.value?.fetchedAt,
      body: async.when(
        skipLoadingOnRefresh: true,
        loading: () => const SoukSkeleton.list(rows: 6),
        error: (e, _) => SellerErrorPane(error: e, onRetry: _refresh),
        data: (r) {
          final e = r.data;
          final open = e.openPayout;
          final ledger = [...e.ledger, ..._more];
          final canRequest = open == null && e.availableSen >= e.payoutMinSen && e.availableSen > 0;
          return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Row(children: [
              Expanded(child: KpiTile(key: const Key('seller-earnings-available'), label: l.sellerAvailable, value: PriceText(e.availableSen, large: true))),
              const SizedBox(width: SoukSpace.s3),
              Expanded(child: KpiTile(label: l.sellerHeld, value: PriceText(e.heldSen, large: true))),
            ]),
            const SizedBox(height: SoukSpace.s3),
            KpiTile(label: l.sellerPaidOut, value: PriceText(e.paidOutSen, large: true)),
            const SizedBox(height: SoukSpace.s4),
            if (open != null)
              SoukCard(
                key: const Key('seller-payout-open'),
                cream: true,
                child: Row(children: [
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(l.sellerPayoutPending, style: SoukText.label),
                      Text('${open.payoutNo} · ${sellerDate(context, open.requestedAt)}', style: SoukText.meta),
                    ]),
                  ),
                  PriceText(open.amountSen),
                  const SizedBox(width: SoukSpace.s2),
                  SoukBadge(open.statusLabel, status: open.status),
                ]),
              )
            else
              SoukButton(
                key: const Key('seller-earnings-request-payout'),
                label: l.sellerRequestPayout,
                variant: SoukButtonVariant.primary,
                expand: true,
                requiresOnline: true,
                disabledReason: canRequest ? null : l.sellerPayoutMin(formatSen(e.payoutMinSen)),
                onPressed: () => _requestPayout(e),
              ),
            const SizedBox(height: SoukSpace.s6),
            SoukSectionHeading(l.sellerLedger),
            const SizedBox(height: SoukSpace.s2),
            if (ledger.isEmpty)
              SoukEmptyState(key: const Key('seller-ledger-empty'), title: l.sellerNoLedger, message: l.sellerNoLedgerBody, compact: true)
            else
              SoukCard(
                padding: EdgeInsets.zero,
                child: Material(
                  type: MaterialType.transparency,
                  child: Column(children: [
                    for (final entry in ledger) _LedgerRow(entry),
                  if (_page < lastPage)
                    Padding(
                      padding: const EdgeInsets.all(SoukSpace.s3),
                      child: SoukButton(
                        key: const Key('seller-ledger-more'),
                        label: l.sellerLoadMore,
                        busy: _loadingMore,
                        onPressed: _loadMore,
                      ),
                    ),
                  ]),
                ),
              ),
            const SizedBox(height: SoukSpace.s6),
            SoukSectionHeading(l.sellerPayouts),
            const SizedBox(height: SoukSpace.s2),
            if (e.payouts.isEmpty)
              Text(l.sellerNoPayouts, style: SoukText.meta)
            else
              SoukCard(
                padding: EdgeInsets.zero,
                child: Material(
                  type: MaterialType.transparency,
                  child: Column(children: [
                    for (final p in e.payouts)
                      ListTile(
                      key: Key('seller-payout-${p.id}'),
                      title: Text(p.payoutNo, style: SoukText.mono.copyWith(color: SoukColors.ink)),
                      subtitle: Text(sellerDate(context, p.paidAt ?? p.requestedAt), style: SoukText.meta),
                      trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                        PriceText(p.amountSen),
                        const SizedBox(width: SoukSpace.s2),
                        SoukBadge(p.statusLabel, status: p.status),
                      ]),
                      shape: const Border(bottom: BorderSide(color: SoukColors.line)),
                    ),
                  ]),
                ),
              ),
          ]);
        },
      ),
    );
  }
}

/// Ledger line: type label, reference, date; signed mono amount (emerald
/// in, danger out).
class _LedgerRow extends StatelessWidget {
  const _LedgerRow(this.e);

  final LedgerEntry e;

  @override
  Widget build(BuildContext context) {
    final positive = e.amountSen >= 0;
    final color = positive ? SoukColors.emerald : SoukColors.danger;
    return ListTile(
      key: Key('seller-ledger-${e.id}'),
      title: Text(e.typeLabel, style: SoukText.label),
      subtitle: Text(
        [e.reference, sellerDate(context, e.createdAt)].nonNulls.where((s) => s.isNotEmpty).join(' · '),
        style: SoukText.meta,
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
      ),
      // formatSen already carries the minus; fold the plus into the same string.
      trailing: Text('${positive ? '+' : ''}${formatSen(e.amountSen)}', style: SoukText.price.copyWith(color: color)),
      shape: const Border(bottom: BorderSide(color: SoukColors.line)),
    );
  }
}

/// `POST seller/payouts {amount_sen}`. Submit stays disabled until the
/// amount parses and sits within [minSen, availableSen]. Pops true on
/// success; a 409 `payout_rejected` shows the server's message in place.
class _PayoutSheet extends ConsumerStatefulWidget {
  const _PayoutSheet({required this.minSen, required this.availableSen});

  final int minSen;
  final int availableSen;

  @override
  ConsumerState<_PayoutSheet> createState() => _PayoutSheetState();
}

class _PayoutSheetState extends ConsumerState<_PayoutSheet> {
  late final _amount = TextEditingController(text: rmInput(widget.availableSen));
  String? _error;
  bool _busy = false;

  @override
  void dispose() {
    _amount.dispose();
    super.dispose();
  }

  int? get _sen => parseRm(_amount.text);

  String? _problem(AppLocalizations l) {
    final sen = _sen;
    if (sen == null) return _amount.text.trim().isEmpty ? null : l.sellerPriceFormat;
    if (sen < widget.minSen) return l.sellerPayoutBelowMin;
    if (sen > widget.availableSen) return l.sellerPayoutAboveAvailable;
    return null;
  }

  Future<void> _submit() async {
    final sen = _sen;
    if (sen == null) return;
    final l = context.l10n;
    final ok = await showSoukConfirm(context,
        title: l.sellerRequestPayout, message: l.sellerPayoutConfirm(formatSen(sen)), confirmLabel: l.sellerRequestPayout, danger: true);
    if (!ok || !mounted) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref.read(sellerApiProvider).requestPayout(sen);
      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = fieldError(e, 'amount_sen') ?? e.message);
    } catch (e) {
      if (mounted) showSellerError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final problem = _problem(l);
    final valid = _sen != null && problem == null;
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      SoukTextField(
        fieldKey: const Key('seller-payout-amount'),
        label: l.sellerPayoutAmountRm,
        controller: _amount,
        required: true,
        keyboardType: const TextInputType.numberWithOptions(decimal: true),
        autocorrect: false,
        hint: '0.00',
        error: _error ?? problem,
        help: '${l.sellerPayoutMin(formatSen(widget.minSen))} · ${l.sellerPayoutMax(formatSen(widget.availableSen))}',
        onChanged: (_) => setState(() => _error = null),
        textInputAction: TextInputAction.done,
      ),
      const SizedBox(height: SoukSpace.s5),
      SoukButton(
        key: const Key('seller-payout-submit'),
        label: l.sellerRequestPayout,
        variant: SoukButtonVariant.primary,
        expand: true,
        busy: _busy,
        requiresOnline: true,
        disabledReason: valid ? null : (problem ?? l.fieldRequired),
        onPressed: valid ? _submit : null,
      ),
    ]);
  }
}

List<RouteBase> get sellerEarningsSubRoutes => const [];
