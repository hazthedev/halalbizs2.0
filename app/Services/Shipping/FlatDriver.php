<?php

namespace App\Services\Shipping;

/** Seller's single flat fee. */
class FlatDriver implements ShippingDriver
{
    public function fee(ShippingContext $context): int
    {
        return (int) $context->store->shipping_flat_fee_sen;
    }
}
