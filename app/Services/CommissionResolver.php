<?php

namespace App\Services;

use App\Models\Category;
use App\Livewire\Admin\Finance\Commission;
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

    /**
     * Plain-words rate for a seller or applicant: the store override if set,
     * otherwise the global rate, widened to a range when categories carry their own.
     */
    public function summary(?Store $store = null): string
    {
        if ($store?->commission_rate !== null) {
            return __(':rate% of each sale', ['rate' => Commission::formatRate((float) $store->commission_rate)]);
        }

        $rates = Category::query()->whereNotNull('commission_rate')->pluck('commission_rate')
            ->map(fn ($rate) => (float) $rate)->push($this->settings->global_rate);
        [$min, $max] = [$rates->min(), $rates->max()];

        if ($min === $max) {
            return __(':rate% of each sale', ['rate' => Commission::formatRate($min)]);
        }

        return __(':min% to :max% of each sale, by category', ['min' => Commission::formatRate($min), 'max' => Commission::formatRate($max)]);
    }
}
