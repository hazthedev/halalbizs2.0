import 'package:flutter/material.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';

import '../../theme/souk_theme.dart';

/// White surface, 1px `line` hairline, 18px radius, card shadow, 16px padding.
/// Depth is print-like (docs/03): a hairline and a cream-vs-white shift, no
/// elevation ramp.
class SoukCard extends StatelessWidget {
  const SoukCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(SoukSpace.s4),
    this.onTap,
    this.color,
    this.cream = false,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final VoidCallback? onTap;
  final Color? color;

  /// Cream inset panel instead of white (packshot beds, tiles).
  final bool cream;

  @override
  Widget build(BuildContext context) {
    final radius = BorderRadius.circular(SoukRadius.card);
    Widget body = Padding(padding: padding, child: child);
    if (onTap != null) body = InkWell(onTap: onTap, borderRadius: radius, child: body);
    // Always a Material: ListTile / InkWell children need one, the box is not.
    body = Material(type: MaterialType.transparency, child: body);
    return Container(
      decoration: BoxDecoration(
        color: color ?? (cream ? SoukColors.cream : SoukColors.surface),
        borderRadius: radius,
        border: Border.all(color: SoukColors.line),
        boxShadow: cream ? null : SoukShadow.card,
      ),
      child: ClipRRect(borderRadius: BorderRadius.circular(SoukRadius.card - 1), child: body),
    );
  }
}

/// Serif section heading with an optional trailing link ("View all").
class SoukSectionHeading extends StatelessWidget {
  const SoukSectionHeading(this.title, {super.key, this.linkLabel, this.onLink});

  final String title;
  final String? linkLabel;
  final VoidCallback? onLink;

  @override
  Widget build(BuildContext context) => Row(
        children: [
          Expanded(child: Text(title, style: SoukText.displayH3, maxLines: 1, overflow: TextOverflow.ellipsis)),
          if (linkLabel != null && onLink != null)
            TextButton(key: const Key('souk-section-link'), onPressed: onLink, child: Text(linkLabel!)),
        ],
      );
}

/// Grouped list row (Account menu): leading icon, label, trailing chevron.
class SoukRow extends StatelessWidget {
  const SoukRow({
    super.key,
    required this.label,
    this.icon,
    this.onTap,
    this.trailing,
    this.danger = false,
    this.subtitle,
  });

  final String label;
  final FaIconData? icon;
  final VoidCallback? onTap;
  final Widget? trailing;
  final bool danger;
  final String? subtitle;

  @override
  Widget build(BuildContext context) {
    final fg = danger ? SoukColors.danger : SoukColors.ink;
    return ListTile(
      onTap: onTap,
      minTileHeight: 48,
      leading: icon == null ? null : FaIcon(icon, size: 18, color: danger ? SoukColors.danger : SoukColors.inkFaint),
      title: Text(label, style: SoukText.body.copyWith(color: fg)),
      subtitle: subtitle == null ? null : Text(subtitle!, style: SoukText.meta),
      trailing: trailing ??
          (onTap == null ? null : const Icon(Icons.chevron_right_rounded, color: SoukColors.inkFaint)),
      shape: const Border(bottom: BorderSide(color: SoukColors.line)),
    );
  }
}
