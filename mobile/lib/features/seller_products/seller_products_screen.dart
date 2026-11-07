import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/widgets/coming_soon.dart';

/// `/seller/products` tab root — built in slice 6.
class SellerProductsScreen extends StatelessWidget {
  const SellerProductsScreen({super.key});

  @override
  Widget build(BuildContext context) => const ComingSoonScreen(title: 'Seller products', tabRoot: true);
}

List<RouteBase> get sellerProductsSubRoutes => const [];
