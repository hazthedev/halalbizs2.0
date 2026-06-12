<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\Review;
use App\Models\Store;

/**
 * Keeps the cached rating columns on products and stores in sync
 * (docs/09 §C). Only VISIBLE reviews (is_hidden = false) count toward
 * the aggregates, so admin hide/unhide re-runs the math. Stores carry a
 * second pair of aggregates — service_rating_avg/count — built from the
 * optional seller_rating stars (one per sub-order). Mass updates via the
 * query builder skip model events on purpose — no Scout churn, no
 * observer loops.
 */
class ReviewObserver
{
    public function created(Review $review): void
    {
        $this->recalculate($review);
    }

    public function updated(Review $review): void
    {
        if ($review->wasChanged(['is_hidden', 'rating', 'seller_rating'])) {
            $this->recalculate($review);
        }
    }

    public function deleted(Review $review): void
    {
        $this->recalculate($review);
    }

    private function recalculate(Review $review): void
    {
        $this->refreshProduct($review->product_id);
        $this->refreshStore($review->store_id);
    }

    private function refreshProduct(int $productId): void
    {
        [$count, $average] = $this->visibleStats('product_id', $productId, 'rating');

        Product::withTrashed()->whereKey($productId)->update([
            'rating_count' => $count,
            'rating_avg' => $average,
        ]);
    }

    private function refreshStore(int $storeId): void
    {
        [$count, $average] = $this->visibleStats('store_id', $storeId, 'rating');
        [$serviceCount, $serviceAverage] = $this->visibleStats('store_id', $storeId, 'seller_rating');

        Store::withTrashed()->whereKey($storeId)->update([
            'rating_count' => $count,
            'rating_avg' => $average,
            'service_rating_count' => $serviceCount,
            'service_rating_avg' => $serviceAverage,
        ]);
    }

    /** @return array{0: int, 1: float} [count, avg rounded to 2dp] from visible reviews carrying $ratingColumn */
    private function visibleStats(string $column, int $id, string $ratingColumn): array
    {
        $stats = Review::query()
            ->toBase()
            ->where($column, $id)
            ->where('is_hidden', false)
            ->whereNotNull($ratingColumn)
            ->selectRaw("count(*) as total, avg({$ratingColumn}) as average")
            ->first();

        return [(int) $stats->total, round((float) ($stats->average ?? 0), 2)];
    }
}
