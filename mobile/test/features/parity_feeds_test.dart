import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';
import 'home_test.dart' show fakeHome;

void main() {
  testWidgets('product page shows the related strip from GET products/{slug}/related', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET products/madu-tualang': (_, _) => fakeProduct(),
      'GET products/madu-tualang/related': (_, _) => [fakeProduct(id: 2, slug: 'kurma-ajwa', name: 'Kurma Ajwa Premium')],
    });
    final h = Harness(api: api);
    await h.pump(tester, location: '/p/madu-tualang');
    await tester.pumpAndSettle();

    await tester.ensureVisible(find.byKey(const Key('related-strip')));
    expect(find.text('Verified alongside it'), findsOneWidget);
    expect(find.byKey(const Key('product-card-kurma-ajwa')), findsOneWidget);

    await tester.ensureVisible(find.byKey(const Key('product-card-kurma-ajwa')));
    await tester.pumpAndSettle();
    await tester.drag(find.byKey(const Key('souk-page-scroll')), const Offset(0, -150));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('product-card-kurma-ajwa')));
    await tester.pumpAndSettle();
    expect(h.router.state.uri.path, '/p/kurma-ajwa');
  });

  testWidgets('related strip hides when empty or when the call fails', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET products/madu-tualang': (_, _) => fakeProduct(),
      'GET products/madu-tualang/related': (_, _) => [],
    });
    final h = Harness(api: api);
    await h.pump(tester, location: '/p/madu-tualang');
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('related-strip')), findsNothing);
  });

  testWidgets('Home ends with the Recommended for you strip (GET recommended)', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET home': (_, _) => fakeHome(featured: [fakeProduct()]),
      'GET recommended': (_, _) => [fakeProduct(id: 9, slug: 'teh-tarik', name: 'Teh Tarik')],
    });
    final h = Harness(api: api);
    await h.pump(tester, location: '/home');
    await tester.pumpAndSettle();

    await tester.ensureVisible(find.byKey(const Key('recommended-strip')));
    expect(find.text('Recommended for you'), findsOneWidget);
    expect(find.byKey(const Key('product-card-teh-tarik')), findsOneWidget);
  });

  testWidgets('Home hides the strip when nothing is recommended (web cold start)', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET home': (_, _) => fakeHome(featured: [fakeProduct()]),
      'GET recommended': (_, _) => [],
    });
    final h = Harness(api: api);
    await h.pump(tester, location: '/home');
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('recommended-strip')), findsNothing);
    expect(find.byKey(const Key('product-card-madu-tualang')), findsOneWidget);
  });
}
