import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_client.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import '../seller_shared/models.dart';
import '../seller_shared/rm.dart';
import '../seller_shared/seller_api.dart';
import '../seller_shared/seller_kit.dart';

/// `/seller/store` tab root: logo / banner, profile, holiday mode, flat
/// shipping, bank details (`PATCH seller/store`), the review inbox with
/// replies, and the way back to the buyer shell.
class SellerStoreScreen extends ConsumerStatefulWidget {
  const SellerStoreScreen({super.key});

  @override
  ConsumerState<SellerStoreScreen> createState() => _SellerStoreScreenState();
}

class _SellerStoreScreenState extends ConsumerState<SellerStoreScreen> {
  final _description = TextEditingController();
  final _flatFee = TextEditingController();
  final _freeAbove = TextEditingController();
  final _accountName = TextEditingController();
  final _accountNumber = TextEditingController();
  String? _state;
  String? _bankName;
  bool _holiday = false;

  SellerStore? _store;
  bool _fromCache = false;
  DateTime? _fetchedAt;
  Object? _loadError;
  String? _busy;
  ApiException? _remote;
  Map<String, String> _local = const {};
  int _reviewsRefresh = 0;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    for (final c in [_description, _flatFee, _freeAbove, _accountName, _accountNumber]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _load() async {
    setState(() => _loadError = null);
    try {
      final r = await ref.read(sellerApiProvider).store();
      if (!mounted) return;
      setState(() {
        _populate(r.data);
        _fromCache = r.fromCache;
        _fetchedAt = r.fetchedAt;
        _reviewsRefresh++;
      });
    } catch (e) {
      if (mounted) setState(() => _loadError = e);
    }
  }

  void _populate(SellerStore s) {
    _store = s;
    _description.text = s.description ?? '';
    _state = kMalaysianStates.contains(s.state) ? s.state : null;
    _holiday = s.holidayMode;
    _flatFee.text = rmInput(s.flatFeeSen);
    _freeAbove.text = rmInput(s.freeAboveSen);
    _bankName = kMalaysianBanks.contains(s.bankName) ? s.bankName : null;
    _accountName.text = s.bankAccountName ?? '';
    _accountNumber.clear();
  }

  String? _err(String path) => _local[path] ?? fieldError(_remote, path);

  Map<String, dynamic>? _validate() {
    final l = context.l10n;
    final local = <String, String>{};
    final body = <String, dynamic>{'holiday_mode': _holiday};
    if (_description.text.trim().isNotEmpty) body['description'] = _description.text.trim();
    if (_state != null) body['state'] = _state;

    int? rm(TextEditingController c, String path, {bool required = false}) {
      final t = c.text.trim();
      if (t.isEmpty) {
        if (required) local[path] = l.fieldRequired;
        return null;
      }
      final sen = parseRm(t);
      if (sen == null) local[path] = l.sellerPriceFormat;
      return sen;
    }

    if (_store?.shippingMode != 'matrix') {
      body['shipping_mode'] = 'flat';
      body['shipping_flat_fee_sen'] = rm(_flatFee, 'shipping_flat_fee_sen', required: true);
      body['free_shipping_over_sen'] = rm(_freeAbove, 'free_shipping_over_sen');
    }

    final number = _accountNumber.text.trim();
    if (number.isNotEmpty) {
      if (_bankName == null) local['bank.bank_name'] = l.fieldRequired;
      if (_accountName.text.trim().isEmpty) local['bank.account_name'] = l.fieldRequired;
      body['bank'] = {'bank_name': _bankName, 'account_name': _accountName.text.trim(), 'account_number': number};
    }

    setState(() {
      _local = local;
      _remote = null;
    });
    return local.isEmpty ? body : null;
  }

  void _snack(String message) => ScaffoldMessenger.of(context)
    ..clearSnackBars()
    ..showSnackBar(SnackBar(content: Text(message)));

  Future<void> _save() async {
    final body = _validate();
    if (body == null) return;
    setState(() => _busy = 'save');
    final l = context.l10n;
    try {
      final s = await ref.read(sellerApiProvider).updateStore(body);
      if (!mounted) return;
      setState(() => _populate(s));
      _snack(l.sellerStoreSaved);
    } on ApiException catch (e) {
      if (!mounted) return;
      if (e.isValidation) {
        setState(() => _remote = e);
      } else {
        showSellerError(context, e);
      }
    } catch (e) {
      if (mounted) showSellerError(context, e);
    } finally {
      if (mounted) setState(() => _busy = null);
    }
  }

  Future<void> _upload(String kind) async {
    final file = await pickSellerImage(context);
    if (file == null || !mounted) return;
    setState(() => _busy = kind);
    try {
      final s = await ref.read(sellerApiProvider).uploadStoreImage(kind, file);
      if (mounted) setState(() => _populate(s));
    } catch (e) {
      if (mounted) showSellerError(context, e);
    } finally {
      if (mounted) setState(() => _busy = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final s = _store;
    final api = ref.watch(sellerApiProvider);

    return SoukPage(
      title: l.sellerTabStore,
      leading: const SellerBackToBuying(),
      showBack: false,
      scrollable: true,
      onRefresh: _load,
      fromCache: _fromCache,
      cachedAt: _fetchedAt,
      body: s == null
          ? (_loadError != null
              ? SellerErrorPane(error: _loadError!, onRetry: _load)
              : const SoukSkeleton.list(rows: 6))
          : Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              // Banner with the logo overlapping its bottom-left corner.
              SizedBox(
                height: 170,
                child: Stack(clipBehavior: Clip.none, children: [
                  Positioned.fill(
                    bottom: 30,
                    child: InkWell(
                      key: const Key('seller-store-banner'),
                      onTap: _busy == null ? () => _upload('banner') : null,
                      child: Stack(fit: StackFit.expand, children: [
                        ProductImage(s.banner),
                        Positioned(
                          right: SoukSpace.s2,
                          bottom: SoukSpace.s2,
                          child: _CameraChip(label: l.sellerChangeBanner, busy: _busy == 'banner'),
                        ),
                      ]),
                    ),
                  ),
                  Positioned(
                    left: SoukSpace.s4,
                    bottom: 0,
                    child: InkWell(
                      key: const Key('seller-store-logo'),
                      onTap: _busy == null ? () => _upload('logo') : null,
                      child: Container(
                        width: 76,
                        height: 76,
                        decoration: BoxDecoration(
                          color: SoukColors.surface,
                          borderRadius: BorderRadius.circular(SoukRadius.card),
                          border: Border.all(color: SoukColors.line),
                          boxShadow: SoukShadow.card,
                        ),
                        padding: const EdgeInsets.all(3),
                        child: Stack(fit: StackFit.expand, children: [
                          ProductImage(s.logo, radius: SoukRadius.control),
                          if (_busy == 'logo') const Center(child: SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))),
                        ]),
                      ),
                    ),
                  ),
                ]),
              ),
              const SizedBox(height: SoukSpace.s3),
              Row(children: [
                Expanded(child: Text(s.name, key: const Key('seller-store-name'), style: SoukText.displayH2)),
                SoukBadge(s.statusLabel, status: s.status),
              ]),
              const SizedBox(height: SoukSpace.s4),
              SoukTextField(
                fieldKey: const Key('seller-store-description'),
                label: l.sellerStoreDescription,
                controller: _description,
                maxLines: 4,
                error: _err('description'),
              ),
              const SizedBox(height: SoukSpace.s4),
              SoukDropdown<String>(
                key: const Key('seller-store-state'),
                label: l.sellerState,
                options: {for (final st in kMalaysianStates) st: st},
                value: _state,
                hint: l.sellerState,
                error: _err('state'),
                onChanged: (v) => setState(() => _state = v),
              ),
              SwitchListTile.adaptive(
                key: const Key('seller-store-holiday'),
                contentPadding: EdgeInsets.zero,
                activeThumbColor: SoukColors.emerald,
                title: Text(l.sellerHolidayMode, style: SoukText.label),
                subtitle: Text(l.sellerHolidayModeHelp, style: SoukText.meta),
                value: _holiday,
                onChanged: (v) => setState(() => _holiday = v),
              ),
              const SizedBox(height: SoukSpace.s4),
              Text(l.sellerShipping, style: SoukText.displayH3),
              const SizedBox(height: SoukSpace.s3),
              if (s.shippingMode == 'matrix')
                SoukAlert(l.sellerShippingMatrix, key: const Key('seller-store-matrix'))
              else
                Row(children: [
                  Expanded(
                    child: SoukTextField(
                      fieldKey: const Key('seller-store-flat-fee'),
                      label: l.sellerFlatFeeRm,
                      controller: _flatFee,
                      required: true,
                      hint: '0.00',
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      autocorrect: false,
                      error: _err('shipping_flat_fee_sen'),
                    ),
                  ),
                  const SizedBox(width: SoukSpace.s3),
                  Expanded(
                    child: SoukTextField(
                      fieldKey: const Key('seller-store-free-above'),
                      label: l.sellerFreeAboveRm,
                      controller: _freeAbove,
                      hint: '0.00',
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      autocorrect: false,
                      error: _err('free_shipping_over_sen'),
                    ),
                  ),
                ]),
              const SizedBox(height: SoukSpace.s5),
              Text(l.sellerBank, style: SoukText.displayH3),
              const SizedBox(height: SoukSpace.s1),
              Text(l.sellerBankHelp, style: SoukText.meta),
              const SizedBox(height: SoukSpace.s3),
              SoukDropdown<String>(
                key: const Key('seller-store-bank-name'),
                label: l.sellerBankName,
                options: {for (final b in kMalaysianBanks) b: b},
                value: _bankName,
                hint: l.sellerBankName,
                error: _err('bank.bank_name'),
                onChanged: (v) => setState(() => _bankName = v),
              ),
              const SizedBox(height: SoukSpace.s4),
              SoukTextField(
                fieldKey: const Key('seller-store-account-name'),
                label: l.sellerAccountName,
                controller: _accountName,
                error: _err('bank.account_name'),
              ),
              const SizedBox(height: SoukSpace.s4),
              SoukTextField(
                fieldKey: const Key('seller-store-account-number'),
                label: l.sellerAccountNumber,
                controller: _accountNumber,
                hint: s.bankAccountNoMasked,
                help: s.bankAccountNoMasked == null ? null : l.sellerAccountNumberHelp(s.bankAccountNoMasked!),
                keyboardType: TextInputType.number,
                autocorrect: false,
                error: _err('bank.account_number'),
              ),
              const SizedBox(height: SoukSpace.s5),
              SoukButton(
                key: const Key('seller-store-save'),
                label: l.sellerSave,
                variant: SoukButtonVariant.primary,
                expand: true,
                busy: _busy == 'save',
                requiresOnline: true,
                onPressed: _busy == null ? _save : null,
              ),
              const SizedBox(height: SoukSpace.s8),
              SoukSectionHeading(l.sellerReviews),
              const SizedBox(height: SoukSpace.s3),
              SellerPagedList<SellerReview>(
                signature: 'reviews#$_reviewsRefresh',
                fetch: (page) => api.reviews(page: page),
                itemBuilder: (context, r) => _ReviewCard(r),
                empty: SoukEmptyState(
                  key: const Key('seller-reviews-empty'),
                  title: l.sellerNoReviews,
                  message: l.sellerNoReviewsBody,
                  compact: true,
                ),
              ),
              const SizedBox(height: SoukSpace.s8),
              SoukCard(
                key: const Key('seller-store-back-to-buying'),
                onTap: () => context.go('/home'),
                padding: const EdgeInsets.symmetric(horizontal: SoukSpace.s4, vertical: SoukSpace.s3),
                child: Row(children: [
                  const FaIcon(FontAwesomeIcons.bagShopping, size: 18, color: SoukColors.inkFaint),
                  const SizedBox(width: SoukSpace.s4),
                  Expanded(child: Text(l.sellerBackToBuying, style: SoukText.body)),
                  const Icon(Icons.chevron_right_rounded, color: SoukColors.inkFaint),
                ]),
              ),
            ]),
    );
  }
}

