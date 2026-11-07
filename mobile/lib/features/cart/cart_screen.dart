import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/widgets/coming_soon.dart';
import '../../l10n/l10n.dart';

/// Cart tab root. The real cart (grouped by store, select lines, qty) ships
/// in slice 5; `/checkout/*` sits in this tab's branch per the plan.
class CartScreen extends StatelessWidget {
  const CartScreen({super.key});

  @override
  Widget build(BuildContext context) => ComingSoonScreen(title: context.l10n.tabCart, tabRoot: true);
}

/// Absolute routes that share the Cart tab's branch.
List<RouteBase> get cartSubRoutes => [
      GoRoute(path: '/checkout', builder: (c, _) => ComingSoonScreen(title: c.l10n.checkout), routes: [
        GoRoute(path: 'pay/:orderNo', builder: (c, _) => ComingSoonScreen(title: c.l10n.payment)),
        GoRoute(path: 'done/:orderNo', builder: (c, _) => ComingSoonScreen(title: c.l10n.orderPlaced)),
      ]),
    ];
