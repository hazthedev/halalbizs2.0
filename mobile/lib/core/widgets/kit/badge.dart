import 'package:flutter/material.dart';

import '../../theme/souk_theme.dart';

/// Status pill: 11px caps, rounded-full, colours from [soukStatusColor]
/// (same mapping as the web's `order-status-pill`). [status] is the raw API
/// code (`pending_payment`); [label] the translated text.
class SoukBadge extends StatelessWidget {
  const SoukBadge(this.label, {super.key, this.status, this.colors});

  final String label;
  final String? status;

  /// Overrides the status mapping (e.g. a sale badge).
  final SoukStatusColors? colors;

  @override
  Widget build(BuildContext context) {
    final c = colors ?? soukStatusColor(status ?? label);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 3),
      decoration: BoxDecoration(
        color: c.bg,
        borderRadius: BorderRadius.circular(SoukRadius.pill),
        border: Border.all(color: c.border),
      ),
      child: Text(
        label.toUpperCase(),
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(
          fontFamily: SoukFonts.sans,
          fontSize: 11,
          height: 1.35,
          fontWeight: FontWeight.w600,
          letterSpacing: .6,
          color: c.fg,
        ),
      ),
    );
  }
}

/// Inline banner (validation summary, session messages, info).
class SoukAlert extends StatelessWidget {
  const SoukAlert(this.message, {super.key, this.status = 'info'});

  final String message;

  /// `warn`, `danger`, `sale` (emerald) or anything else (ink).
  final String status;

  @override
  Widget build(BuildContext context) {
    final c = soukStatusColor(status);
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: SoukSpace.s4, vertical: SoukSpace.s3),
      decoration: BoxDecoration(
        color: c.bg == SoukColors.surface ? SoukColors.cream : c.bg,
        borderRadius: BorderRadius.circular(SoukRadius.control),
        border: Border.all(color: c.border),
      ),
      child: Text(message, style: SoukText.small.copyWith(color: c.fg)),
    );
  }
}
