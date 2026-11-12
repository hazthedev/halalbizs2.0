import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/auth/session.dart';
import '../../core/theme/souk_theme.dart';
import '../../l10n/l10n.dart';

class PreviewMode extends Notifier<bool> {
  // Watching the permission resets the switch to off on sign-out or account change.
  @override
  bool build() {
    ref.watch(profileProvider.select((p) => p?.canPreviewContent ?? false));
    return false;
  }

  void set(bool on) => state = on;
}

/// Staff only; in memory, so off again after an app restart.
final previewModeProvider = NotifierProvider<PreviewMode, bool>(PreviewMode.new);

/// `{'preview': 1}` while the switch is on, else null (live content).
Map<String, dynamic>? previewQuery(bool on) => on ? {'preview': 1} : null;

/// Thin strip at the top of Home, pages and help while drafts are shown.
class PreviewStrip extends ConsumerWidget {
  const PreviewStrip({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (!ref.watch(previewModeProvider)) return const SizedBox.shrink();
    return Container(
      key: const Key('preview-strip'),
      width: double.infinity,
      color: SoukColors.warnTint,
      padding: const EdgeInsets.symmetric(vertical: SoukSpace.s1, horizontal: SoukSpace.s3),
      child: Text(context.l10n.previewStrip, style: SoukText.meta.copyWith(color: SoukColors.warn), textAlign: TextAlign.center),
    );
  }
}
