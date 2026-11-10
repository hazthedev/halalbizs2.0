import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_client.dart';
import '../../core/api/json.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import '../account/account_api.dart';
import '../cart/cart_api.dart';
import '../catalog/halal.dart';
import 'checkout_api.dart';

/// Translated label for a `payment_method` code.
String paymentMethodLabel(AppLocalizations l, String method) => switch (method) {
      'cod' => l.checkoutMethodCod,
      'ipay88' => l.checkoutMethodIpay88,
      _ => method,
    };

/// `/checkout` — address, per-store lines + notes, vouchers, coins, payment
/// method, the quote's totals and "Place order". Every change re-quotes;
/// the app never computes an amount.
class CheckoutScreen extends ConsumerStatefulWidget {
  const CheckoutScreen({super.key});

  @override
  ConsumerState<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends ConsumerState<CheckoutScreen> {
  int? _addressId;
  String? _method;
  String? _platformCode;
  String? _shopCode;
  bool _useCoins = false;
  int _coins = 0;
  final _platformCtl = TextEditingController();
  final _shopCtl = TextEditingController();
  final _coinsCtl = TextEditingController();
  final _notes = <int, TextEditingController>{};

  CheckoutQuote? _quote;
  Object? _error;

  /// 409 `checkout_failed` from the last attempt: `fields` keyed by variant id.
  ApiException? _placeError;
  bool _quoting = false;
  bool _placing = false;
  // One key per attempt; a retry after a timeout reuses it, a placed order renews it.
  String _idempotencyKey = newIdempotencyKey();
  int _seq = 0;

  @override
  void initState() {
    super.initState();
    _start();
  }

  @override
  void dispose() {
    _platformCtl.dispose();
    _shopCtl.dispose();
    _coinsCtl.dispose();
    for (final c in _notes.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _start() async {
    try {
      final addresses = await ref.read(addressesProvider.future);
      if (!mounted) return;
      _addressId ??= (addresses.where((a) => a.isDefault).firstOrNull ?? addresses.firstOrNull)?.id;
    } catch (_) {
      // The address card shows the retry.
    }
    await _requote();
  }

  Future<void> _requote() async {
    final seq = ++_seq;
    setState(() {
      _quoting = true;
      _error = null;
    });
    try {
      final q = await ref.read(checkoutApiProvider).quote(
            addressId: _addressId,
            paymentMethod: _method,
            platformCode: _platformCode,
            shopCode: _shopCode,
            coins: _useCoins ? _coins : 0,
          );
      if (!mounted || seq != _seq) return;
      setState(() {
        _quote = q;
        // First quote: default to the first available method; keep the server's echo otherwise.
        _method ??= q.paymentMethods.where((m) => m.available).firstOrNull?.method;
        if (_useCoins && q.coinsRedeemed != _coins && q.coinsRedeemed > 0) {
          _coins = q.coinsRedeemed;
          _coinsCtl.text = '$_coins';
        }
      });
    } on ApiException catch (e) {
      if (!mounted || seq != _seq) return;
      if (e.isForbidden && e.code == 'email_unverified') {
        context.go('/verify-email');
        return;
      }
      _fail(e);
    } catch (e) {
      if (!mounted || seq != _seq) return;
      _fail(e);
    } finally {
      if (mounted && seq == _seq) setState(() => _quoting = false);
    }
  }

  /// A failed re-quote drops the quote: the totals on screen would be stale,
  /// so "Place order" goes with them until a quote succeeds.
  void _fail(Object e) => setState(() {
        _error = e;
        _quote = null;
      });

  Future<void> _place() async {
    final q = _quote;
    final addressId = _addressId;
    final method = _method;
    if (q == null || addressId == null || method == null) return;
    setState(() {
      _placing = true;
      _placeError = null;
    });
    final l = context.l10n;
    final router = GoRouter.of(context);
    final messenger = ScaffoldMessenger.of(context);
    try {
      final placed = await ref.read(checkoutApiProvider).place(
            addressId: addressId,
            paymentMethod: method,
            idempotencyKey: _idempotencyKey,
            platformCode: _platformCode,
            shopCode: _shopCode,
            coins: _useCoins ? _coins : 0,
            sellerNotes: {for (final e in _notes.entries) if (e.value.text.trim().isNotEmpty) e.key: e.value.text.trim()},
          );
      if (!mounted) return;
      _idempotencyKey = newIdempotencyKey();
      ref.invalidate(cartProvider);
      final orderNo = Uri.encodeComponent(placed.orderNo);
      if (placed.paymentMethod != 'ipay88') {
        router.go('/checkout/done/$orderNo');
      } else if (placed.paymentUrl == null) {
        // Placed but unpaid and no gateway link: the orders list, never the done page.
        messenger
          ..clearSnackBars()
          ..showSnackBar(SnackBar(content: Text(l.checkoutPayLinkMissing)));
        router.go('/orders');
      } else {
        router.go('/checkout/pay/$orderNo', extra: placed.paymentUrl);
      }
    } on ApiException catch (e) {
      if (!mounted) return;
      if (e.isForbidden && e.code == 'email_unverified') {
        router.go('/verify-email');
        return;
      }
      if (e.code == 'timeout' || e.isOffline) {
        await _afterUncertainPlace(e);
        return;
      }
      showApiError(context, e);
      if (e.code == 'checkout_failed') setState(() => _placeError = e);
      // Stock or price moved under us: refresh the quote so the totals are honest.
      if (e.code == 'checkout_failed' || e.isValidation) unawaited(_requote());
    } catch (e) {
      if (mounted) showApiError(context, e);
    } finally {
      // Still mounted means we did not leave for the orders list: safe to allow another attempt.
      if (mounted) setState(() => _placing = false);
    }
  }

  /// The request died in flight (`timeout` / `offline`), so the server may
  /// have placed the order. `place()` consumes the selected lines in the same
  /// transaction, so a fresh `GET cart` is decisive: selection gone means the
  /// order exists. Unknown (cart unreachable) also leaves for the orders list;
  /// a second tap must never create a duplicate. Server-side
  /// `Idempotency-Key` on `checkout/place` is the API follow-up.
  Future<void> _afterUncertainPlace(ApiException e) async {
    final l = context.l10n;
    final router = GoRouter.of(context);
    final messenger = ScaffoldMessenger.of(context);
    Cart? cart;
    try {
      cart = await ref.read(cartProvider.notifier).refresh();
    } catch (_) {}
    if (!mounted) return;
    if (cart != null && cart.selectedCount > 0) {
      showApiError(context, e);
      return;
    }
    messenger
      ..clearSnackBars()
      ..showSnackBar(SnackBar(content: Text(cart == null ? l.checkoutMaybePlaced : l.checkoutPlacedCheckOrders)));
    router.go('/orders');
  }

  Future<void> _pickAddress(List<Address> addresses) async {
    final l = context.l10n;
    final picked = await showModalBottomSheet<int>(
      context: context,
      useRootNavigator: true,
      builder: (ctx) => SafeArea(
        child: ListView(shrinkWrap: true, padding: const EdgeInsets.symmetric(vertical: SoukSpace.s2), children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(SoukSpace.page, SoukSpace.s3, SoukSpace.page, SoukSpace.s2),
            child: Text(l.checkoutDeliverTo, style: SoukText.displayH3),
          ),
          for (final a in addresses)
            SoukRow(
              key: Key('checkout-address-${a.id}'),
              label: a.label ?? a.recipientName,
              subtitle: '${a.recipientName} · ${a.oneLine}',
              trailing: a.id == _addressId ? const Icon(Icons.check_rounded, color: SoukColors.emerald) : const SizedBox.shrink(),
              onTap: () => Navigator.of(ctx).pop(a.id),
            ),
          SoukRow(
            key: const Key('checkout-address-new'),
            icon: FontAwesomeIcons.plus,
            label: l.accountAddAddress,
            onTap: () => Navigator.of(ctx).pop(-1),
          ),
        ]),
      ),
    );
    if (!mounted || picked == null) return;
    if (picked == -1) {
      // The form pops with the created address id.
      final created = await context.push<int>('/account/addresses/new');
      if (!mounted) return;
      ref.invalidate(addressesProvider);
      if (created == null) return;
      _addressId = created;
    } else {
      _addressId = picked;
    }
    await _requote();
  }

  TextEditingController _note(int storeId) => _notes.putIfAbsent(storeId, TextEditingController.new);

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final addresses = ref.watch(addressesProvider);
    final q = _quote;
    final address = addresses.value?.where((a) => a.id == _addressId).firstOrNull;
    final canPlace = q != null &&
        address != null &&
        _method != null &&
        q.paymentMethodAvailable &&
        q.blockReason == null &&
        q.purchasingEnabled &&
        _error == null &&
        !_quoting;

    return SoukPage(
      title: l.checkoutTitle,
      scrollable: true,
      onRefresh: () async {
        ref.invalidate(addressesProvider);
        await _requote();
      },
      bottomBar: q == null
          ? null
          : Row(children: [
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
                  Text(l.checkoutGrandTotal.toUpperCase(), style: SoukText.eyebrow),
                  PriceText(q.grandTotalSen, key: const Key('checkout-grand-total'), large: true),
                ]),
              ),
              SoukButton(
                key: const Key('checkout-place'),
                label: l.checkoutPlaceOrder,
                icon: FontAwesomeIcons.check,
                variant: SoukButtonVariant.primary,
                requiresOnline: true,
                busy: _placing,
                disabledReason: canPlace
                    ? null
                    : address == null
                        ? l.checkoutNoAddress
                        : q.blockReason ?? (_method == null || !q.paymentMethodAvailable ? l.checkoutChooseMethod : null),
                onPressed: canPlace ? _place : null,
              ),
            ]),
      body: _error != null
          ? ErrorPane(error: _error!, onRetry: _requote, retryLabel: l.retry)
          : q == null
              ? const SoukSkeleton.list(rows: 5)
              : Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  if (q.blockReason != null) ...[
                    SoukAlert(q.blockReason!, key: const Key('checkout-block-reason'), status: 'danger'),
                    const SizedBox(height: SoukSpace.s3),
                  ],
                  // Address
                  _Section(
                    title: l.checkoutDeliverTo,
                    child: addresses.when(
                      loading: () => const SoukSkeleton.block(height: 48),
                      error: (e, _) => ErrorPane(error: e, onRetry: () => ref.invalidate(addressesProvider), retryLabel: l.retry),
                      data: (list) => address == null
                          ? Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              Text(l.checkoutNoAddress, style: SoukText.bodySoft),
                              const SizedBox(height: SoukSpace.s3),
                              SoukButton(
                                key: const Key('checkout-add-address'),
                                label: l.accountAddAddress,
                                icon: FontAwesomeIcons.plus,
                                onPressed: () => _pickAddress(list),
                              ),
                            ])
                          : InkWell(
                              key: const Key('checkout-address'),
                              onTap: () => _pickAddress(list),
                              borderRadius: BorderRadius.circular(SoukRadius.control),
                              child: Row(children: [
                                const FaIcon(FontAwesomeIcons.locationDot, size: 16, color: SoukColors.inkFaint),
                                const SizedBox(width: SoukSpace.s3),
                                Expanded(
                                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                    Text('${address.recipientName} · ${address.phone}', style: SoukText.label),
                                    Text(address.oneLine, style: SoukText.meta),
                                  ]),
                                ),
                                Text(l.checkoutChange, style: SoukText.small.copyWith(color: SoukColors.emerald, fontWeight: FontWeight.w600)),
                              ]),
                            ),
                    ),
                  ),
                  // Per store
                  for (final g in q.groups) ...[
                    const SizedBox(height: SoukSpace.s3),
                    _Section(
                      key: Key('checkout-store-${g.storeId}'),
                      title: g.storeName,
                      icon: FontAwesomeIcons.store,
                      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                        for (final line in g.lines)
                          Padding(
                            padding: const EdgeInsets.only(bottom: SoukSpace.s2),
                            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                              Row(children: [
                                Expanded(
                                  child: Text(
                                    '${asStr(line['name']) ?? ''}${line['variant_label'] == null ? '' : ' · ${line['variant_label']}'}  × ${line['qty']}',
                                    style: SoukText.small,
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ),
                                const SizedBox(width: SoukSpace.s2),
                                PriceText(asInt(line['line_total_sen']) ?? 0),
                              ]),
                              if (asStr(line['halal_cert_number']) case final no?)
                                HalalTick(no, key: Key('checkout-halal-${line['variant_id']}')),
                              if (fieldError(_placeError, '${line['variant_id']}') case final err?)
                                Text(err, key: Key('checkout-line-error-${line['variant_id']}'), style: SoukText.error),
                            ]),
                          ),
                        _TotalRow(l.checkoutShipping, g.shippingFeeSen, muted: true, pending: g.shippingFeeSen == null ? l.checkoutShippingPending : null),
                        const SizedBox(height: SoukSpace.s3),
                        SoukTextField(
                          label: l.checkoutSellerNote,
                          fieldKey: Key('checkout-note-${g.storeId}'),
                          controller: _note(g.storeId),
                          hint: l.checkoutSellerNoteHint,
                          maxLines: 3,
                        ),
                      ]),
                    ),
                  ],
                  // Vouchers
                  const SizedBox(height: SoukSpace.s3),
                  _Section(
                    title: l.checkoutVouchers,
                    icon: FontAwesomeIcons.ticket,
                    child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                      _VoucherField(
                        slot: 'platform',
                        label: l.checkoutPlatformVoucher,
                        controller: _platformCtl,
                        quote: q,
                        applied: _platformCode,
                        busy: _quoting,
                        onApply: (code) {
                          _platformCode = code;
                          _requote();
                        },
                      ),
                      const SizedBox(height: SoukSpace.s3),
                      _VoucherField(
                        slot: 'shop',
                        label: l.checkoutShopVoucher,
                        controller: _shopCtl,
                        quote: q,
                        applied: _shopCode,
                        busy: _quoting,
                        onApply: (code) {
                          _shopCode = code;
                          _requote();
                        },
                      ),
                    ]),
                  ),
                  // Coins
                  if (q.coinsEnabled && q.coinBalance > 0) ...[
                    const SizedBox(height: SoukSpace.s3),
                    _Section(
                      title: l.coins,
                      icon: FontAwesomeIcons.coins,
                      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                        SwitchListTile.adaptive(
                          key: const Key('checkout-coins-toggle'),
                          contentPadding: EdgeInsets.zero,
                          activeTrackColor: SoukColors.emerald,
                          title: Text(l.checkoutUseCoins(q.coinBalance), style: SoukText.body),
                          value: _useCoins,
                          onChanged: (v) {
                            setState(() {
                              _useCoins = v;
                              _coins = v ? q.coinBalance : 0;
                              _coinsCtl.text = v ? '${q.coinBalance}' : '';
                            });
                            _requote();
                          },
                        ),
                        if (_useCoins)
                          SoukTextField(
                            label: l.checkoutCoinsToUse,
                            fieldKey: const Key('checkout-coins'),
                            controller: _coinsCtl,
                            keyboardType: TextInputType.number,
                            textInputAction: TextInputAction.done,
                            help: l.checkoutCoinsWorth(q.coinsRedeemed, formatSen(q.coinRedemptionSen)),
                            // Typed but not submitted still counts when placing; submit re-quotes.
                            onChanged: (v) => _coins = (int.tryParse(v) ?? 0).clamp(0, q.coinBalance),
                            onSubmitted: (v) {
                              final n = (int.tryParse(v) ?? 0).clamp(0, q.coinBalance);
                              setState(() {
                                _coins = n;
                                _coinsCtl.text = '$n';
                              });
                              _requote();
                            },
                          ),
                      ]),
                    ),
                  ],
                  // Payment method
                  const SizedBox(height: SoukSpace.s3),
                  _Section(
                    title: l.checkoutPaymentMethod,
                    icon: FontAwesomeIcons.creditCard,
                    child: RadioGroup<String>(
                      groupValue: _method,
                      onChanged: (v) {
                        if (v == null) return;
                        setState(() => _method = v);
                        _requote();
                      },
                      child: Column(children: [
                        for (final m in q.paymentMethods)
                          RadioListTile<String>(
                            key: Key('checkout-method-${m.method}'),
                            contentPadding: EdgeInsets.zero,
                            activeColor: SoukColors.emerald,
                            value: m.method,
                            enabled: m.available,
                            title: Text(paymentMethodLabel(l, m.method),
                                style: SoukText.body.copyWith(color: m.available ? SoukColors.ink : SoukColors.inkFaint)),
                            subtitle: m.available
                                ? null
                                : Text(m.reason ?? l.checkoutMethodUnavailable,
                                    key: Key('checkout-method-reason-${m.method}'), style: SoukText.meta.copyWith(color: SoukColors.warn)),
                          ),
                      ]),
                    ),
                  ),
                  // Totals
                  const SizedBox(height: SoukSpace.s3),
                  _Section(
                    key: const Key('checkout-totals'),
                    title: l.checkoutSummary,
                    child: Column(children: [
                      _TotalRow(l.checkoutSubtotal, q.subtotalSen),
                      _TotalRow(l.checkoutShipping, q.shippingTotalSen),
                      if (q.shippingSubsidySen > 0) _TotalRow(l.checkoutShippingDiscount, -q.shippingSubsidySen, discount: true),
                      if (q.platformDiscountSen > 0) _TotalRow(l.checkoutPlatformDiscount, -q.platformDiscountSen, discount: true),
                      if (q.shopDiscountSen > 0) _TotalRow(l.checkoutShopDiscount, -q.shopDiscountSen, discount: true),
                      if (q.coinRedemptionSen > 0) _TotalRow(l.coins, -q.coinRedemptionSen, discount: true),
                      if (q.taxTotalSen > 0) _TotalRow(l.checkoutTax, q.taxTotalSen),
                      const Divider(height: SoukSpace.s4),
                      _TotalRow(l.checkoutGrandTotal, q.grandTotalSen, bold: true),
                    ]),
                  ),
                ]),
    );
  }
}

