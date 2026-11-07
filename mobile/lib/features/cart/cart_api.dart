import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';

enum CartOutcome {
  added,

  /// `POST cart/items` is not on the server yet (slice 2) — 404.
  notReady,
}

/// Cart writes (`auth:sanctum`). Wired to the plan's endpoints so slice 5
/// only removes the [CartOutcome.notReady] branch.
class CartApi {
  CartApi(this._api);

  final ApiClient _api;

  /// `POST cart/items {variant_id, qty}`.
  Future<CartOutcome> addItem({required int variantId, required int qty}) async {
    try {
      await _api.post('cart/items', body: {'variant_id': variantId, 'qty': qty});
      return CartOutcome.added;
    } on ApiException catch (e) {
      // ponytail: stub until slice 2 ships the cart API; remove this branch then.
      if (e.isNotFound) return CartOutcome.notReady;
      rethrow;
    }
  }
}

final cartApiProvider = Provider<CartApi>((ref) => CartApi(ref.watch(apiClientProvider)));
