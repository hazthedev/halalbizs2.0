<?php

namespace App\Services\Shipping;

use App\Models\Store;

/** Everything a shipping driver needs to quote a fee for one store's parcel. */
final class ShippingContext
{
    public function __construct(
        public readonly Store $store,
        public readonly string $destinationState,
        public readonly ?string $destinationPostcode,
        public readonly int $itemsSubtotalSen,
        public readonly int $weightGrams = 0,
    ) {}
}
