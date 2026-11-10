import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/core/api/api_client.dart';
import 'package:halalbizs/core/auth/session.dart';

import '../helpers.dart';

void main() {
  testWidgets('login form validates before calling the API', (tester) async {
    usePhone(tester);
    final h = Harness();
    await h.pump(tester, location: '/login');

    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();
    expect(find.text('This field is required.'), findsNWidgets(2));

    await tester.enterText(find.byKey(const Key('login-email')), 'not-an-email');
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();
    expect(find.text('Enter a valid email address.'), findsOneWidget);
    expect(h.api.calls.where((c) => c.startsWith('POST auth/login')), isEmpty);
  });

  testWidgets('login → two-factor challenge → code → signed in', (tester) async {
    usePhone(tester);
    Object? tfBody;
    final api = FakeApi(routes: {
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'POST auth/login': (_, body) => {
            'two_factor': {'challenge': 'chal-1', 'method': 'email'},
          },
      'POST auth/two-factor': (_, body) {
        tfBody = body;
        return {'token': 'tok', 'user': fakeUser()};
      },
    });
    final h = Harness(api: api);
    await h.pump(tester, location: '/login?from=%2Fcart');

    await tester.enterText(find.byKey(const Key('login-email')), 'buyer@halalbizs.test');
    await tester.enterText(find.byKey(const Key('login-password')), 'password');
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();

    expect(h.container.read(sessionProvider), isA<TwoFactorPending>());
    expect(h.router.state.uri.path, '/two-factor');
    expect(find.byKey(const Key('two-factor-code')), findsOneWidget);
    expect(find.text('Enter the code we emailed you.'), findsOneWidget);

    await tester.enterText(find.byKey(const Key('two-factor-code')), '123456');
    await tester.tap(find.byKey(const Key('two-factor-submit')));
    await tester.pumpAndSettle();

    expect(h.container.read(sessionProvider), isA<SignedIn>());
    expect(h.router.state.uri.path, '/cart');
    final tf = tfBody as Map;
    expect(tf['challenge'], 'chal-1');
    expect(tf['code'], '123456');
    expect(tf['device_name'], 'Test device');
  });

  for (final method in ['totp', 'email']) {
    testWidgets('$method two-factor sends device_name, and a 422 on another field is shown', (tester) async {
      usePhone(tester);
      Object? tfBody;
      final api = FakeApi(routes: {
        'GET products': (_, _) => [],
        'GET categories': (_, _) => [],
        'POST auth/login': (_, _) => {
              'two_factor': {'challenge': 'chal-1', 'method': method},
            },
        'POST auth/two-factor': (_, body) {
          tfBody = body;
          return ApiException('validation_failed', 'The given data was invalid.',
              status: 422, fields: {'challenge': ['This sign-in has expired. Sign in again.']});
        },
      });
      final h = Harness(api: api);
      await h.pump(tester, location: '/login');
      await tester.enterText(find.byKey(const Key('login-email')), 'buyer@halalbizs.test');
      await tester.enterText(find.byKey(const Key('login-password')), 'password');
      await tester.tap(find.byKey(const Key('login-submit')));
      await tester.pumpAndSettle();
      await tester.enterText(find.byKey(const Key('two-factor-code')), '123456');
      await tester.tap(find.byKey(const Key('two-factor-submit')));
      await tester.pumpAndSettle();

      expect((tfBody as Map)['device_name'], 'Test device');
      expect(find.text('This sign-in has expired. Sign in again.'), findsOneWidget);
    });
  }

  testWidgets('422 from login lands on the email field', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      'GET products': (_, _) => [],
      'GET categories': (_, _) => [],
      'POST auth/login': (_, _) => ApiException('validation_failed', 'The given data was invalid.',
          status: 422, fields: {'email': ['These credentials do not match our records.']}),
    });
    final h = Harness(api: api);
    await h.pump(tester, location: '/login');
    await tester.enterText(find.byKey(const Key('login-email')), 'buyer@halalbizs.test');
    await tester.enterText(find.byKey(const Key('login-password')), 'wrong');
    await tester.tap(find.byKey(const Key('login-submit')));
    await tester.pumpAndSettle();
    expect(find.text('These credentials do not match our records.'), findsOneWidget);
    expect(h.container.read(sessionProvider), isA<SignedOut>());
  });
}
