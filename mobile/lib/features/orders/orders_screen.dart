import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/widgets/coming_soon.dart';
import '../../l10n/l10n.dart';

/// Orders tab root (tabs by status) — slice 5.
class OrdersScreen extends StatelessWidget {
  const OrdersScreen({super.key});

  @override
  Widget build(BuildContext context) => ComingSoonScreen(title: context.l10n.tabOrders, tabRoot: true);
}

List<RouteBase> get ordersSubRoutes => [
      GoRoute(path: ':id', builder: (c, _) => ComingSoonScreen(title: c.l10n.orderDetails), routes: [
        GoRoute(path: 'return', builder: (c, _) => ComingSoonScreen(title: c.l10n.orderReturn)),
        GoRoute(path: 'review', builder: (c, _) => ComingSoonScreen(title: c.l10n.orderReview)),
      ]),
    ];
