import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../l10n/l10n.dart';
import 'api/json.dart';
import 'offline/offline.dart';
import 'widgets/souk_kit.dart';

/// `purchasing_enabled` from `GET config` (the server's one switch). Unknown
/// (offline, no saved copy) = on: the cart / checkout APIs still refuse.
final purchasingEnabledProvider = FutureProvider<bool>((ref) async {
  try {
    final cfg = asMap(
      (await ref
              .watch(offlineRepoProvider)
              .getJson('config')
              .timeout(const Duration(seconds: 3)))
          .data,
    );
    return !cfg.containsKey('purchasing_enabled') ||
        asBool(cfg['purchasing_enabled']);
  } catch (_) {
    return true;
  }
});

/// True unless config says buying is off. Hides every purchase control.
bool purchasingOn(WidgetRef ref) =>
    ref.watch(purchasingEnabledProvider).value ?? true;

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
