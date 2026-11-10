import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/widgets/coming_soon.dart';

/// `/seller` tab root — built in slice 6.
class SellerDashboardScreen extends StatelessWidget {
  const SellerDashboardScreen({super.key});

  @override
  Widget build(BuildContext context) => const ComingSoonScreen(title: 'Seller centre', tabRoot: true);
}

List<RouteBase> get sellerDashboardSubRoutes => const [];
