<?php

namespace App\Livewire\Seller;

use App\Enums\ProductStatus;
use App\Enums\SubOrderStatus;
use App\Livewire\Concerns\CurrentStore;
use App\Models\ProductVariant;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Seller dashboard (docs/07 §A2) — stat cards, to-do strip, recent orders,
 * 14-day sales sparkline (placeholder data until M4).
 */
#[Layout('layouts.seller')]
class Dashboard extends Component
{
    use CurrentStore;

    public const LOW_STOCK_THRESHOLD = 5;

    public int $todayOrders = 0;

    public int $toShip = 0;

    public int $liveProducts = 0;

    public int $lowStock = 0;

    public function mount(): void
    {
        $store = $this->currentStore();

        $this->todayOrders = $store->subOrders()
            ->whereDate('created_at', today())
            ->count();

        $this->toShip = $store->subOrders()
            ->whereIn('status', [SubOrderStatus::Confirmed, SubOrderStatus::Processing])
            ->count();

        $this->liveProducts = $store->products()
            ->where('status', ProductStatus::Live)
            ->count();

        $this->lowStock = ProductVariant::query()
            ->where('stock', '<', self::LOW_STOCK_THRESHOLD)
            ->whereHas('product', fn ($query) => $query
                ->where('store_id', $store->id)
                ->where('status', ProductStatus::Live))
            ->count();
    }

    public function render()
    {
        $store = $this->currentStore();

        $recentOrders = $store->subOrders()
            ->latest()
            ->limit(5)
            ->get();

        // Placeholder until M4 wires real sub-order totals per day.
        $sparkline = collect(range(13, 0))->map(fn (int $daysAgo) => [
            'date' => today()->subDays($daysAgo),
            'total_sen' => 0,
        ]);

        return view('livewire.seller.dashboard', [
            'recentOrders' => $recentOrders,
            'sparkline' => $sparkline,
        ])->title(__('Dashboard'));
    }
}
