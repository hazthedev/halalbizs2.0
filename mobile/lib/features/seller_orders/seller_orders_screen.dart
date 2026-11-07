import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/widgets/coming_soon.dart';

/// `/seller/orders` tab root — built in slice 6.
class SellerOrdersScreen extends StatelessWidget {
  const SellerOrdersScreen({super.key});

  @override
  Widget build(BuildContext context) => const ComingSoonScreen(title: 'Seller orders', tabRoot: true);
}

List<RouteBase> get sellerOrdersSubRoutes => const [];
