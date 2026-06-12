<?php

namespace App\Services;

use App\Models\Store;

class ShippingCalculator
{
    /**
     * Shipping fee a store charges to an address state, in sen.
     * v1: seller-defined flat rate or per-state matrix (docs/06 §A).
     */
    public function feeForStore(Store $store, string $state, int $itemsSubtotalSen): int
    {
        if ($store->free_shipping_over_sen !== null && $itemsSubtotalSen >= $store->free_shipping_over_sen) {
            return 0;
        }

        if ($store->shipping_mode === 'matrix') {
            $matrix = $store->shipping_matrix ?? [];

            return (int) ($matrix[$state] ?? $store->shipping_flat_fee_sen);
        }

        return (int) $store->shipping_flat_fee_sen;
    }
}
