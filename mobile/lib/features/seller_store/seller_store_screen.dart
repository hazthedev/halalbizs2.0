import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../core/widgets/coming_soon.dart';

/// `/seller/store` tab root — built in slice 6.
class SellerStoreScreen extends StatelessWidget {
  const SellerStoreScreen({super.key});

  @override
  Widget build(BuildContext context) => const ComingSoonScreen(title: 'My store', tabRoot: true);
}

List<RouteBase> get sellerStoreSubRoutes => const [];
