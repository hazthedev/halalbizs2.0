import 'package:flutter/material.dart';

import '../../theme/souk_theme.dart';

/// Skeletons, not spinners (docs/03): cream blocks in the shape of the
/// content that is coming. No shimmer — motion stays quiet.
class SoukSkeleton extends StatelessWidget {
  const SoukSkeleton.block({super.key, this.width = double.infinity, this.height = 14, this.radius = 6})
      : _kind = _Kind.block,
        rows = 0;

  /// A list of [rows] text-like rows.
  const SoukSkeleton.list({super.key, this.rows = 6})
      : _kind = _Kind.list,
        width = double.infinity,
        height = 14,
        radius = 6;

  /// A two-column grid of [rows] product-card placeholders.
  const SoukSkeleton.cards({super.key, this.rows = 4})
      : _kind = _Kind.cards,
        width = double.infinity,
        height = 14,
        radius = 6;

  final _Kind _kind;
  final double width;
  final double height;
  final double radius;
  final int rows;

  @override
  Widget build(BuildContext context) => switch (_kind) {
        _Kind.block => _Block(width: width, height: height, radius: radius),
        _Kind.list => Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              for (var i = 0; i < rows; i++)
                Padding(
                  padding: const EdgeInsets.only(bottom: SoukSpace.s3),
                  child: Row(children: [
                    const _Block(width: 56, height: 56, radius: SoukRadius.control),
                    const SizedBox(width: SoukSpace.s3),
                    Expanded(
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: const [
                        _Block(width: double.infinity, height: 14, radius: 6),
                        SizedBox(height: SoukSpace.s2),
                        _Block(width: 120, height: 12, radius: 6),
                      ]),
                    ),
                  ]),
                ),
            ],
          ),
        _Kind.cards => GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: rows,
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              mainAxisSpacing: SoukSpace.s3,
              crossAxisSpacing: SoukSpace.s3,
              childAspectRatio: .68,
            ),
            itemBuilder: (_, _) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: const [
              Expanded(child: _Block(width: double.infinity, height: double.infinity, radius: SoukRadius.card)),
              SizedBox(height: SoukSpace.s2),
              _Block(width: double.infinity, height: 14, radius: 6),
              SizedBox(height: 6),
              _Block(width: 80, height: 14, radius: 6),
            ]),
          ),
      };
}

enum _Kind { block, list, cards }

class _Block extends StatelessWidget {
  const _Block({required this.width, required this.height, required this.radius});

  final double width;
  final double height;
  final double radius;

  @override
  Widget build(BuildContext context) => Container(
        width: width,
        height: height,
        decoration: BoxDecoration(color: SoukColors.cream, borderRadius: BorderRadius.circular(radius)),
      );
}
