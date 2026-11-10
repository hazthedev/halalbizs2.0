import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/core/auth/session.dart';
import 'package:halalbizs/core/router.dart';

import '../helpers.dart';

void main() {
  group('soukRedirect', () {
    test('guest is bounced from guarded routes only', () {
      const out = SignedOut();
      expect(soukRedirect(out, Uri.parse('/cart')), '/login?from=%2Fcart');
      expect(soukRedirect(out, Uri.parse('/orders/12')), '/login?from=%2Forders%2F12');
      expect(soukRedirect(out, Uri.parse('/seller/products')), startsWith('/login'));
      expect(soukRedirect(out, Uri.parse('/home')), isNull);
      expect(soukRedirect(out, Uri.parse('/search')), isNull);
      expect(soukRedirect(out, Uri.parse('/p/madu')), isNull);
      expect(soukRedirect(out, Uri.parse('/s/kedai')), isNull);
      expect(soukRedirect(out, Uri.parse('/c/makanan')), isNull);
      expect(soukRedirect(out, Uri.parse('/login')), isNull);
    });

    test('two-factor pending pins the user to /two-factor', () {
      const tf = TwoFactorPending('abc', 'email');
      expect(soukRedirect(tf, Uri.parse('/home')), '/two-factor');
      expect(soukRedirect(tf, Uri.parse('/two-factor')), isNull);
      expect(soukRedirect(tf, Uri.parse('/login?from=%2Fcart')), '/two-factor?from=%2Fcart');
      expect(soukRedirect(const SignedOut(), Uri.parse('/two-factor')), '/login');
    });

    test('signed-in users leave auth pages; seller shell needs an approved store', () {
      final buyer = SignedIn(Profile(fakeUser()));
      expect(soukRedirect(buyer, Uri.parse('/login')), '/home');
      expect(soukRedirect(buyer, Uri.parse('/login?from=%2Fcart')), '/cart');
      expect(soukRedirect(buyer, Uri.parse('/seller')), '/account');
      final seller = SignedIn(Profile(fakeUser(storeStatus: 'approved')));
      expect(soukRedirect(seller, Uri.parse('/seller/orders')), isNull);
      expect(soukRedirect(const SessionLoading(), Uri.parse('/cart')), isNull);
    });

    test('from is honoured only for in-app, non-auth paths', () {
      final buyer = SignedIn(Profile(fakeUser()));
      expect(soukRedirect(buyer, Uri.parse('/login?from=%2Forders%2F12%3Ftab%3Dall')), '/orders/12?tab=all');
      expect(soukRedirect(buyer, Uri.parse('/login?from=https%3A%2F%2Fevil.test')), '/home');
      expect(soukRedirect(buyer, Uri.parse('/login?from=%2F%2Fevil.test')), '/home');
      expect(soukRedirect(buyer, Uri.parse('/login?from=%2Flogin')), '/home');
      expect(safeFrom('/two-factor?from=%2Fcart'), '/home');
      expect(safeFrom(null), '/home');
    });
  });

  testWidgets('guest opening /cart lands on /login', (tester) async {
    usePhone(tester);
    final h = Harness(api: FakeApi(routes: {
      'GET products': (_, _) => [fakeProduct()],
      'GET categories': (_, _) => [],
    }));
    await h.pump(tester);
    h.router.go('/cart');
    await tester.pumpAndSettle();
    expect(h.router.state.uri.path, '/login');
    expect(h.router.state.uri.queryParameters['from'], '/cart');
    expect(find.byKey(const Key('login-submit')), findsOneWidget);
  });
}
