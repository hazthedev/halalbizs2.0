/// Ringgit text <-> integer sen for the seller forms, with string and
/// integer arithmetic only (CLAUDE.md rule 1): "12.50" -> 1250, "12" -> 1200,
/// "12.5" -> 1250, "12.345" -> null, "" -> null.
library;

int? parseRm(String input) {
  final s = input.trim().replaceAll(',', '').replaceFirst(RegExp(r'^RM\s*', caseSensitive: false), '');
  final m = RegExp(r'^(\d{1,10})(?:\.(\d{1,2}))?$').firstMatch(s);
  if (m == null) return null;
  final units = int.parse(m.group(1)!);
  final minor = int.parse((m.group(2) ?? '').padRight(2, '0'));
  return units * 100 + minor;
}

/// Prefill text for a price field: 1250 -> "12.50", null -> "".
String rmInput(int? sen) => sen == null ? '' : '${sen ~/ 100}.${(sen % 100).toString().padLeft(2, '0')}';

/// Whole-number field ("stock", "weight (g)"): "12" -> 12, "" -> null, "1.5" -> throws.
int? parseWhole(String input) {
  final s = input.trim();
  if (s.isEmpty) return null;
  if (!RegExp(r'^\d{1,9}$').hasMatch(s)) throw const FormatException('whole number');
  return int.parse(s);
}
