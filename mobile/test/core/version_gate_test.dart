import 'dart:async';
import 'dart:convert';

import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/core/api/api_client.dart';
import 'package:halalbizs/core/db/app_db.dart';
import 'package:halalbizs/core/version_gate.dart';

import '../features/home_test.dart';
import '../helpers.dart';

Harness harness(Object Function() config, {String installed = '1.2.0', AppDb? db, FakeBrowser? browser}) => Harness(
      api: FakeApi(routes: {'GET config': (_, _) => config(), 'GET home': (_, _) => fakeHome()}),
      db: db,
      browser: browser,
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

  // HB-APP-STATES gap 10: the gate is not a dead end.
  testWidgets('Update required has an Update now button that opens the store page from config', (tester) async {
    usePhone(tester);
    final browser = FakeBrowser();
    const store = 'https://play.google.com/store/apps/details?id=com.halalbizs.app';
    final h = harness(
        () => {
              'min_app_version': '1.4.0',
              'maintenance': {'enabled': false, 'message': ''},
              'urls': {'store_android': store, 'store_ios': null},
            },
        browser: browser);
    await h.pump(tester);
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('gate-update-store')));
    await tester.pumpAndSettle();
    expect(browser.external, [store]);
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

  testWidgets('first frame does not wait for GET config; a too-old answer then blocks', (tester) async {
    usePhone(tester);
    final config = Completer<Map<String, dynamic>>();
    final api = _SlowConfigApi(config.future);
    final h = Harness(api: api, overrides: [installedVersionProvider.overrideWith((ref) => '1.2.0')]);
    await h.pump(tester);
    await tester.pump();
    expect(find.byKey(const Key('home-empty')), findsOneWidget, reason: 'home is up while config is still pending');
    config.complete({'min_app_version': '1.4.0', 'maintenance': {'enabled': false, 'message': ''}});
    await tester.pumpAndSettle();
    expect(find.text('Update required'), findsOneWidget);
    expect(find.byKey(const Key('home-empty')), findsNothing);
  });

  testWidgets('saved config says too old: blocks at the first frame even while fresh config is pending', (tester) async {
    usePhone(tester);
    final db = AppDb.test(NativeDatabase.memory());
    await db.putResponse('config', jsonEncode({'min_app_version': '2.0.0', 'maintenance': {'enabled': false}}), null);
    final h = Harness(api: _SlowConfigApi(Completer<Map<String, dynamic>>().future), db: db, overrides: [installedVersionProvider.overrideWith((ref) => '1.2.0')]);
    await h.pump(tester);
    await tester.pump();
    expect(find.text('Update required'), findsOneWidget);
  });
}

class _SlowConfigApi extends FakeApi {
  _SlowConfigApi(this.config) : super(routes: {'GET home': (_, _) => fakeHome()});

  final Future<Map<String, dynamic>> config;

  @override
  Future<dynamic> get(String path, {Map<String, dynamic>? query, void Function(Map<String, dynamic>)? meta}) async =>
      path == 'config' ? config : super.get(path, query: query, meta: meta);
}
