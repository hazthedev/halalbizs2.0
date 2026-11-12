import 'dart:convert';

import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/core/api/api_client.dart';
import 'package:halalbizs/core/db/app_db.dart';
import 'package:halalbizs/core/version_gate.dart';

import '../features/home_test.dart';
import '../helpers.dart';

Harness harness(Object Function() config, {String installed = '1.2.0', AppDb? db}) => Harness(
      api: FakeApi(routes: {'GET config': (_, _) => config(), 'GET home': (_, _) => fakeHome()}),
      db: db,
      overrides: [installedVersionProvider.overrideWith((ref) => installed)],
    );

void main() {
  test('versions compare as numbers', () {
    expect(isBelow('1.9.0', '1.10.0'), isTrue);
    expect(isBelow('1.10.0', '1.9.0'), isFalse);
    expect(isBelow('1.4.0+7', '1.4.0'), isFalse);
    expect(isBelow('1.4', '1.4.1'), isTrue);
  });

  testWidgets('below min_app_version shows Update required', (tester) async {
    usePhone(tester);
    final h = harness(() => {'min_app_version': '1.4.0', 'maintenance': {'enabled': false, 'message': ''}});
    await h.pump(tester);
    await tester.pumpAndSettle();
    expect(find.text('Update required'), findsOneWidget);
    expect(find.byKey(const Key('home-empty')), findsNothing);
  });

  testWidgets('at or above the minimum lets the user in', (tester) async {
    usePhone(tester);
    final h = harness(() => {'min_app_version': '1.2.0', 'maintenance': {'enabled': false, 'message': ''}});
    await h.pump(tester);
    await tester.pumpAndSettle();
    expect(find.text('Update required'), findsNothing);
    expect(find.byKey(const Key('home-empty')), findsOneWidget);
  });

  testWidgets('maintenance shows the message; Try again re-reads config and opens the app', (tester) async {
    usePhone(tester);
    var on = true;
    final h = harness(() => {'min_app_version': '1.0.0', 'maintenance': {'enabled': on, 'message': 'Back at 3pm'}});
    await h.pump(tester);
    await tester.pumpAndSettle();
    expect(find.text('Back at 3pm'), findsOneWidget);
    on = false;
    await tester.tap(find.text('Try again'));
    await tester.pumpAndSettle();
    expect(find.text('Back at 3pm'), findsNothing);
    expect(find.byKey(const Key('home-empty')), findsOneWidget);
  });

  testWidgets('offline with no saved config lets the user in', (tester) async {
    usePhone(tester);
    final h = harness(ApiException.offline);
    await h.pump(tester);
    await tester.pumpAndSettle();
    expect(find.text('Update required'), findsNothing);
    expect(find.byKey(const Key('home-empty')), findsOneWidget);
  });

  testWidgets('offline with a saved config still blocks an old install', (tester) async {
    usePhone(tester);
    final db = AppDb.test(NativeDatabase.memory());
    await db.putResponse('config', jsonEncode({'min_app_version': '2.0.0', 'maintenance': {'enabled': false}}), null);
    final h = harness(ApiException.offline, db: db);
    await h.pump(tester);
    await tester.pumpAndSettle();
    expect(find.text('Update required'), findsOneWidget);
  });
}
