import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/core/api/api_client.dart';

import '../helpers.dart';

/// Gap item 9: Buy now, COD badge, shipping row, seller card, Specifications
/// and Q&A tabs (web product-detail.blade.php + ProductQuestions).
Map<String, dynamic> _detail({bool cod = true}) => {
  ...fakeProduct(),
  'cod_available': cod,
  'ships_from': 'Selangor',
  'specs': {
    'brand': 'Madu Kita',
    'condition': 'New',
    'weight_grams': 1250,
    'category': 'Honey',
  },
  'store': {
    'name': 'Kedai Madu',
    'slug': 'kedai-madu',
    'verified': true,
    'logo': null,
    'rating_avg': 4.5,
    'rating_count': 12,
    'service_rating_avg': 4.2,
    'service_rating_count': 3,
    'product_count': 7,
    'state': 'Johor',
    'joined_at': '2026-03-05T00:00:00+08:00',
  },
};

FakeApi _api({bool cod = true, Map<String, FakeHandler> more = const {}}) =>
    FakeApi(
      routes: {
        'GET products': (_, _) => [],
        'GET categories': (_, _) => [],
        'GET products/madu-tualang': (_, _) => _detail(cod: cod),
        ...more,
      },
    );

Map<String, dynamic> _q(int id, String text, {String? answer}) => {
  'id': id,
  'question': text,
  'asker_name': 'Aisyah Y.',
  'created_at': '2026-11-01T10:00:00+08:00',
  'answer': answer,
  'answered_at': answer == null ? null : '2026-11-02T10:00:00+08:00',
};

Future<void> _tapVisible(WidgetTester t, String key) async {
  await t.ensureVisible(find.byKey(Key(key)));
  await t.drag(find.byType(Scrollable).first, const Offset(0, -150)); // clear of the sticky buy bar
  await t.pumpAndSettle();
  await t.tap(find.byKey(Key(key)));
  await t.pumpAndSettle();
}

Future<void> _tab(WidgetTester t, String key) async {
  await t.scrollUntilVisible(
    find.byKey(Key('product-tab-$key')),
    300,
    scrollable: find.byType(Scrollable).first,
  );
  await t.drag(find.byType(Scrollable).first, const Offset(0, -150)); // clear of the sticky buy bar
  await t.pumpAndSettle();
  await t.tap(find.byKey(Key('product-tab-$key')));
  await t.pumpAndSettle();
}

