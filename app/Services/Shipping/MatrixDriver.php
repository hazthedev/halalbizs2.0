<?php

namespace App\Services\Shipping;

/** Per-state fee matrix, falling back to the flat fee for unlisted states. */
class MatrixDriver implements ShippingDriver
{
    public function fee(ShippingContext $context): int
    {
        $matrix = $context->store->shipping_matrix ?? [];

        return (int) ($matrix[$context->destinationState] ?? $context->store->shipping_flat_fee_sen);
    }
}
