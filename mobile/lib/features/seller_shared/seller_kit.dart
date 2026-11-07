import 'package:flutter/material.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';

import '../../core/api/api_client.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import 'models.dart';

/// App-bar leading on every seller tab root: back to the buyer shell.
class SellerBackToBuying extends StatelessWidget {
  const SellerBackToBuying({super.key});

  @override
  Widget build(BuildContext context) => SoukIconButton(
        key: const Key('seller-back-to-buying'),
        icon: FontAwesomeIcons.bagShopping,
        label: context.l10n.sellerBackToBuying,
        onPressed: () => context.go('/home'),
      );
}

/// 403 `seller_required` / `store_suspended`: the store is no longer an
/// approved seller — leave the shell with the server's message. True when handled.
bool sellerGuardBounce(BuildContext context, Object error) {
  if (error is! ApiException || !error.isForbidden) return false;
  ScaffoldMessenger.of(context)
    ..clearSnackBars()
    ..showSnackBar(SnackBar(content: Text(error.message.isEmpty ? context.l10n.sellerRequired : error.message)));
  context.go('/account');
  return true;
}

/// [showApiError] with the seller-guard bounce in front of it.
void showSellerError(BuildContext context, Object error) {
  if (!sellerGuardBounce(context, error)) showApiError(context, error);
}

/// [ErrorPane] that bounces a 403 instead of rendering it.
class SellerErrorPane extends StatefulWidget {
  const SellerErrorPane({super.key, required this.error, this.onRetry});

  final Object error;
  final VoidCallback? onRetry;

  @override
  State<SellerErrorPane> createState() => _SellerErrorPaneState();
}

class _SellerErrorPaneState extends State<SellerErrorPane> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) sellerGuardBounce(context, widget.error);
    });
  }

  @override
  Widget build(BuildContext context) =>
      ErrorPane(error: widget.error, onRetry: widget.onRetry, retryLabel: context.l10n.retry);
}

/// "7 Nov 2026, 15:52" in the UI locale and the device's zone (right on a
/// phone); '' for null / unparsable.
String sellerDate(BuildContext context, String? iso) {
  final d = iso == null ? null : DateTime.tryParse(iso);
  if (d == null) return '';
  return DateFormat('d MMM yyyy, HH:mm', Localizations.localeOf(context).toString()).format(d.toLocal());
}

/// Translated label for a sub-order or product status code; the raw code
/// for anything new.
String sellerStatusLabel(AppLocalizations l, String status) => switch (status) {
      'pending_payment' => l.sellerStatusPendingPayment,
      'confirmed' => l.sellerStatusConfirmed,
      'processing' => l.sellerStatusProcessing,
      'shipped' => l.sellerStatusShipped,
      'delivered' => l.sellerStatusDelivered,
      'completed' => l.sellerStatusCompleted,
      'cancelled' => l.sellerStatusCancelled,
      'return_requested' => l.sellerStatusReturnRequested,
      'returned' => l.sellerStatusReturned,
      'refunded' => l.sellerStatusRefunded,
      'draft' => l.sellerStatusDraft,
      'pending_review' => l.sellerStatusPendingReview,
      'live' => l.sellerStatusLive,
      'delisted' => l.sellerStatusDelisted,
      'banned' => l.sellerStatusBanned,
      // payment statuses
      'pending' => l.sellerStatusPending,
      'paid' => l.sellerStatusPaid,
      'failed' => l.sellerStatusFailed,
      'expired' => l.sellerStatusExpired,
      _ => status,
    };

/// Dashboard / earnings number tile: eyebrow label over a mono value.
class KpiTile extends StatelessWidget {
  const KpiTile({super.key, required this.label, required this.value});

  final String label;
  final Widget value;

  @override
  Widget build(BuildContext context) => SoukCard(
        padding: const EdgeInsets.symmetric(horizontal: SoukSpace.s4, vertical: SoukSpace.s3),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
          Text(label.toUpperCase(), style: SoukText.eyebrow, maxLines: 1, overflow: TextOverflow.ellipsis),
          const SizedBox(height: SoukSpace.s1),
          // A large figure ("RM 2,315.91") must stay whole in a half-width tile.
          FittedBox(fit: BoxFit.scaleDown, alignment: Alignment.centerLeft, child: value),
        ]),
      );
}

/// Plain count in the price font (orders, stock).
class KpiCount extends StatelessWidget {
  const KpiCount(this.count, {super.key});

  final int count;

  @override
  Widget build(BuildContext context) => Text('$count', style: SoukText.priceLarge.copyWith(color: SoukColors.inkHead));
}

/// Order list row (orders tab, dashboard): number, status, buyer, items, total.
class SellerOrderRow extends StatelessWidget {
  const SellerOrderRow(this.order, {super.key, required this.onTap});

  final SellerOrder order;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final o = order;
    final items = o.items;
    return SoukCard(
      key: Key('seller-order-${o.id}'),
      onTap: onTap,
      padding: const EdgeInsets.symmetric(horizontal: SoukSpace.s4, vertical: SoukSpace.s3),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Expanded(child: Text(o.subOrderNo, style: SoukText.mono.copyWith(color: SoukColors.ink))),
          SoukBadge(o.statusLabel, status: o.status),
        ]),
        const SizedBox(height: SoukSpace.s1),
        Text(
          items.isEmpty ? o.buyerName : '${o.buyerName} · ${l.sellerItemsCount(o.itemsCount)}',
          style: SoukText.body,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        const SizedBox(height: SoukSpace.s1),
        Row(children: [
          Expanded(child: Text(sellerDate(context, o.placedAt), style: SoukText.meta)),
          PriceText(o.totalSen),
        ]),
      ]),
    );
  }
}

