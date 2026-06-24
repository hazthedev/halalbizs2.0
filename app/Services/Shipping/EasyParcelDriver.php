<?php

namespace App\Services\Shipping;

use App\Services\EasyParcelService;

/**
 * Live courier rates via EasyParcel. Falls back to the seller's flat fee when
 * the API is unconfigured or unavailable, so checkout never blocks on a
 * third-party outage.
 */
class EasyParcelDriver implements ShippingDriver
{
    public function __construct(private EasyParcelService $easyParcel) {}

    public function fee(ShippingContext $context): int
    {
        return $this->easyParcel->cheapestRateSen($context)
            ?? (int) $context->store->shipping_flat_fee_sen;
    }
}
