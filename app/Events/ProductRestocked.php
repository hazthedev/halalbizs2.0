<?php

namespace App\Events;

use App\Models\ProductVariant;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when StockService takes a variant's stock from out-of-stock (< 1) back
 * up to >= 1. The hook point for back-in-stock alerts.
 */
class ProductRestocked
{
    use Dispatchable;

    public function __construct(public ProductVariant $variant) {}
}