class _Section extends StatelessWidget {
  const _Section({super.key, required this.title, required this.child, this.icon});

  final String title;
  final Widget child;
  final FaIconData? icon;

  @override
  Widget build(BuildContext context) => SoukCard(
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Row(children: [
            if (icon != null) ...[FaIcon(icon, size: 14, color: SoukColors.inkFaint), const SizedBox(width: SoukSpace.s2)],
            Expanded(child: Text(title, style: SoukText.displayName, maxLines: 1, overflow: TextOverflow.ellipsis)),
          ]),
          const SizedBox(height: SoukSpace.s3),
          // List tiles (radio, switch) paint ink on the nearest Material; give them one inside the card.
          Material(type: MaterialType.transparency, child: child),
        ]),
      );
}

class _TotalRow extends StatelessWidget {
  const _TotalRow(this.label, this.sen, {this.bold = false, this.discount = false, this.muted = false, this.pending});

  final String label;
  final int? sen;
  final bool bold;
  final bool discount;
  final bool muted;

  /// Shown instead of an amount when [sen] is null.
  final String? pending;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 3),
        child: Row(children: [
          Expanded(child: Text(label, style: muted ? SoukText.meta : (bold ? SoukText.label : SoukText.small))),
          if (sen == null)
            Text(pending ?? '—', style: SoukText.meta)
          else
            PriceText(sen!,
                style: bold
                    ? SoukText.price.copyWith(color: SoukColors.emerald)
                    : SoukText.price.copyWith(fontSize: 13.5, color: discount ? SoukColors.emerald : (muted ? SoukColors.inkFaint : SoukColors.ink))),
        ]),
      );
}

