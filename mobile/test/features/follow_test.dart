import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/core/api/api_client.dart';

import '../helpers.dart';

Map<String, dynamic> fakeStore({bool following = false, int followers = 4}) => {
      'id': 3,
      'slug': 'kedai-madu',
      'name': 'Kedai Madu',
      'description': null,
      'logo': null,
      'banner': null,
      'rating_avg': 0.0,
      'review_count': 0,
      'product_count': 1,
      'followers_count': followers,
      'is_following': following,
      'joined_at': '2026-01-01T00:00:00Z',
    };

Map<String, FakeHandler> _storeRoutes({bool following = false, Map<String, FakeHandler> extra = const {}}) => {
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'GET stores/kedai-madu': (_, _) => fakeStore(following: following),
      'GET stores/kedai-madu/products': (_, _) => [fakeProduct()],
      ...extra,
    };

void main() {
  testWidgets('Follow flips to Following with the count, then Unfollow flips back (server state wins)', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: _storeRoutes(extra: {
      'GET me': (_, _) => fakeUser(),
      'POST stores/kedai-madu/follow': (_, _) => {'is_following': true, 'followers_count': 5},
      'DELETE stores/kedai-madu/follow': (_, _) => {'is_following': false, 'followers_count': 4},
    }));
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/s/kedai-madu');
    await tester.pumpAndSettle();

    expect(find.text('Follow'), findsOneWidget);
    expect(find.text('4 followers'), findsOneWidget);

    await tester.tap(find.byKey(const Key('follow-button')));
    await tester.pumpAndSettle();
    expect(api.calls, contains('POST stores/kedai-madu/follow'));
    expect(find.text('Following'), findsOneWidget);
    expect(find.text('5 followers'), findsOneWidget);

    await tester.tap(find.byKey(const Key('follow-button')));
    await tester.pumpAndSettle();
    expect(api.calls, contains('DELETE stores/kedai-madu/follow'));
    expect(find.text('Follow'), findsOneWidget);
    expect(find.text('4 followers'), findsOneWidget);
  });

  testWidgets('a failed follow reverts the optimistic flip and shows the error', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: _storeRoutes(extra: {
      'GET me': (_, _) => fakeUser(),
      'POST stores/kedai-madu/follow': (_, _) => ApiException('server_error', 'Could not follow.', status: 500),
    }));
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/s/kedai-madu');
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('follow-button')));
    await tester.pumpAndSettle();

    expect(api.calls, contains('POST stores/kedai-madu/follow'));
    expect(find.text('Follow'), findsOneWidget);
    expect(find.text('Following'), findsNothing);
    expect(find.text('4 followers'), findsOneWidget);
  });

  testWidgets('guest tap goes to login and calls nothing', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: _storeRoutes());
    final h = Harness(api: api);
    await h.pump(tester, location: '/s/kedai-madu');
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('follow-button')));
    await tester.pumpAndSettle();
    expect(h.router.state.uri.path, '/login');
    expect(api.calls.where((c) => c.contains('follow')), isEmpty);
  });

  testWidgets('the shop owner sees the count but no button', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: _storeRoutes(extra: {'GET me': (_, _) => fakeUser(storeStatus: 'approved')}));
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/s/kedai-madu');
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('follow-button')), findsNothing);
    expect(find.text('4 followers'), findsOneWidget);
  });

  testWidgets('the product page seller card has Follow too', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: _storeRoutes(following: true, extra: {
      'GET products/madu-tualang': (_, _) => fakeProduct(),
      'GET products/madu-tualang/related': (_, _) => [],
    }));
    final h = Harness(api: api);
    await h.pump(tester, location: '/p/madu-tualang');
    await tester.pumpAndSettle();

    await tester.ensureVisible(find.byKey(const Key('follow-button')));
    expect(find.text('Following'), findsOneWidget);
    expect(find.text('4 followers'), findsOneWidget);
  });

  testWidgets('Account lists followed shops and opens one', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: _storeRoutes(extra: {
      'GET me': (_, _) => fakeUser(),
      'GET conversations': (_, _) => [],
      'GET orders/quick-status': (_, _) => [],
      'GET me/followed-stores': (_, _) => [fakeStore()],
    }));
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/account');
    await tester.pumpAndSettle();

    await tester.ensureVisible(find.byKey(const Key('account-following')));
    await tester.tap(find.byKey(const Key('account-following')));
    await tester.pumpAndSettle();
    expect(h.router.state.uri.path, '/account/following');
    expect(find.byKey(const Key('followed-store-kedai-madu')), findsOneWidget);
    expect(find.text('4 followers'), findsOneWidget);

    await tester.tap(find.byKey(const Key('followed-store-kedai-madu')));
    await tester.pumpAndSettle();
    expect(h.router.state.uri.path, '/s/kedai-madu');
  });

  testWidgets('no followed shops shows the empty state', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: _storeRoutes(extra: {'GET me': (_, _) => fakeUser(), 'GET me/followed-stores': (_, _) => []}));
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/account/following');
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('followed-empty')), findsOneWidget);
  });
}
