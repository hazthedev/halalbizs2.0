<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductView;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Content-based product recommender (docs: no ML, no new deps). Blends a
 * buyer's recent purchases, viewed products and wishlist into a category +
 * store affinity, scores live in-stock candidates against it, and falls back
 * to popularity for cold-start. Pure Eloquent + integer-friendly scoring.
 */
class RecommendationService
{
    private const CANDIDATE_POOL = 60;

    private const VIEW_LOOKBACK_DAYS = 60;

    private const MAX_VIEWS = 20;

    /** Affinity weights per signal (summed when a category appears in several). */
    private const W_PURCHASE_CATEGORY = 5;

    private const W_VIEW_CATEGORY = 3;

    private const W_WISHLIST_CATEGORY = 2;

    private const W_PURCHASE_STORE = 2;

    private const W_VIEW_STORE = 1;

    /**
     * Personalised picks for a buyer. Cached for 30 min, keyed by a cheap
     * behaviour signature so it busts when they buy/view/wishlist something.
     */
    public function forUser(User $user, int $limit = 12, ?int $excludeProductId = null): Collection
    {
        $signature = $this->behaviourSignature($user);

        // Cache only the ranked ids — caching Eloquent models breaks under
        // cache.serializable_classes=false (they return __PHP_Incomplete_Class).
        $ids = Cache::remember(
            "recs:user:{$user->id}:{$signature}:{$limit}:".($excludeProductId ?? 0),
            now()->addMinutes(30),
            fn () => $this->buildForUser($user, $limit, $excludeProductId)->modelKeys()
        );

        return $this->hydrate($ids);
    }

    /**
     * Re-fetch products for cached ids, preserving rank order + eager loads.
     *
     * @param  array<int>  $ids
     */
    private function hydrate(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        $products = Product::query()
            ->whereIn('id', $ids)
            ->with(['variants', 'media', 'store'])
            ->get()
            ->keyBy('id');

        return collect($ids)->map(fn ($id) => $products->get($id))->filter()->values();
    }

