<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Store;
use App\Settings\CommissionSettings;

/**
 * Resolver hierarchy (locked decision): seller override → category chain upward → global default.
 */
class CommissionResolver
{
    public function __construct(private CommissionSettings $settings) {}

    public function resolve(Store $store, ?Category $category = null): float
    {
        if ($store->commission_rate !== null) {
            return (float) $store->commission_rate;
        }

        if ($category !== null) {
            $categoryRate = $category->effectiveCommissionRate();

            if ($categoryRate !== null) {
                return $categoryRate;
            }
        }

        return $this->settings->global_rate;
    }
}
