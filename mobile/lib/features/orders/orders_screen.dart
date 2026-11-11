import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/locale.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import 'order_detail_screen.dart';
import 'order_return_screen.dart';
import 'order_review_screen.dart';
import 'orders_api.dart';

List<RouteBase> get ordersSubRoutes => [
      GoRoute(path: ':id', builder: (_, s) => OrderDetailScreen(id: int.tryParse(s.pathParameters['id']!) ?? 0), routes: [
        GoRoute(path: 'return', builder: (_, s) => OrderReturnScreen(id: int.tryParse(s.pathParameters['id']!) ?? 0)),
        GoRoute(path: 'review', builder: (_, s) => OrderReviewScreen(id: int.tryParse(s.pathParameters['id']!) ?? 0)),
      ]),
    ];

/// `/orders` — bucket chips from `GET orders/buckets`, one card per sub-order,
/// pull-to-refresh, "Load more" pagination.
class OrdersScreen extends ConsumerStatefulWidget {
  const OrdersScreen({super.key, this.initialBucket});

  /// `/orders?bucket=to-ship` (the Account shortcuts); null = All.
  final String? initialBucket;

  @override
  ConsumerState<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends ConsumerState<OrdersScreen> {
  late String? _bucket = widget.initialBucket;
  final _items = <SubOrder>[];
  int _page = 1;
  bool _hasMore = false;
  bool _loading = true;
  bool _fromCache = false;
  DateTime? _fetchedAt;
  Object? _error;
  int _seq = 0;

  @override
  void initState() {
    super.initState();
    _load(reset: true);
  }

  @override
  void didUpdateWidget(OrdersScreen old) {
    super.didUpdateWidget(old);
    if (widget.initialBucket != old.initialBucket) _pick(widget.initialBucket);
  }

  Future<void> _load({required bool reset}) async {
    final seq = ++_seq;
    setState(() {
      _loading = true;
      if (reset) _error = null;
    });
    try {
      final page = reset ? 1 : _page + 1;
      final r = await ref.read(ordersApiProvider).list(bucket: _bucket, page: page);
      if (!mounted || seq != _seq) return;
      setState(() {
        if (reset) _items.clear();
        _items.addAll(r.items);
        _page = r.meta.page;
        _hasMore = r.meta.hasMore;
        _fromCache = r.fromCache;
        _fetchedAt = r.fetchedAt;
      });
    } catch (e) {
      if (!mounted || seq != _seq) return;
      if (reset) {
        setState(() => _error = e);
      } else {
        showApiError(context, e);
      }
    } finally {
      if (mounted && seq == _seq) setState(() => _loading = false);
    }
  }

  Future<void> _refresh() async {
    ref.invalidate(orderBucketsProvider);
    await _load(reset: true);
  }

  void _pick(String? key) {
    if (key == _bucket) return;
    setState(() => _bucket = key);
    _load(reset: true);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final buckets = ref.watch(orderBucketsProvider);
    // Status labels are server text in the request's language.
    ref.listen(localeProvider, (_, _) => _load(reset: true));

    return SoukPage(
      title: l.tabOrders,
      brandMark: true,
      showBack: false,
      scrollable: true,
      onRefresh: _refresh,
      fromCache: _fromCache,
      cachedAt: _fetchedAt,
      body: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        buckets.when(
          skipLoadingOnRefresh: true,
          loading: () => const SoukSkeleton.block(height: 40),
          error: (_, _) => const SizedBox.shrink(),
          data: (list) => SizedBox(
            height: 40,
            child: ListView(
              key: const Key('orders-buckets'),
              scrollDirection: Axis.horizontal,
              children: [
                SoukChip(key: const Key('orders-bucket-all'), label: l.ordersAll, selected: _bucket == null, onTap: () => _pick(null)),
                for (final b in list) ...[
                  const SizedBox(width: SoukSpace.s2),
                  SoukChip(
                    key: Key('orders-bucket-${b.key}'),
                    label: b.count > 0 ? '${b.label} · ${b.count}' : b.label,
                    selected: _bucket == b.key,
                    onTap: () => _pick(b.key),
                  ),
                ],
              ],
            ),
          ),
        ),
        const SizedBox(height: SoukSpace.s4),
        if (_error != null)
          ErrorPane(error: _error!, onRetry: () => _load(reset: true), retryLabel: l.retry)
        else if (_loading && _items.isEmpty)
          const SoukSkeleton.list(rows: 4)
        else if (_items.isEmpty)
          SoukEmptyState(
            key: const Key('orders-empty'),
            title: _bucket == null ? l.ordersEmptyTitle : l.ordersEmptyBucketTitle,
            message: _bucket == null ? l.ordersEmptyBody : l.ordersEmptyBucketBody,
            action: SoukButton(label: l.cartContinueShopping, variant: SoukButtonVariant.primary, onPressed: () => context.go('/home')),
          )
        else ...[
          for (final o in _items) ...[
            OrderCard(order: o, onTap: () => context.push('/orders/${o.id}')),
            const SizedBox(height: SoukSpace.s3),
          ],
          if (_hasMore)
            Center(
              child: SoukButton(
                key: const Key('orders-load-more'),
                label: l.ordersLoadMore,
                busy: _loading,
                requiresOnline: true,
                onPressed: () => _load(reset: false),
              ),
            ),
        ],
      ]),
    );
  }
}

/// One sub-order in the list: store, status pill, first item + "+N more", total, placed at.
class OrderCard extends StatelessWidget {
  const OrderCard({super.key, required this.order, required this.onTap});

  final SubOrder order;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final o = order;
    final first = o.items.firstOrNull;
    final more = o.items.length - 1;
    return SoukCard(
      key: Key('order-card-${o.id}'),
      onTap: onTap,
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Row(children: [
          Expanded(child: Text(o.storeName, style: SoukText.label, maxLines: 1, overflow: TextOverflow.ellipsis)),
          SoukBadge(o.statusLabel, status: o.status),
        ]),
        const SizedBox(height: SoukSpace.s3),
        Row(children: [
          SizedBox(width: 56, height: 56, child: ProductImage(first?.image, radius: SoukRadius.control)),
          const SizedBox(width: SoukSpace.s3),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(first?.name ?? '', style: SoukText.displayName, maxLines: 2, overflow: TextOverflow.ellipsis),
              if (first?.variantLabel != null) Text(first!.variantLabel!, style: SoukText.meta),
              if (more > 0) Text(l.ordersMoreItems(more), style: SoukText.meta),
            ]),
          ),
        ]),
        const SizedBox(height: SoukSpace.s3),
        Row(children: [
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(o.subOrderNo, style: SoukText.monoLabel),
              if (o.placedAt != null) Text(soukDateTime(context, o.placedAt!), style: SoukText.meta),
            ]),
          ),
          Text('${l.ordersTotal}: ', style: SoukText.small),
          PriceText(o.totalSen),
        ]),
      ]),
    );
  }
}
