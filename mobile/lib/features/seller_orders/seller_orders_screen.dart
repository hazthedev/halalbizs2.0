import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import '../seller_shared/models.dart';
import '../seller_shared/seller_api.dart';
import '../seller_shared/seller_kit.dart';
import 'seller_order_detail_screen.dart';

/// `/seller/orders?status=` tab root: status chips + paged order rows.
class SellerOrdersScreen extends ConsumerStatefulWidget {
  const SellerOrdersScreen({super.key, this.initialStatus});

  /// From the `status` query (dashboard chips deep-link here).
  final String? initialStatus;

  @override
  ConsumerState<SellerOrdersScreen> createState() => _SellerOrdersScreenState();
}

class _SellerOrdersScreenState extends ConsumerState<SellerOrdersScreen> {
  String? _status;
  int _refresh = 0;

  @override
  void initState() {
    super.initState();
    _status = widget.initialStatus;
  }

  @override
  void didUpdateWidget(SellerOrdersScreen old) {
    super.didUpdateWidget(old);
    if (old.initialStatus != widget.initialStatus) _status = widget.initialStatus;
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final api = ref.watch(sellerApiProvider);
    return SoukPage(
      title: l.sellerTabOrders,
      leading: const SellerBackToBuying(),
      showBack: false,
      scrollable: true,
      onRefresh: () async => setState(() => _refresh++),
      body: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        SizedBox(
          height: 40,
          child: ListView(
            key: const Key('seller-orders-tabs'),
            scrollDirection: Axis.horizontal,
            children: [
              SoukChip(
                key: const Key('seller-orders-tab-all'),
                label: l.sellerAllStatuses,
                selected: _status == null,
                onTap: () => setState(() => _status = null),
              ),
              for (final s in kSubOrderStatuses) ...[
                const SizedBox(width: SoukSpace.s2),
                SoukChip(
                  key: Key('seller-orders-tab-$s'),
                  label: sellerStatusLabel(l, s),
                  selected: _status == s,
                  onTap: () => setState(() => _status = s),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: SoukSpace.s4),
        SellerPagedList<SellerOrder>(
          signature: '${_status ?? ''}#$_refresh',
          fetch: (page) => api.orders(status: _status, page: page),
          itemBuilder: (context, o) => SellerOrderRow(o, onTap: () => context.push('/seller/orders/${o.id}')),
          empty: SoukEmptyState(key: const Key('seller-orders-empty'), title: l.sellerNoOrders, message: l.sellerNoOrdersBody),
        ),
      ]),
    );
  }
}

List<RouteBase> get sellerOrdersSubRoutes => [
      GoRoute(
        path: ':id',
        builder: (_, s) => SellerOrderDetailScreen(id: int.tryParse(s.pathParameters['id'] ?? '') ?? 0),
      ),
    ];
