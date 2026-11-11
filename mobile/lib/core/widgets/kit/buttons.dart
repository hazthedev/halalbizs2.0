import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';

import '../../../l10n/l10n.dart';
import '../../offline/offline.dart';
import '../../theme/souk_theme.dart';

/// docs/03 button variants: primary = emerald filled (one per region),
/// secondary = ink outline, ghost = text, danger = danger filled. Brass is
/// never a button fill (ornament only).
enum SoukButtonVariant { primary, secondary, ghost, danger }

/// The one button for every screen. Never use raw ElevatedButton in features.
///
///   SoukButton(label: 'Add to cart', icon: FontAwesomeIcons.cartPlus,
///       requiresOnline: true, busy: _adding, onPressed: _add)
///
/// * [busy] swaps the icon for a spinner and blocks taps (async writes).
/// * [requiresOnline] disables the button while `isOfflineProvider` is true
///   and shows "Needs a connection" on tap — the standard guard for writes.
/// * 44pt minimum height (HIG touch target).
class SoukButton extends StatelessWidget {
  const SoukButton({
    super.key,
    required this.label,
    this.onPressed,
    this.icon,
    this.variant = SoukButtonVariant.secondary,
    this.busy = false,
    this.requiresOnline = false,
    this.disabledReason,
    this.expand = false,
    this.compact = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final FaIconData? icon;
  final SoukButtonVariant variant;
  final bool busy;
  final bool requiresOnline;

  /// Disables the button; tapping it shows this reason in a SnackBar.
  final String? disabledReason;

  /// Full width.
  final bool expand;

  /// Smaller horizontal padding + 13.5pt text (chips, card actions). Height stays 44.
  final bool compact;

  @override
  Widget build(BuildContext context) {
    if (!requiresOnline) return _build(context, offline: false);
    return Consumer(
      builder: (context, ref, _) =>
          _build(context, offline: ref.watch(isOfflineProvider).value ?? false),
    );
  }

  Widget _build(BuildContext context, {required bool offline}) {
    final reason = disabledReason ?? (offline ? context.l10n.needsConnection : null);
    final enabled = onPressed != null && reason == null && !busy;
    final (bg, fg, border) = switch (variant) {
      SoukButtonVariant.primary => (SoukColors.emerald, Colors.white, null),
      SoukButtonVariant.secondary => (SoukColors.surface, SoukColors.ink, SoukColors.ink),
      SoukButtonVariant.ghost => (Colors.transparent, SoukColors.emerald, null),
      SoukButtonVariant.danger => (SoukColors.danger, Colors.white, null),
    };
    final font = compact ? 13.5 : 15.0;
    final iconSize = font * .95;
    final lead = busy
        ? SizedBox(
            width: iconSize,
            height: iconSize,
            child: CircularProgressIndicator(strokeWidth: 2, color: fg),
          )
        : (icon == null ? null : FaIcon(icon, size: iconSize, color: fg));

    final radius = BorderRadius.circular(SoukRadius.pill);
    Widget button = Opacity(
      opacity: enabled || busy ? 1 : .5,
      child: Material(
        color: bg,
        shape: RoundedRectangleBorder(
          borderRadius: radius,
          side: border == null ? BorderSide.none : BorderSide(color: border),
        ),
        child: InkWell(
          borderRadius: radius,
          onTap: enabled ? onPressed : null,
          child: ConstrainedBox(
            constraints: const BoxConstraints(minHeight: SoukSpace.minTap),
            child: Padding(
              padding: EdgeInsets.symmetric(horizontal: compact ? SoukSpace.s3 : SoukSpace.s5),
              child: Row(
                mainAxisSize: expand ? MainAxisSize.max : MainAxisSize.min,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  if (lead != null) ...[lead, const SizedBox(width: SoukSpace.s2)],
                  Flexible(
                    child: Text(
                      label,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: SoukText.button.copyWith(fontSize: font, color: fg),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
    if (!enabled && !busy && reason != null) {
      // Tooltips are unreachable on touch: tapping a disabled control surfaces the reason.
      button = GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: () => ScaffoldMessenger.maybeOf(context)
          ?..hideCurrentSnackBar()
          ..showSnackBar(SnackBar(content: Text(reason))),
        child: button,
      );
    }
    if (expand) button = SizedBox(width: double.infinity, child: button);
    // No label here: the Text child supplies it (a second one is read twice).
    return Semantics(button: true, enabled: enabled, child: button);
  }
}

/// Round 44pt icon button for app bars and card corners.
class SoukIconButton extends StatelessWidget {
  const SoukIconButton({super.key, required this.icon, required this.label, this.onPressed, this.color});

  final FaIconData icon;
  final String label;
  final VoidCallback? onPressed;
  final Color? color;

  @override
  Widget build(BuildContext context) => IconButton(
        tooltip: label,
        onPressed: onPressed,
        style: IconButton.styleFrom(
          minimumSize: const Size(SoukSpace.minTap, SoukSpace.minTap),
          foregroundColor: color ?? SoukColors.inkHead,
        ),
        icon: FaIcon(icon, size: 18),
      );
}
