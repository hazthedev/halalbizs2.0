<?php

use App\Enums\PaymentMethod;
use App\Services\CartService;
use App\Services\CheckoutService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;

/**
 * The oversell guard is `if ($variant->stock < $item->qty)` at
 * CheckoutService:180, and the only thing that makes it safe when two buyers hit
 * the same SKU in the same instant is the `lockForUpdate()` on the variants
 * query above it. Every other checkout test runs one transaction at a time, so
 * that lock has never been asserted anywhere — dropping it would leave the whole
 * suite green while the guard started reading a staleable row.
 *
 * ⚠ This asserts that checkout ASKS for the lock, not that InnoDB honours it.
 * That is deliberate. The first version of this file opened a second connection
 * to force real contention, and to make the fixture visible to that connection
 * it had to `DB::commit()` — which commits RefreshDatabase's own wrapping
 * transaction, so its rows outlived the test and turned three unrelated
 * SemanticSearchTest cases red. Proving MySQL implements FOR UPDATE is not our
 * job; proving we still ask for it is.
 */
beforeEach(fn () => $this->seed(RoleSeeder::class));

it('takes a row lock on the variants it is about to check stock against', function () {
    [$buyer, $address] = checkoutPageBuyer();
    $product = checkoutPageProduct(10_000);

    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    $locking = [];

    DB::listen(function ($query) use (&$locking) {
        $sql = strtolower($query->sql);

        if (str_contains($sql, 'product_variants') && str_contains($sql, 'for update')) {
            $locking[] = $query->sql;
        }
    });

    app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);

    expect($locking)->not->toBeEmpty(
        'checkout read product_variants without FOR UPDATE — two buyers can now pass the stock guard on the same unit'
    );
});
