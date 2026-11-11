import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers.dart';
import '../seller/seller_helpers.dart';
import 'home_test.dart' show fakeHome;

Map<String, FakeHandler> _routes({int notifications = 3, int unreadChat = 2, bool me = true}) => {
      'GET home': (_, _) => fakeHome(),
      if (me) 'GET me': (_, _) => {...fakeUser(), 'unread_notifications': notifications},
      'GET conversations': (_, _) => [
            {'id': 1, 'side': 'buyer', 'store': {'id': 3, 'slug': 'k', 'name': 'Kedai', 'logo': null}, 'unread_count': unreadChat, 'last_message': null},
          ],
      'GET notifications': (_, _) => [],
    };

void main() {
  testWidgets('Home shows bell + chat with unread badges and opens the Account pages', (tester) async {
    usePhone(tester);
    final h = Harness(api: FakeApi(routes: _routes()), token: 'tok');
    await h.pump(tester, location: '/home');
    await tester.pumpAndSettle();

    expect(find.descendant(of: find.byKey(const Key('inbox-bell-badge')), matching: find.text('3')), findsOneWidget);
    expect(find.descendant(of: find.byKey(const Key('inbox-chat-badge')), matching: find.text('2')), findsOneWidget);

    await tester.tap(find.byKey(const Key('inbox-bell')));
    await tester.pumpAndSettle();
    expect(h.router.state.uri.path, '/account/notifications');

    h.router.go('/home');
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('inbox-chat')));
    await tester.pumpAndSettle();
    expect(h.router.state.uri.path, '/account/messages');
  });

  testWidgets('badges hide at zero, icons stay', (tester) async {
    usePhone(tester);
    final h = Harness(api: FakeApi(routes: _routes(notifications: 0, unreadChat: 0)), token: 'tok');
    await h.pump(tester, location: '/home');
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('inbox-bell')), findsOneWidget);
    expect(find.byKey(const Key('inbox-chat')), findsOneWidget);
    expect(find.byKey(const Key('inbox-bell-badge')), findsNothing);
    expect(find.byKey(const Key('inbox-chat-badge')), findsNothing);
  });

  testWidgets('logged out: icons show without badges, a tap goes to login (Haze 11-11)', (tester) async {
    usePhone(tester);
    final h = Harness(api: FakeApi(routes: _routes(me: false)));
    await h.pump(tester, location: '/home');
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('inbox-bell')), findsOneWidget);
    expect(find.byKey(const Key('inbox-chat')), findsOneWidget);
    expect(find.byKey(const Key('inbox-bell-badge')), findsNothing);
    expect(h.api.calls.where((c) => c.startsWith('GET conversations')), isEmpty);

    await tester.tap(find.byKey(const Key('inbox-chat')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('login-email')), findsOneWidget);
  });

  testWidgets('Seller Dashboard shows the same icons and badges', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      ...sellerBaseRoutes(),
      'GET me': (_, _) => {...fakeUser(storeStatus: 'approved'), 'unread_notifications': 4},
      'GET seller/dashboard': (_, _) => fakeDashboard(),
      'GET conversations': (_, _) => [
            {'id': 1, 'side': 'seller', 'store': {'id': 3, 'slug': 'k', 'name': 'Kedai', 'logo': null}, 'buyer': {'name': 'B'}, 'unread_count': 5, 'last_message': null},
          ],
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/seller');
    await tester.pumpAndSettle();

    expect(find.descendant(of: find.byKey(const Key('inbox-bell-badge')), matching: find.text('4')), findsOneWidget);
    expect(find.descendant(of: find.byKey(const Key('inbox-chat-badge')), matching: find.text('5')), findsOneWidget);
    expect(find.byKey(const Key('seller-back-to-buying')), findsOneWidget);
  });
}
