import 'package:drift/native.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:halalbizs/core/api/api_client.dart';
import 'package:halalbizs/core/auth/session.dart';
import 'package:halalbizs/core/auth/token_store.dart';
import 'package:halalbizs/core/db/app_db.dart';
import 'package:halalbizs/core/offline/offline.dart';
import 'package:halalbizs/features/catalog/catalog_api.dart';
import 'package:halalbizs/features/orders/orders_api.dart';

import '../helpers.dart';
import 'follow_test.dart' show fakeStore;

class _Who extends Notifier<Profile?> {
  @override
  Profile? build() => Profile({...fakeUser(), 'id': 7});
  void become(int id) => state = Profile({...fakeUser(), 'id': id});
}

final _who = NotifierProvider<_Who, Profile?>(_Who.new);

void main() {
  // Kept-alive account reads must refetch when a different account signs in
  // on the same app run, or account B sees account A's counts and follows.
  test('order shortcut counts and store follow state refetch for the next account', () async {
    final api = FakeApi(routes: {
      'GET orders/quick-status': (_, _) => [],
      'GET stores/kedai-madu': (_, _) => fakeStore(following: true),
    });
    final db = AppDb.test(NativeDatabase.memory());
    final c = ProviderContainer(overrides: [
      tokenStoreProvider.overrideWith((ref) => MemoryTokenStore('t')),
      apiClientProvider.overrideWith((ref) => api),
      appDbProvider.overrideWith((ref) => db),
      isOfflineProvider.overrideWith((ref) => Stream.value(false)),
      profileProvider.overrideWith((ref) => ref.watch(_who)),
    ]);
    addTearDown(c.dispose);
    addTearDown(db.close);
    final keep = [
      c.listen(orderQuickStatusProvider, (_, _) {}),
      c.listen(storeProvider('kedai-madu'), (_, _) {}),
    ];
    addTearDown(() {
      for (final s in keep) {
        s.close();
      }
    });

    await c.read(orderQuickStatusProvider.future);
    await c.read(storeProvider('kedai-madu').future);
    c.read(_who.notifier).become(8);
    await c.read(orderQuickStatusProvider.future);
    await c.read(storeProvider('kedai-madu').future);

    expect(api.calls.where((x) => x == 'GET orders/quick-status'), hasLength(2));
    expect(api.calls.where((x) => x == 'GET stores/kedai-madu'), hasLength(2));
  });
}
