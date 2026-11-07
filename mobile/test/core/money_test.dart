import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/core/widgets/kit/price_text.dart';

void main() {
  test('formatSen matches App\\Support\\Money::format (integer sen, RM + space)', () {
    expect(formatSen(0), 'RM 0.00');
    expect(formatSen(5), 'RM 0.05');
    expect(formatSen(1250), 'RM 12.50');
    expect(formatSen(123456), 'RM 1,234.56');
    expect(formatSen(-1250), '-RM 12.50');
    expect(formatSen(100000000), 'RM 1,000,000.00');
  });

  testWidgets('PriceText renders the formatted sen', (tester) async {
    await tester.pumpWidget(const MaterialApp(
      home: Scaffold(body: Column(children: [PriceText(0), PriceText(5), PriceText(1250), PriceText(123456)])),
    ));
    expect(find.text('RM 0.00'), findsOneWidget);
    expect(find.text('RM 0.05'), findsOneWidget);
    expect(find.text('RM 12.50'), findsOneWidget);
    expect(find.text('RM 1,234.56'), findsOneWidget);
  });
}
