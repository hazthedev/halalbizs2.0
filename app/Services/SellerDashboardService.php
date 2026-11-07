<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Enums\SubOrderStatus;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\SubOrder;
use App\Support\DisplayTime;
use Illuminate\Database\Eloquent\Collection;

/**
 * The seller dashboard's headline numbers, shared by the Livewire Dashboard
 * (stat cards, to-do strip, recent orders) and GET /api/v1/seller/dashboard.
 * All money is integer sen.
 */
class SellerDashboardService
{
    /** Statuses that count as realised revenue (paid + progressing). */
    public const REVENUE_STATUSES = [
        SubOrderStatus::Confirmed,
        SubOrderStatus::Processing,
        SubOrderStatus::Shipped,
        SubOrderStatus::Delivered,
        SubOrderStatus::Completed,
    ];

    public function __construct(private LedgerService $ledger) {}

    /**
     * Everything the API dashboard shows: the counts plus balances and the
     * recent orders.
     *
     * @return array{
     *     today_sales_sen: int,
     *     today_orders: int,
     *     orders_by_status: array<string, int>,
     *     to_ship: int,
     *     live_products: int,
     *     low_stock: int,
     *     available_sen: int,
     *     held_sen: int,
     *     recent_orders: Collection<int, SubOrder>
     * }
     */
    public function stats(Store $store): array
    {
        return $this->counts($store) + [
            'available_sen' => $this->ledger->availableBalanceSen($store),
            'held_sen' => $store->heldBalanceSen(),
            'recent_orders' => $this->recentOrders($store),
        ];
    }

    /**
     * The cheap count queries only — what the Livewire stat cards need.
     *
     * @return array{
     *     today_sales_sen: int,
     *     today_orders: int,
     *     orders_by_status: array<string, int>,
     *     to_ship: int,
     *     live_products: int,
     *     low_stock: int
     * }
     */
    public function counts(Store $store): array
    {
        $today = [DisplayTime::dayStart(), DisplayTime::dayEnd()];

        $byStatus = $store->subOrders()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'today_sales_sen' => (int) $store->subOrders()
                ->whereIn('status', self::REVENUE_STATUSES)
                ->whereBetween('created_at', $today)
                ->sum('total_sen'),
            'today_orders' => $store->subOrders()->whereBetween('created_at', $today)->count(),
            'orders_by_status' => collect(SubOrderStatus::cases())
                ->mapWithKeys(fn (SubOrderStatus $status) => [$status->value => (int) ($byStatus[$status->value] ?? 0)])
                ->all(),
            'to_ship' => (int) ($byStatus[SubOrderStatus::Confirmed->value] ?? 0) + (int) ($byStatus[SubOrderStatus::Processing->value] ?? 0),
            'live_products' => $store->products()->where('status', ProductStatus::Live)->count(),
            'low_stock' => ProductVariant::query()
                ->lowStock() // respects each variant's low_stock_threshold (default 5)
                ->whereHas('product', fn ($query) => $query
                    ->where('store_id', $store->id)
                    ->where('status', ProductStatus::Live))
                ->count(),
        ];
    }

    /** @return Collection<int, SubOrder> */
    public function recentOrders(Store $store, int $limit = 5): Collection
    {
        return $store->subOrders()->with('order.user')->latest()->latest('id')->limit($limit)->get();
    }
}
