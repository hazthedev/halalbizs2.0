import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';

/// Sellers write descriptions in a rich-text editor, so the API sends HTML.
/// The app must render it, never print the tags.
bool _anyTextHas(WidgetTester tester, String needle) => tester
    .widgetList<RichText>(find.byType(RichText))
    .any((w) => w.text.toPlainText().contains(needle));

void main() {
  testWidgets('product description HTML renders as text, no raw tags', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET products/madu-tualang': (_, _) =>
          {...fakeProduct(), 'description': '<p>One</p><p><strong>Two</strong></p>'},
    });
    await Harness(api: api).pump(tester, location: '/p/madu-tualang');
    await tester.scrollUntilVisible(find.textContaining('Two', findRichText: true), 200,
        scrollable: find.byType(Scrollable).first);

    expect(find.textContaining('One', findRichText: true), findsWidgets);
    expect(find.textContaining('Two', findRichText: true), findsWidgets);
    expect(_anyTextHas(tester, '<p>'), isFalse);
    expect(_anyTextHas(tester, '<strong>'), isFalse);
  });

  testWidgets('store blurb strips tags and decodes entities', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET categories': (_, _) => [],
      'GET stores/kedai-madu': (_, _) =>
          {'name': 'Kedai Madu', 'slug': 'kedai-madu', 'description': '<p>Honey &amp; dates</p><p>Since&nbsp;1990</p>'},
      'GET stores/kedai-madu/products': (_, _) => [],
    });
    await Harness(api: api).pump(tester, location: '/s/kedai-madu');

    expect(find.textContaining('Honey & dates Since 1990', findRichText: true), findsOneWidget);
    expect(_anyTextHas(tester, '<p>'), isFalse);
    expect(_anyTextHas(tester, '&amp;'), isFalse);
  });
}
