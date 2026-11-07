import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../l10n/l10n.dart';
import 'souk_kit.dart';

/// Placeholder page for screens built in a later slice (cart, checkout,
/// orders, account sub-pages, the seller centre).
class ComingSoonScreen extends StatelessWidget {
  const ComingSoonScreen({super.key, required this.title, this.tabRoot = false});

  final String title;

  /// Tab root: no back chevron, brand mark instead.
  final bool tabRoot;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return SoukPage(
      title: title,
      brandMark: tabRoot,
      showBack: tabRoot ? false : null,
      body: Center(
        child: SoukEmptyState(
          key: const Key('coming-soon'),
          title: l.comingNextSlice,
          message: l.comingNextSliceBody,
          action: SoukButton(label: l.backToHome, onPressed: () => context.go('/home')),
        ),
      ),
    );
  }
}