    /**
     * Guest path: affinity from the passed (localStorage) viewed product ids,
     * else popular. Not cached — the id set is request-specific.
     */
    public function forViewedIds(array $productIds, int $limit = 12, ?int $excludeProductId = null): Collection
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));

        if ($productIds === []) {
            return $this->popular($limit, $excludeProductId !== null ? [$excludeProductId] : []);
        }

        $viewed = Product::query()->whereIn('id', $productIds)->get(['id', 'category_id', 'store_id']);
        $categoryWeights = $viewed->groupBy('category_id')->map(fn () => self::W_VIEW_CATEGORY)->all();
        $storeWeights = $viewed->groupBy('store_id')->map(fn () => self::W_VIEW_STORE)->all();

        $exclude = $productIds;
        if ($excludeProductId !== null) {
            $exclude[] = $excludeProductId;
        }

        return $this->rank($categoryWeights, $storeWeights, $exclude, $limit);
    }

    private function buildForUser(User $user, int $limit, ?int $excludeProductId): Collection
    {
        // ── Purchase signal: paid orders → completed/delivered sub-orders ──
        $purchased = OrderItem::query()
            ->whereNotNull('product_id')
            ->whereHas('subOrder', fn (Builder $q) => $q
                ->whereIn('status', [SubOrderStatus::Completed, SubOrderStatus::Delivered])
                ->whereHas('order', fn (Builder $o) => $o
                    ->where('user_id', $user->id)
                    ->where('payment_status', PaymentStatus::Paid)))
            ->with('product:id,category_id,store_id')
            ->get()
            ->pluck('product')
            ->filter();

        // ── View signal: recent server-side product views ──
        $viewed = ProductView::query()
            ->where('user_id', $user->id)
            ->where('viewed_at', '>=', now()->subDays(self::VIEW_LOOKBACK_DAYS))
            ->orderByDesc('viewed_at')
            ->limit(self::MAX_VIEWS)
            ->with('product:id,category_id,store_id')
            ->get()
            ->pluck('product')
            ->filter();

        // ── Wishlist signal ──
        $wishlisted = Wishlist::query()
            ->where('user_id', $user->id)
            ->with('product:id,category_id,store_id')
            ->get()
            ->pluck('product')
            ->filter();

        // Build summed category + store affinity.
        $categoryWeights = [];
        $storeWeights = [];

        foreach ([[$purchased, self::W_PURCHASE_CATEGORY, self::W_PURCHASE_STORE],
            [$viewed, self::W_VIEW_CATEGORY, self::W_VIEW_STORE],
            [$wishlisted, self::W_WISHLIST_CATEGORY, 0]] as [$set, $catW, $storeW]) {
            foreach ($set as $product) {
                $categoryWeights[$product->category_id] = ($categoryWeights[$product->category_id] ?? 0) + $catW;
                if ($storeW > 0) {
                    $storeWeights[$product->store_id] = ($storeWeights[$product->store_id] ?? 0) + $storeW;
                }
            }
        }

        // Discover NEW products: exclude everything the buyer already knows.
        $exclude = $purchased->pluck('id')
            ->merge($viewed->pluck('id'))
            ->merge($wishlisted->pluck('id'))
            ->push($excludeProductId)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($categoryWeights === [] && $storeWeights === []) {
            return $this->popular($limit, $exclude); // cold start
        }

        return $this->rank($categoryWeights, $storeWeights, $exclude, $limit);
    }

    /**
     * Score live, in-stock candidates in the affinity categories/stores, then
     * top up with popularity so the strip is always full.
     *
     * @param  array<int,int>  $categoryWeights
     * @param  array<int,int>  $storeWeights
     * @param  array<int>  $exclude
     */
    private function rank(array $categoryWeights, array $storeWeights, array $exclude, int $limit): Collection
    {
        $candidates = $this->liveInStock()
            ->whereNotIn('products.id', $exclude ?: [0])
            ->where(function (Builder $q) use ($categoryWeights, $storeWeights) {
                if ($categoryWeights !== []) {
                    $q->whereIn('category_id', array_keys($categoryWeights));
                }
                if ($storeWeights !== []) {
                    $q->orWhereIn('store_id', array_keys($storeWeights));
                }
            })
            ->with(['variants', 'media', 'store'])
            ->limit(self::CANDIDATE_POOL)
            ->get();

        $ranked = $candidates
            ->sortByDesc(function (Product $product) use ($categoryWeights, $storeWeights) {
                $score = ($categoryWeights[$product->category_id] ?? 0)
                    + ($storeWeights[$product->store_id] ?? 0);

                // Popularity + rating only break ties — kept < 1 so affinity dominates.
                $score += min((int) $product->sold_count, 5000) / 5000 * 0.6;
                $score += ((float) $product->rating_avg) / 5 * 0.4;

                return $score;
            })
            ->take($limit)
            ->values();

        if ($ranked->count() < $limit) {
            $topUp = $this->popular(
                $limit - $ranked->count(),
                array_merge($exclude, $ranked->pluck('id')->all()),
            );
            $ranked = $ranked->concat($topUp)->values();
        }

        return $ranked;
    }

    /**
     * Frequently bought together (M1.8): products co-purchased in the same
     * orders as $productId, ranked by co-occurrence, live + in stock. Pure
     * Eloquent co-visitation — no ML.
     */
    public function frequentlyBoughtTogether(int $productId, int $limit = 6): Collection
    {
        $orderIds = OrderItem::query()
            ->where('product_id', $productId)
            ->join('sub_orders', 'order_items.sub_order_id', '=', 'sub_orders.id')
            ->distinct()
            ->pluck('sub_orders.order_id');

        if ($orderIds->isEmpty()) {
            return new Collection;
        }

        $coIds = OrderItem::query()
            ->join('sub_orders', 'order_items.sub_order_id', '=', 'sub_orders.id')
            ->whereIn('sub_orders.order_id', $orderIds)
            ->where('order_items.product_id', '!=', $productId)
            ->whereNotNull('order_items.product_id')
            ->selectRaw('order_items.product_id, COUNT(*) as freq')
            ->groupBy('order_items.product_id')
            ->orderByDesc('freq')
            ->limit($limit * 3)
            ->pluck('order_items.product_id');

        if ($coIds->isEmpty()) {
            return new Collection;
        }

        $products = Product::query()
            ->live()
            ->whereIn('id', $coIds)
            ->whereHas('variants', fn (Builder $query) => $query->where('stock', '>', 0))
            ->with(['variants', 'media', 'store'])
            ->get()
            ->keyBy('id');

        return $coIds->map(fn ($id) => $products->get($id))->filter()->take($limit)->values();
    }

    /**
     * Popularity fallback — live, in stock, most sold (mirrors Home 'top').
     *
     * @param  array<int>  $exclude
     */
    public function popular(int $limit, array $exclude = []): Collection
    {
        if ($limit <= 0) {
            return new Collection;
        }

        return $this->liveInStock()
            ->whereNotIn('products.id', $exclude ?: [0])
            ->with(['variants', 'media', 'store'])
            ->orderByDesc('sold_count')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    private function liveInStock(): Builder
    {
        return Product::query()
            ->live()
            ->whereHas('variants', fn (Builder $q) => $q->where('stock', '>', 0));
    }

    private function behaviourSignature(User $user): string
    {
        $lastOrder = $user->orders()->max('id') ?? 0;
        $lastView = ProductView::where('user_id', $user->id)->max('id') ?? 0;
        $wishlist = Wishlist::where('user_id', $user->id)->count();

        return "{$lastOrder}-{$lastView}-{$wishlist}";
    }
}
