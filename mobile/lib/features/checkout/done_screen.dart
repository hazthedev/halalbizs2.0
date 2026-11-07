import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/widgets/souk_kit.dart';
import '../../l10n/l10n.dart';
import '../cart/cart_api.dart';

/// `/checkout/done/:orderNo` — order placed (COD) or paid (iPay88).
class DoneScreen extends ConsumerStatefulWidget {
  const DoneScreen({super.key, required this.orderNo});

  final String orderNo;

  @override
  ConsumerState<DoneScreen> createState() => _DoneScreenState();
}

class _DoneScreenState extends ConsumerState<DoneScreen> {
  @override
  void initState() {
    super.initState();
    // The selected lines are gone: refresh the tab badge (after this build,
    // so the shell's Consumer is not marked dirty mid-frame).
    WidgetsBinding.instance.addPostFrameCallback((_) => ref.invalidate(cartProvider));
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return SoukPage(
      title: l.checkoutDoneTitle,
      showBack: false,
      body: Center(
        child: SoukEmptyState(
          key: const Key('checkout-done'),
          title: l.checkoutDoneTitle,
          message: l.checkoutDoneBody(widget.orderNo),
          action: SoukButton(
            key: const Key('checkout-done-orders'),
            label: l.checkoutViewOrders,
            variant: SoukButtonVariant.primary,
            onPressed: () => context.go('/orders'),
          ),
        ),
      ),
    );
  }
}
