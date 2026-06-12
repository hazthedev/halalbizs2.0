<?php

namespace App\Livewire\Admin;

use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Enums\SubOrderStatus;
use App\Models\Order;
use App\Models\Payout;
use App\Models\Product;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Admin dashboard (docs/08 §A) — GMV/commission stat row with a period
 * picker, pending-queue cards with deep links, a 30-day GMV sparkline
 * (pure SVG, no JS lib), orders by status, and top stores by GMV.
 */
#[Layout('layouts.admin')]
class Dashboard extends Component
{
    public const CHART_DAYS = 30;

    public const TOP_STORES = 5;

    #[Url(except: '30d')]
    public string $period = '30d';

    public function mount(): void
    {
        if (! array_key_exists($this->period, $this->periods())) {
            $this->period = '30d';
        }
    }

    /** @return array<string, string> period key → label */
    public function periods(): array
    {
        return [
            'today' => __('Today'),
            '7d' => __('7 days'),
            '30d' => __('30 days'),
        ];
    }

    public function setPeriod(string $period): void
    {
        if (array_key_exists($period, $this->periods())) {
            $this->period = $period;
        }
    }

    public function render()
    {
        $start = $this->periodStart();

        // GMV — paid orders only (the platform's honest number).
        $gmvSen = (int) Order::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->where('paid_at', '>=', $start)
            ->sum('grand_total_sen');

        // Commission revenue ≈ completed sub-orders' commission. Shown as "—"
        // until the first sub-order completes with a commission amount.
        $commissionQuery = SubOrder::query()
            ->where('status', SubOrderStatus::Completed)
            ->where('completed_at', '>=', $start)
            ->whereNotNull('commission_sen');
        $commissionKnown = $commissionQuery->clone()->exists();
        $commissionSen = (int) $commissionQuery->sum('commission_sen');

        $ordersToday = Order::query()->where('placed_at', '>=', now()->startOfDay())->count();
        $newBuyersToday = User::query()->where('created_at', '>=', now()->startOfDay())->count();

        $ordersByStatus = SubOrder::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $topStores = SubOrder::query()
            ->where('status', SubOrderStatus::Completed)
            ->selectRaw('store_id, sum(total_sen) as gmv_sen, count(*) as completed_count')
            ->groupBy('store_id')
            ->orderByDesc('gmv_sen')
            ->limit(self::TOP_STORES)
            ->with('store')
            ->get();

        return view('livewire.admin.dashboard', [
            'gmvSen' => $gmvSen,
            'commissionKnown' => $commissionKnown,
            'commissionSen' => $commissionSen,
            'ordersToday' => $ordersToday,
            'newBuyersToday' => $newBuyersToday,
            'queues' => $this->queues(),
            'chart' => $this->gmvChart(),
            'ordersByStatus' => $ordersByStatus,
            'topStores' => $topStores,
        ])->title(__('Dashboard'));
    }

    private function periodStart(): CarbonInterface
    {
        return match ($this->period) {
            'today' => now()->startOfDay(),
            '7d' => now()->subDays(6)->startOfDay(),
            default => now()->subDays(29)->startOfDay(),
        };
    }

    /** @return list<array{label: string, count: int, url: ?string}> */
    private function queues(): array
    {
        return [
            [
                'label' => __('Seller applications'),
                'count' => Store::query()->where('status', StoreStatus::Pending)->count(),
                'url' => Route::has('admin.sellers.applications') ? route('admin.sellers.applications') : null,
            ],
            [
                'label' => __('Products pending review'),
                'count' => Product::query()->where('status', ProductStatus::PendingReview)->count(),
                'url' => Route::has('admin.catalog.moderation') ? route('admin.catalog.moderation') : null,
            ],
            [
                'label' => __('Payout requests'),
                'count' => Payout::query()->where('status', PayoutStatus::Requested)->count(),
                'url' => Route::has('admin.finance.payouts') ? route('admin.finance.payouts') : null,
            ],
            [
                // Returns engine arrives with M8 — placeholder card.
                'label' => __('Return escalations'),
                'count' => 0,
                'url' => null,
            ],
        ];
    }

    /**
     * 30-day GMV sparkline — paid orders bucketed per day in PHP (keeps the
     * grouping portable across SQLite/MySQL). Amounts stay integer sen;
     * floats appear only as SVG pixel coordinates, never as money.
     *
     * @return array{points: string, baseline: string, maxSen: int, totalSen: int, firstDay: string, lastDay: string}
     */
    private function gmvChart(): array
    {
        $days = collect(range(self::CHART_DAYS - 1, 0))
            ->map(fn (int $back) => now()->subDays($back)->toDateString());

        $byDay = Order::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->where('paid_at', '>=', now()->subDays(self::CHART_DAYS - 1)->startOfDay())
            ->get(['paid_at', 'grand_total_sen'])
            ->groupBy(fn (Order $order) => $order->paid_at->toDateString())
            ->map(fn ($orders) => (int) $orders->sum('grand_total_sen'));

        $series = $days->map(fn (string $day) => (int) ($byDay[$day] ?? 0))->values();
        $maxSen = max(1, (int) $series->max());

        // viewBox 0 0 600 140 with 10px padding on every side.
        $stepX = 580 / (self::CHART_DAYS - 1);
        $points = $series
            ->map(function (int $sen, int $i) use ($maxSen, $stepX) {
                $x = 10 + $i * $stepX;
                $y = 130 - ($sen / $maxSen) * 120;

                return round($x, 1).','.round($y, 1);
            })
            ->implode(' ');

        return [
            'points' => $points,
            'baseline' => '10,130 590,130',
            'maxSen' => (int) $series->max(),
            'totalSen' => (int) $series->sum(),
            'firstDay' => $days->first(),
            'lastDay' => $days->last(),
        ];
    }
}
