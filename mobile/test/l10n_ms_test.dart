import 'dart:convert';
import 'dart:io';

import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/l10n/app_localizations.dart';

void main() {
  test('BM checkout is "Bayar", never "daftar keluar" (reads as sign out; Haze 2026-11-10)', () {
    final l = lookupAppLocalizations(const Locale('ms'));
    expect(l.checkout, 'Bayar');
    expect(l.cartCheckout, 'Bayar');
    expect(l.checkoutTitle, 'Bayar');

    final arb = jsonDecode(File('lib/l10n/app_ms.arb').readAsStringSync()) as Map<String, dynamic>;
    final left = arb.entries.where((e) => e.value is String && (e.value as String).toLowerCase().contains('daftar keluar'));
    expect(left.map((e) => e.key), isEmpty);
  });
}
