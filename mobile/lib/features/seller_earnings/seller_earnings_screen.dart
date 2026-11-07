import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/widgets/coming_soon.dart';

/// `/seller/earnings` tab root — built in slice 6.
class SellerEarningsScreen extends StatelessWidget {
  const SellerEarningsScreen({super.key});

  @override
  Widget build(BuildContext context) => const ComingSoonScreen(title: 'Earnings', tabRoot: true);
}

List<RouteBase> get sellerEarningsSubRoutes => const [];