class _VoucherField extends StatelessWidget {
  const _VoucherField({
    required this.slot,
    required this.label,
    required this.controller,
    required this.quote,
    required this.applied,
    required this.busy,
    required this.onApply,
  });

  final String slot;
  final String label;
  final TextEditingController controller;
  final CheckoutQuote quote;
  final String? applied;
  final bool busy;
  final ValueChanged<String?> onApply;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final v = quote.voucher(slot);
    final error = quote.voucherErrors[slot];
    final code = v == null ? null : asStr(v['code']);
    return Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
      Expanded(
        child: SoukTextField(
          label: label,
          fieldKey: Key('checkout-$slot-code'),
          controller: controller,
          hint: l.checkoutVoucherHint,
          autocorrect: false,
          textInputAction: TextInputAction.done,
          error: error,
          help: code == null ? null : l.checkoutVoucherApplied(code, formatSen(asInt(v!['discount_sen']) ?? 0)),
          onSubmitted: (s) => onApply(s.trim().isEmpty ? null : s.trim()),
        ),
      ),
      const SizedBox(width: SoukSpace.s2),
      Padding(
        padding: EdgeInsets.only(bottom: error != null || code != null ? 22 : 0),
        child: SoukButton(
          key: Key('checkout-$slot-apply'),
          label: applied != null && code != null ? l.checkoutVoucherRemove : l.checkoutVoucherApply,
          compact: true,
          busy: busy && applied != null,
          onPressed: () {
            if (applied != null && code != null) {
              controller.clear();
              onApply(null);
            } else {
              final s = controller.text.trim();
              onApply(s.isEmpty ? null : s);
            }
          },
        ),
      ),
    ]);
  }
}