class _CameraChip extends StatelessWidget {
  const _CameraChip({required this.label, required this.busy});

  final String label;
  final bool busy;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: SoukSpace.s3, vertical: 6),
        decoration: BoxDecoration(
          color: SoukColors.surface.withValues(alpha: .92),
          borderRadius: BorderRadius.circular(SoukRadius.pill),
          border: Border.all(color: SoukColors.line),
        ),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          if (busy)
            const SizedBox(width: 12, height: 12, child: CircularProgressIndicator(strokeWidth: 2))
          else
            const FaIcon(FontAwesomeIcons.camera, size: 12, color: SoukColors.ink),
          const SizedBox(width: SoukSpace.s2),
          Text(label, style: SoukText.small.copyWith(fontWeight: FontWeight.w600)),
        ]),
      );
}

/// One review: brass stars, body, buyer · product, the reply or the reply
/// box when `can_reply`. A 409 `reply_locked` surfaces as a snackbar.
class _ReviewCard extends ConsumerStatefulWidget {
  const _ReviewCard(this.initial);

  final SellerReview initial;

  @override
  ConsumerState<_ReviewCard> createState() => _ReviewCardState();
}

class _ReviewCardState extends ConsumerState<_ReviewCard> {
  late SellerReview _review = widget.initial;
  final _reply = TextEditingController();
  String? _error;
  bool _busy = false;

