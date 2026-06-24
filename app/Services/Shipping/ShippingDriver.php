<?php

namespace App\Services\Shipping;

/**
 * Computes a store's shipping fee (in sen) for a parcel. Flat and Matrix are
 * the seller-defined rates; EasyParcel quotes live courier rates. Adding a new
 * carrier means a new driver — checkout is unchanged.
 */
interface ShippingDriver
{
    public function fee(ShippingContext $context): int;
}
