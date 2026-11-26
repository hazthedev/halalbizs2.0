import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../l10n/l10n.dart';
import 'version_gate.dart';
import 'widgets/souk_kit.dart';

/// True unless config says buying is off. Hides every purchase control.
/// Same source as the app gate: saved config first, then the fresh answer; a
/// slow or failed fetch keeps the last known one. Unknown (no saved copy, no
/// answer yet) = on: the cart / checkout APIs still refuse.
bool purchasingOn(WidgetRef ref) => ref.watch(appGateProvider).value?.purchasing ?? true;

/// Shown in place of cart / checkout while buying is off (deep links land here).
class BuyingOpensSoon extends StatelessWidget {
  const BuyingOpensSoon({super.key});

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return SoukEmptyState(
      key: const Key('buying-opens-soon'),
      title: l.buyingOpensSoonTitle,
      message: l.buyingOpensSoonBody,
      action: SoukButton(
        key: const Key('buying-soon-browse'),
        label: l.cartContinueShopping,
        variant: SoukButtonVariant.primary,
        onPressed: () => context.go('/home'),
      ),
    );
  }
}