  @override
  void dispose() {
    _reply.dispose();
    super.dispose();
  }

  Future<void> _post() async {
    final l = context.l10n;
    final body = _reply.text.trim();
    if (body.length < 5) {
      setState(() => _error = l.sellerReplyMin);
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final r = await ref.read(sellerApiProvider).reply(_review.id, body);
      if (!mounted) return;
      setState(() => _review = r);
      _reply.clear();
    } on ApiException catch (e) {
      if (!mounted) return;
      if (e.isValidation) {
        setState(() => _error = fieldError(e, 'body') ?? e.message);
      } else {
        showSellerError(context, e);
      }
    } catch (e) {
      if (mounted) showSellerError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final r = _review;
    return SoukCard(
      key: Key('seller-review-${r.id}'),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          for (var i = 1; i <= 5; i++)
            Padding(
              padding: const EdgeInsets.only(right: 2),
              child: FaIcon(i <= r.rating ? FontAwesomeIcons.solidStar : FontAwesomeIcons.star, size: 12, color: SoukColors.brass),
            ),
          const SizedBox(width: SoukSpace.s2),
          Expanded(child: Text(sellerDate(r.createdAt), style: SoukText.meta, textAlign: TextAlign.end)),
        ]),
        if (r.body != null) ...[
          const SizedBox(height: SoukSpace.s2),
          Text(r.body!, style: SoukText.body),
        ],
        const SizedBox(height: SoukSpace.s2),
        Text([r.buyerName, r.productName].nonNulls.where((s) => s.isNotEmpty).join(' · '), style: SoukText.meta),
        if (r.replyBody != null) ...[
          const SizedBox(height: SoukSpace.s3),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(SoukSpace.s3),
            decoration: BoxDecoration(color: SoukColors.cream, borderRadius: BorderRadius.circular(SoukRadius.control)),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(l.sellerYourReply.toUpperCase(), style: SoukText.eyebrow),
              const SizedBox(height: SoukSpace.s1),
              Text(r.replyBody!, style: SoukText.small),
              if (r.replyAt != null) Text(sellerDate(r.replyAt), style: SoukText.meta),
            ]),
          ),
        ],
        if (r.canReply) ...[
          const SizedBox(height: SoukSpace.s3),
          SoukTextField(
            fieldKey: Key('seller-review-${r.id}-reply'),
            label: r.replyBody == null ? l.sellerReplyHint : l.sellerYourReply,
            controller: _reply,
            maxLines: 3,
            error: _error,
          ),
          const SizedBox(height: SoukSpace.s2),
          Align(
            alignment: Alignment.centerRight,
            child: SoukButton(
              key: Key('seller-review-${r.id}-post'),
              label: l.sellerPostReply,
              compact: true,
              busy: _busy,
              requiresOnline: true,
              onPressed: _post,
            ),
          ),
        ],
      ]),
    );
  }
}

List<RouteBase> get sellerStoreSubRoutes => const [];
