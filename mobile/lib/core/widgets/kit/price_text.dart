import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../theme/souk_theme.dart';

/// Formats integer sen for DISPLAY ONLY — "RM 1,250.00" from 125000, the
/// same output as the web's `App\Support\Money::format`. Integer division
/// and modulo only: the app never does money arithmetic (CLAUDE.md rule 1);
/// every total, discount and cap arrives from the API already computed.
String formatSen(int sen, {String symbol = 'RM'}) {
  final negative = sen < 0;
  final abs = sen.abs();
  final units = NumberFormat.decimalPattern('en').format(abs ~/ 100);
  final minor = (abs % 100).toString().padLeft(2, '0');
  return '${negative ? '-' : ''}$symbol $units.$minor';
}

/// Mono price (`--text-price`, IBM Plex Mono 500).
class PriceText extends StatelessWidget {
  const PriceText(
    this.sen, {
    super.key,
    this.currency = 'RM',
    this.style,
    this.strike = false,
    this.large = false,
  });

  final int sen;

  /// Symbol as the API's `X-Display-Currency` rendered it (default MYR).
  final String currency;
  final TextStyle? style;

  /// Struck-through original price beside a sale price.
  final bool strike;

  /// Emerald 22pt (product page).
  final bool large;

  @override
  Widget build(BuildContext context) {
    var s = style ?? (large ? SoukText.priceLarge : SoukText.price);
    if (strike) {
      s = s.copyWith(
        color: SoukColors.inkFaint,
        fontWeight: FontWeight.w400,
        decoration: TextDecoration.lineThrough,
        decorationColor: SoukColors.inkFaint,
      );
    }
    return Text(formatSen(sen, symbol: currency), style: s, maxLines: 1, overflow: TextOverflow.ellipsis);
  }
}
