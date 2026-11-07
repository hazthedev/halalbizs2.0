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
      GoRoute(path: ':id', builder: (_, s) => ComingSoonScreen(title: 'Order ${s.pathParameters['id']}'), routes: [
        GoRoute(path: 'return', builder: (_, _) => const ComingSoonScreen(title: 'Return')),
        GoRoute(path: 'review', builder: (_, _) => const ComingSoonScreen(title: 'Review')),
      ]),
    ];
