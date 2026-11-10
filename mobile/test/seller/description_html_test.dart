import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/features/seller_products/description_html.dart';

import '../helpers.dart';
import 'seller_helpers.dart';

/// Phone sweep 2026-11-10 row 17: the seller never edits raw HTML.
void main() {
  test('a <p>/<br> description round-trips through plain paragraphs', () {
    const html = '<p>Raw wild honey.</p><p>Line one<br>line two &amp; more</p>';
    expect(isPlainParagraphHtml(html), isTrue);
    final plain = htmlToParagraphs(html);
    expect(plain, 'Raw wild honey.\n\nLine one\nline two & more');
    expect(paragraphsToHtml(plain), html);
    expect(paragraphsToHtml('a < b\n\n\n\nc'), '<p>a &lt; b</p><p>c</p>');
    expect(paragraphsToHtml('  '), '');
    expect(isPlainParagraphHtml('No tags at all.'), isTrue);
  });

  test('bold, lists, links and attributes count as formatting', () {
    for (final html in ['<p><strong>Bold</strong></p>', '<ul><li>a</li></ul>', '<p><a href="x">l</a></p>', '<p style="x">a</p>']) {
      expect(isPlainParagraphHtml(html), isFalse, reason: html);
    }
  });

  Future<List<Object?>> openProduct(WidgetTester tester, Map<String, String> desc) async {
    usePhone(tester);
    final sent = <Object?>[];
    final product = {...fakeSellerProduct(), 'description_translations': {'en': '', 'ms': '', 'vi': '', ...desc}};
    final api = FakeApi(routes: {
      ...sellerBaseRoutes(),
      'GET seller/products/7': (_, _) => product,
      'PATCH seller/products/7': (_, body) {
        sent.add(body);
        return product;
      },
    });
    await Harness(api: api, token: 'tok').pump(tester, location: '/seller/products/7');
    return sent;
  }

  Future<void> save(WidgetTester tester) async {
    await tester.tap(find.byKey(const Key('seller-product-save')));
    await tester.pumpAndSettle();
  }

  testWidgets('a simple description edits as plain text and saves as <p> per paragraph', (tester) async {
    final sent = await openProduct(tester, {'en': '<p>Raw wild honey.</p><p>From Pahang.</p>'});
    final field = tester.widget<EditableText>(find.descendant(of: find.byKey(const Key('seller-product-desc-en')), matching: find.byType(EditableText)));
    expect(field.controller.text, 'Raw wild honey.\n\nFrom Pahang.');
    expect(find.textContaining('<p>'), findsNothing);

    await tester.enterText(find.byKey(const Key('seller-product-desc-en')), 'Raw wild honey.\n\nFrom Pahang & Johor.');
    await save(tester);
    final desc = (sent.single! as Map)['description'] as Map;
    expect(desc['en'], '<p>Raw wild honey.</p><p>From Pahang &amp; Johor.</p>');
  });

  testWidgets('an untouched description is sent back exactly as stored', (tester) async {
    final sent = await openProduct(tester, {'en': 'Raw wild honey.'});
    await save(tester);
    expect(((sent.single! as Map)['description'] as Map)['en'], 'Raw wild honey.');
  });

  testWidgets('a formatted description is shown rendered, read-only, with the web note, and kept on save', (tester) async {
    const rich = '<p><strong>Pure</strong> honey</p><ul><li>500g</li></ul>';
    final sent = await openProduct(tester, {'en': rich});
    expect(find.byKey(const Key('seller-product-desc-en')), findsNothing);
    final box = find.byKey(const Key('seller-product-desc-en-readonly'));
    expect(box, findsOneWidget);
    expect(find.descendant(of: box, matching: find.textContaining('<')), findsNothing);
    expect(find.descendant(of: box, matching: find.textContaining('Pure', findRichText: true)), findsWidgets);
    expect(find.descendant(of: box, matching: find.text('This description has formatting. Edit it on the web seller centre.')), findsOneWidget);
    await save(tester);
    expect(((sent.single! as Map)['description'] as Map)['en'], rich);
  });
}
