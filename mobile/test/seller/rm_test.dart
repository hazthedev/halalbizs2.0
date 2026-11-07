import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/features/seller_shared/rm.dart';

void main() {
  test('parseRm converts ringgit text to sen with string arithmetic', () {
    expect(parseRm('12.50'), 1250);
    expect(parseRm('12'), 1200);
    expect(parseRm('12.5'), 1250);
    expect(parseRm('0.05'), 5);
    expect(parseRm('RM 1,234.56'), 123456);
    expect(parseRm('12.345'), isNull);
    expect(parseRm('abc'), isNull);
    expect(parseRm(''), isNull);
    expect(parseRm('-5'), isNull);
  });

  test('rmInput prefills a price field from sen', () {
    expect(rmInput(1250), '12.50');
    expect(rmInput(5), '0.05');
    expect(rmInput(null), '');
  });

  test('parseWhole accepts digits only', () {
    expect(parseWhole('12'), 12);
    expect(parseWhole(''), isNull);
    expect(() => parseWhole('1.5'), throwsFormatException);
  });
}
