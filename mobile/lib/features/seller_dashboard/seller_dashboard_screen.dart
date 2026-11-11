import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/json.dart';
import '../../core/auth/session.dart';
import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import '../account/inbox_actions.dart';
import '../seller_shared/models.dart';
import '../seller_shared/seller_api.dart';
import '../seller_shared/seller_kit.dart';

/// `/seller` tab root: KPI tiles, orders-by-status chips, recent orders.
/// `GET seller/dashboard` through the offline repo (cached strip offline).
class SellerDashboardScreen extends ConsumerWidget {
  const SellerDashboardScreen({super.key});

  Future<void> _refresh(WidgetRef ref) async {
    ref.invalidate(sellerDashboardProvider);
    try {
      await ref.read(sellerDashboardProvider.future);
    } catch (_) {
      // surfaced by the error pane
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final async = ref.watch(sellerDashboardProvider);
    final storeName = asStr(ref.watch(profileProvider)?.store?['name']);

    return SoukPage(
      title: storeName ?? l.sellerTabDashboard,
      actions: const [InboxActions(), SellerBackToBuying()],
      showBack: false,
      scrollable: true,
      onRefresh: () => _refresh(ref),
      fromCache: async.value?.fromCache ?? false,
      cachedAt: async.value?.fetchedAt,
      body: async.when(
        skipLoadingOnRefresh: true,
        loading: () => const SoukSkeleton.list(rows: 6),
        error: (e, _) => SellerErrorPane(error: e, onRetry: () => _refresh(ref)),
        data: (r) => _Body(r.data),
      ),
    );
  }
}

class _Body extends StatelessWidget {
  const _Body(this.d);

  final Dashboard d;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final tiles = <Widget>[
      KpiTile(key: const Key('seller-kpi-today-sales'), label: l.sellerTodaySales, value: PriceText(d.todaySalesSen, large: true)),
      KpiTile(label: l.sellerTodayOrders, value: KpiCount(d.todayOrders)),
      KpiTile(label: l.sellerToShip, value: KpiCount(d.toShip)),
      KpiTile(label: l.sellerLowStock, value: KpiCount(d.lowStock)),
      KpiTile(key: const Key('seller-kpi-available'), label: l.sellerAvailable, value: PriceText(d.availableSen, large: true)),
      KpiTile(label: l.sellerHeld, value: PriceText(d.heldSen, large: true)),
    ];
    final statuses = d.ordersByStatus.entries.where((e) => e.value > 0).toList();

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      GridView.count(
        key: const Key('seller-kpis'),
        crossAxisCount: 2,
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        mainAxisSpacing: SoukSpace.s3,
        crossAxisSpacing: SoukSpace.s3,
        childAspectRatio: 2.1,
        children: tiles,
      ),
      const SizedBox(height: SoukSpace.s5),
      SoukSectionHeading(l.sellerOrdersByStatus),
      const SizedBox(height: SoukSpace.s3),
      if (statuses.isEmpty)
        Text(l.sellerNoOrders, style: SoukText.meta)
      else
        Wrap(spacing: SoukSpace.s2, runSpacing: SoukSpace.s2, children: [
          for (final e in statuses)
            SoukChip(
              key: Key('seller-status-${e.key}'),
              label: '${sellerStatusLabel(l, e.key)} · ${e.value}',
              selected: false,
              onTap: () => context.go('/seller/orders?status=${Uri.encodeQueryComponent(e.key)}'),
            ),
        ]),
      const SizedBox(height: SoukSpace.s5),
      SoukSectionHeading(l.sellerRecentOrders, linkLabel: l.viewAll, onLink: () => context.go('/seller/orders')),
      const SizedBox(height: SoukSpace.s3),
      if (d.recentOrders.isEmpty)
        SoukEmptyState(key: const Key('seller-dashboard-empty'), title: l.sellerNoOrders, message: l.sellerNoOrdersBody, compact: true)
      else
        for (final o in d.recentOrders)
          Padding(
            padding: const EdgeInsets.only(bottom: SoukSpace.s3),
            child: SellerOrderRow(o, onTap: () => context.push('/seller/orders/${o.id}')),
          ),
    ]);
  }
}

List<RouteBase> get sellerDashboardSubRoutes => const [];