void main() {
  testWidgets(
    'shipping row, COD badge and the seller card show the API fields',
    (tester) async {
      usePhone(tester);
      await Harness(api: _api()).pump(tester, location: '/p/madu-tualang');
      await tester.scrollUntilVisible(
        find.byKey(const Key('product-store')),
        300,
        scrollable: find.byType(Scrollable).first,
      );

      expect(
        find.text('Ships from Selangor · Shipping calculated at checkout'),
        findsOneWidget,
      );
      expect(find.byKey(const Key('product-cod')), findsOneWidget);
      expect(find.text('CASH ON DELIVERY'), findsOneWidget);
      expect(find.byKey(const Key('product-store-verified')), findsOneWidget);
      expect(find.text('4.5 (12)'), findsOneWidget);
      expect(find.text('Seller service 4.2 (3)'), findsOneWidget);
      expect(find.text('7 products'), findsOneWidget);
      expect(find.text('Johor'), findsOneWidget);
      expect(find.text('Joined Mar 2026'), findsOneWidget);
    },
  );

  testWidgets('no COD badge when the product is not COD-available', (
    tester,
  ) async {
    usePhone(tester);
    await Harness(api: _api(cod: false))
        .pump(tester, location: '/p/madu-tualang');
    expect(find.byKey(const Key('product-shipping')), findsOneWidget);
    expect(find.byKey(const Key('product-cod')), findsNothing);
  });

  testWidgets(
    'tabs: Description first, Specifications lists brand, condition, weight, category',
    (tester) async {
      usePhone(tester);
      await Harness(api: _api()).pump(tester, location: '/p/madu-tualang');
      await tester.scrollUntilVisible(
        find.byKey(const Key('product-tab-description')),
        300,
        scrollable: find.byType(Scrollable).first,
      );
      expect(find.text('Raw wild honey.', findRichText: true), findsOneWidget);
      expect(find.byKey(const Key('product-specs')), findsNothing);

      await _tab(tester, 'specs');
      expect(find.text('Raw wild honey.', findRichText: true), findsNothing);
      expect(find.text('Madu Kita'), findsOneWidget);
      expect(find.text('New'), findsOneWidget);
      expect(find.text('1,250 g'), findsOneWidget);
      expect(find.text('Honey'), findsOneWidget);
    },
  );

  testWidgets(
    'Q&A tab lists answered and awaiting questions; a guest is sent to log in',
    (tester) async {
      usePhone(tester);
      final api = _api(
        more: {
          'GET products/madu-tualang/questions': (_, _) => [
            _q(2, 'Ships to Sabah?'),
            _q(1, 'Is it halal?', answer: 'Yes, JAKIM.'),
          ],
        },
      );
      final h = Harness(api: api);
      await h.pump(tester, location: '/p/madu-tualang');
      await _tab(tester, 'qa');

      expect(find.text('Is it halal?'), findsOneWidget);
      expect(find.text('Yes, JAKIM.'), findsOneWidget);
      expect(find.text('Ships to Sabah?'), findsOneWidget);
      expect(find.byKey(const Key('qa-2-awaiting')), findsOneWidget);
      expect(find.byKey(const Key('qa-1-awaiting')), findsNothing);
      expect(find.byKey(const Key('qa-input')), findsNothing);

      await _tapVisible(tester, 'qa-login');
      expect(h.router.state.uri.path, '/login');
      expect(h.router.state.uri.queryParameters['from'], '/p/madu-tualang');
    },
  );

  testWidgets(
    'Q&A tab: empty state, and a signed-in buyer asks (10 char minimum, then posts and reloads)',
    (tester) async {
      usePhone(tester);
      var asked = 0;
      final api = _api(
        more: {
          'GET me': (_, _) => fakeUser(),
          'GET products/madu-tualang/questions': (_, _) =>
              asked == 0 ? [] : [_q(5, 'Does it ship to Sabah?')],
          'POST products/madu-tualang/questions': (_, body) {
            expect((body as Map)['question'], 'Does it ship to Sabah?');
            asked++;
            return _q(5, 'Does it ship to Sabah?');
          },
        },
      );
      await Harness(
        api: api,
        token: 'tok',
      ).pump(tester, location: '/p/madu-tualang');
      await _tab(tester, 'qa');
      expect(find.byKey(const Key('qa-empty')), findsOneWidget);

      await tester.enterText(find.byKey(const Key('qa-input')), 'too short');
      await _tapVisible(tester, 'qa-post');
      expect(
        find.text('Add a little more detail (at least 10 characters).'),
        findsOneWidget,
      );
      expect(asked, 0);

      await tester.enterText(
        find.byKey(const Key('qa-input')),
        '  Does it ship to Sabah?  ',
      );
      await _tapVisible(tester, 'qa-post');
      expect(asked, 1);
      expect(find.text('Does it ship to Sabah?'), findsOneWidget);
      expect(find.byKey(const Key('qa-empty')), findsNothing);
    },
  );

  testWidgets(
    'Q&A tab: a server refusal (rate limit) is shown, nothing is added',
    (tester) async {
      usePhone(tester);
      final api = _api(
        more: {
          'GET me': (_, _) => fakeUser(),
          'GET products/madu-tualang/questions': (_, _) => [],
          'POST products/madu-tualang/questions': (_, _) => ApiException(
            'too_many_attempts',
            'Slow down — you can ask again in 30 seconds.',
            status: 429,
          ),
        },
      );
      await Harness(
        api: api,
        token: 'tok',
      ).pump(tester, location: '/p/madu-tualang');
      await _tab(tester, 'qa');
      await tester.enterText(
        find.byKey(const Key('qa-input')),
        'Another fine question',
      );
      await _tapVisible(tester, 'qa-post');
      expect(
        find.text('Slow down — you can ask again in 30 seconds.'),
        findsOneWidget,
      );
      expect(find.byKey(const Key('qa-empty')), findsOneWidget);
    },
  );

  testWidgets(
    'Buy now adds the variant then opens the cart; a guest is sent to log in',
    (tester) async {
      usePhone(tester);
      final api = _api(
        more: {
          'GET me': (_, _) => fakeUser(),
          'GET cart': (_, _) => {
            'groups': [],
            'item_count': 0,
            'selected_count': 0,
            'selected_subtotal_sen': 0,
            'purchasing_enabled': true,
          },
          'POST cart/items': (_, body) {
            expect((body as Map)['variant_id'], 10);
            return {
              'groups': [],
              'item_count': 1,
              'selected_count': 1,
              'selected_subtotal_sen': 1250,
              'purchasing_enabled': true,
            };
          },
        },
      );
      final h = Harness(api: api, token: 'tok');
      await h.pump(tester, location: '/p/madu-tualang');
      await tester.tap(find.byKey(const Key('product-buy-now')));
      await tester.pumpAndSettle();
      expect(api.calls, contains('POST cart/items'));
      expect(h.router.state.uri.path, '/cart');

      final guest = Harness(api: _api());
      await guest.pump(tester, location: '/p/madu-tualang');
      await tester.tap(find.byKey(const Key('product-buy-now')));
      await tester.pumpAndSettle();
      expect(guest.router.state.uri.path, '/login');
    },
  );
}
