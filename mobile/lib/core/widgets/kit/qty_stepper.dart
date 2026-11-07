import 'package:flutter/material.dart';

import '../../theme/souk_theme.dart';

/// Pill quantity stepper: − [value] +, mono numerals, 44pt targets.
/// Keys: `qty-minus`, `qty-plus`.
class QtyStepper extends StatelessWidget {
  const QtyStepper({
    super.key,
    required this.value,
    required this.onChanged,
    this.min = 1,
    this.max,
    this.enabled = true,
  });

  final int value;
  final ValueChanged<int> onChanged;
  final int min;
  final int? max;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    final canMinus = enabled && value > min;
    final canPlus = enabled && (max == null || value < max!);
    return Container(
      decoration: BoxDecoration(
        color: SoukColors.surface,
        borderRadius: BorderRadius.circular(SoukRadius.pill),
        border: Border.all(color: SoukColors.lineStrong),
      ),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        _Step(key: const Key('qty-minus'), icon: Icons.remove_rounded, label: 'Decrease quantity',
            onTap: canMinus ? () => onChanged(value - 1) : null),
        ConstrainedBox(
          constraints: const BoxConstraints(minWidth: 36),
          child: Text('$value', textAlign: TextAlign.center, style: SoukText.price),
        ),
        _Step(key: const Key('qty-plus'), icon: Icons.add_rounded, label: 'Increase quantity',
            onTap: canPlus ? () => onChanged(value + 1) : null),
      ]),
    );
  }
}

class _Step extends StatelessWidget {
  const _Step({super.key, required this.icon, required this.label, this.onTap});

  final IconData icon;
  final String label;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => IconButton(
        tooltip: label,
        onPressed: onTap,
        style: IconButton.styleFrom(
          minimumSize: const Size(SoukSpace.minTap, SoukSpace.minTap),
          foregroundColor: SoukColors.ink,
          disabledForegroundColor: SoukColors.line,
        ),
        icon: Icon(icon, size: 20),
      );
}
