import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/core/api/api_client.dart';

import '../helpers.dart';
import 'seller_helpers.dart';

void main() {
  testWidgets('payout sheet disables submit below the minimum and posts amount_sen otherwise', (tester) async {
    usePhone(tester);
    Object? sent;
    final api = FakeApi(routes: {
      ...sellerBaseRoutes(),
      'GET seller/earnings': (_, _) => fakeEarnings(),
      'POST seller/payouts': (_, body) {
        sent = body;
        return {'id': 2, 'payout_no': 'PO2611BBBB', 'amount_sen': (body as Map)['amount_sen'], 'status': 'requested', 'status_label': 'Requested'};
      },
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/seller/earnings');

    expect(find.text('RM 500.00'), findsOneWidget); // available
    expect(find.text('RM 23.75'), findsOneWidget); // ledger sale
    expect(find.text('-RM 1.25'), findsOneWidget); // ledger commission, signed
    expect(find.byKey(const Key('seller-payout-1')), findsOneWidget);

    await tester.tap(find.byKey(const Key('seller-earnings-request-payout')));
    await tester.pumpAndSettle();
    await tester.enterText(find.byKey(const Key('seller-payout-amount')), '20.00');
    await tester.pump();
    expect(find.text('Below the minimum payout.'), findsOneWidget);
    await tester.tap(find.byKey(const Key('seller-payout-submit')));
    await tester.pumpAndSettle();
    expect(sent, isNull);

    await tester.enterText(find.byKey(const Key('seller-payout-amount')), '150.00');
    await tester.pump();
    await tester.tap(find.byKey(const Key('seller-payout-submit')));
    await tester.pumpAndSettle();
    expect(sent, {'amount_sen': 15000});
    expect(find.text('Payout requested'), findsOneWidget);
  });

  testWidgets('409 payout_rejected keeps the sheet open with the server message', (tester) async {
    usePhone(tester);
    final api = FakeApi(routes: {
      ...sellerBaseRoutes(),
      'GET seller/earnings': (_, _) => fakeEarnings(),
      'POST seller/payouts': (_, _) => ApiException('payout_rejected', 'A payout request is already open.', status: 409),
    });
    final h = Harness(api: api, token: 'tok');
    await h.pump(tester, location: '/seller/earnings');
    await tester.tap(find.byKey(const Key('seller-earnings-request-payout')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('seller-payout-submit')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('seller-payout-submit')), findsOneWidget);
    expect(find.text('A payout request is already open.'), findsOneWidget);
  });
}
