<?php

namespace App\Services;

use App\Models\Store;
use App\Services\Shipping\EasyParcelDriver;
use App\Services\Shipping\FlatDriver;
use App\Services\Shipping\MatrixDriver;
use App\Services\Shipping\ShippingContext;
use App\Services\Shipping\ShippingDriver;

/**
 * Shipping fee a store charges to a destination, in sen. Dispatches to a
 * driver by the store's shipping_mode: flat / matrix (seller-defined) or
 * easyparcel (live courier rates). The seller-funded free-shipping threshold
 * is honoured before any driver runs.
 */
class ShippingCalculator
{
    public function feeForStore(
        Store $store,
        string $state,
        int $itemsSubtotalSen,
        ?string $postcode = null,
        int $weightGrams = 0,
    ): int {
        if ($store->free_shipping_over_sen !== null && $itemsSubtotalSen >= $store->free_shipping_over_sen) {
            return 0;
        }

        $context = new ShippingContext($store, $state, $postcode, $itemsSubtotalSen, $weightGrams);

        return $this->driverFor($store)->fee($context);
    }

    private function driverFor(Store $store): ShippingDriver
    {
        return match ($store->shipping_mode) {
            'matrix' => app(MatrixDriver::class),
            'easyparcel' => app(EasyParcelDriver::class),
            default => app(FlatDriver::class),
        };
    }
}