/// Gallery-or-camera picker. Null when the user cancels, the platform has
/// no picker (web camera, tests), or the file is over [maxKb] (the server's
/// `image|max:` rule — refused here with a snackbar instead of a 422).
Future<XFile?> pickSellerImage(BuildContext context, {required int maxKb}) async {
  final l = context.l10n;
  final source = await showModalBottomSheet<ImageSource>(
    context: context,
    useRootNavigator: true,
    builder: (ctx) => SafeArea(
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        SoukRow(
          key: const Key('seller-pick-gallery'),
          icon: FontAwesomeIcons.images,
          label: l.sellerGallery,
          onTap: () => Navigator.of(ctx).pop(ImageSource.gallery),
        ),
        SoukRow(
          key: const Key('seller-pick-camera'),
          icon: FontAwesomeIcons.camera,
          label: l.sellerCamera,
          onTap: () => Navigator.of(ctx).pop(ImageSource.camera),
        ),
      ]),
    ),
  );
  if (source == null) return null;
  final XFile? file;
  try {
    file = await ImagePicker().pickImage(source: source, maxWidth: 1600, imageQuality: 85);
  } catch (_) {
    return null;
  }
  if (file == null) return null;
  if (await file.length() > maxKb * 1024) {
    if (context.mounted) {
      ScaffoldMessenger.of(context)
        ..clearSnackBars()
        ..showSnackBar(SnackBar(content: Text(l.sellerImageTooLarge(maxKb)), backgroundColor: SoukColors.danger));
    }
    return null;
  }
  return file;
}

/// Bottom sheet with a serif title, keyboard inset and the kit padding.
Future<T?> showSellerSheet<T>(BuildContext context, {required String title, required Widget child}) =>
    showModalBottomSheet<T>(
      context: context,
      useRootNavigator: true,
      isScrollControlled: true,
      builder: (ctx) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(ctx).bottom),
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(SoukSpace.page, SoukSpace.s5, SoukSpace.page, SoukSpace.s6),
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, mainAxisSize: MainAxisSize.min, children: [
            Text(title, style: SoukText.displayH3),
            const SizedBox(height: SoukSpace.s4),
            child,
          ]),
        ),
      ),
    );

/// "Load more" list over a seller endpoint: first page skeleton, error pane
/// with retry, empty state, then rows with a trailing Load more button. A
/// change of [signature] (filters, refresh) restarts from page 1.
class SellerPagedList<T> extends StatefulWidget {
  const SellerPagedList({
    super.key,
    required this.fetch,
    required this.itemBuilder,
    required this.signature,
    required this.empty,
  });

  final Future<SellerPage<T>> Function(int page) fetch;
  final Widget Function(BuildContext context, T item) itemBuilder;
  final String signature;
  final Widget empty;

  @override
  State<SellerPagedList<T>> createState() => _SellerPagedListState<T>();
}

class _SellerPagedListState<T> extends State<SellerPagedList<T>> {
  final _items = <T>[];
  int _page = 0;
  int _lastPage = 1;
  bool _loading = false;
  bool _fromCache = false;
  DateTime? _fetchedAt;
  Object? _error;
  int _request = 0;

  @override
  void initState() {
    super.initState();
    _load(1);
  }

  @override
  void didUpdateWidget(SellerPagedList<T> old) {
    super.didUpdateWidget(old);
    if (old.signature != widget.signature) {
      _items.clear();
      _page = 0;
      _lastPage = 1;
      _error = null;
      _load(1);
    }
  }

  Future<void> _load(int page) async {
    final id = ++_request;
    setState(() => _loading = true);
    try {
      final p = await widget.fetch(page);
      if (!mounted || id != _request) return;
      setState(() {
        if (p.page <= 1) _items.clear();
        _items.addAll(p.items);
        _page = p.page;
        _lastPage = p.lastPage;
        _fromCache = p.fromCache;
        _fetchedAt = p.fetchedAt;
        _error = null;
      });
    } catch (e) {
      if (!mounted || id != _request) return;
      // A 403 means the store lost its approval: leave the shell.
      if (sellerGuardBounce(context, e)) return;
      setState(() => _error = e);
    } finally {
      if (mounted && id == _request) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    if (_items.isEmpty) {
      if (_loading) return const SoukSkeleton.list(rows: 5);
      if (_error != null) return SellerErrorPane(error: _error!, onRetry: () => _load(1));
      return widget.empty;
    }
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      if (_fromCache)
        Padding(
          padding: const EdgeInsets.only(bottom: SoukSpace.s3),
          child: Align(alignment: Alignment.centerLeft, child: CachedChip(fetchedAt: _fetchedAt)),
        ),
      for (var i = 0; i < _items.length; i++) ...[
        if (i > 0) const SizedBox(height: SoukSpace.s3),
        widget.itemBuilder(context, _items[i]),
      ],
      if (_error != null) ...[
        const SizedBox(height: SoukSpace.s3),
        SoukAlert(_error is ApiException ? (_error! as ApiException).message : l.somethingWentWrong, status: 'danger'),
      ],
      if (_page < _lastPage) ...[
        const SizedBox(height: SoukSpace.s4),
        Center(
          child: SoukButton(
            key: const Key('seller-load-more'),
            label: l.sellerLoadMore,
            busy: _loading,
            onPressed: () => _load(_page + 1),
          ),
        ),
      ],
    ]);
  }
}

/// Label: value line for the order totals and store summary cards.
class SellerLine extends StatelessWidget {
  const SellerLine(this.label, this.value, {super.key, this.strong = false});

  final String label;
  final Widget value;
  final bool strong;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 3),
        child: Row(children: [
          Expanded(child: Text(label, style: strong ? SoukText.label : SoukText.bodySoft)),
          value,
        ]),
      );
}
