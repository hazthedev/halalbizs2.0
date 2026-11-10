import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../theme/souk_theme.dart';

/// The khatam brand mark (`public/favicon.svg`): two squares, one rotated
/// 45°, and a centre dot, stroked. Drawn rather than decoded so it stays
/// crisp at any size and needs no SVG dependency.
class KhatamMark extends StatelessWidget {
  const KhatamMark({super.key, this.size = 24, this.color = SoukColors.emerald, this.strokeWidth});

  final double size;
  final Color color;
  final double? strokeWidth;

  // Align + SizedBox: a tight parent (a stretched Column on the auth
  // screens) can widen the Align but never the painted box, so the mark
  // stays [size] square and never draws behind the content beside it.
  @override
  Widget build(BuildContext context) => Semantics(
        label: 'HalalBizs',
        image: true,
        child: Align(
          alignment: Alignment.centerLeft,
          widthFactor: 1,
          heightFactor: 1,
          child: SizedBox.square(
            dimension: size,
            child: CustomPaint(painter: _KhatamPainter(color, strokeWidth ?? size / 12.6)),
          ),
        ),
      );
}

class _KhatamPainter extends CustomPainter {
  const _KhatamPainter(this.color, this.stroke);

  final Color color;
  final double stroke;

  @override
  void paint(Canvas canvas, Size size) {
    // favicon.svg: viewBox 24, square 13.2 at 5.4, dot r 2.1 — scaled to size.
    final s = math.min(size.width, size.height) / 24;
    final paint = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = stroke
      ..strokeJoin = StrokeJoin.round;
    final rect = RRect.fromRectAndRadius(Rect.fromLTWH(5.4 * s, 5.4 * s, 13.2 * s, 13.2 * s), Radius.circular(.6 * s));
    canvas.drawRRect(rect, paint);
    canvas.save();
    canvas.translate(12 * s, 12 * s);
    canvas.rotate(math.pi / 4);
    canvas.translate(-12 * s, -12 * s);
    canvas.drawRRect(rect, paint);
    canvas.restore();
    canvas.drawCircle(Offset(12 * s, 12 * s), 2.1 * s, paint);
  }

  @override
  bool shouldRepaint(_KhatamPainter old) => old.color != color || old.stroke != stroke;
}

/// docs/03 empty state: brass khatam medallion, one serif line, one sentence,
/// one action. Never a spinner, never a second button.
class SoukEmptyState extends StatelessWidget {
  const SoukEmptyState({
    super.key,
    required this.title,
    this.message,
    this.action,
    this.compact = false,
  });

  final String title;
  final String? message;

  /// The single action (a [SoukButton]).
  final Widget? action;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final tile = compact ? 56.0 : 76.0;
    return Padding(
      padding: EdgeInsets.symmetric(horizontal: SoukSpace.s6, vertical: compact ? SoukSpace.s6 : 44),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: tile,
            height: tile,
            decoration: const BoxDecoration(color: SoukColors.brassTint, shape: BoxShape.circle),
            alignment: Alignment.center,
            child: KhatamMark(size: tile * .5, color: SoukColors.brassDeep),
          ),
          const SizedBox(height: SoukSpace.s4),
          Text(title, textAlign: TextAlign.center, style: SoukText.displayH3),
          if (message != null) ...[
            const SizedBox(height: SoukSpace.s2),
            ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 360),
              child: Text(message!, textAlign: TextAlign.center, style: SoukText.bodySoft),
            ),
          ],
          if (action != null) ...[const SizedBox(height: SoukSpace.s5), action!],
        ],
      ),
    );
  }
}
