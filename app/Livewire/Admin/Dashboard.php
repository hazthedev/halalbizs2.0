<?php

namespace App\Livewire\Admin;

use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Enums\SubOrderStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payout;
use App\Models\Product;
use App\Models\ProductBoost;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Admin dashboard (docs/08 §A) — GMV/commission/boost stat row with a period
 * picker, pending-queue cards with deep links, and four interactive ApexCharts:
 * GMV trend (area), orders by status (donut), top categories (bar), and new
 * buyers (line). Each chart is rendered via <x-ui.chart> and refreshed when the
 * period changes by dispatching a fresh single-payload event per chart.
 *
 * Money stays integer sen end-to-end (CLAUDE.md hard rule 1); emerald is used
 * only for the money/GMV series; the donut uses the shared status palette.
 */
#[Layout('layouts.admin')]
class Dashboard extends Component
{
    public const TOP_STORES = 5;

    public const TOP_CATEGORIES = 5;

    /** Status → donut colour (docs/03 §6 semantic palette). */
    private const STATUS_COLORS = [
        'pending_payment' => '#B45309',
        'confirmed' => '#191B1A',
        'processing' => '#191B1A',
        'shipped' => '#475569',
        'delivered' => '#475569',
        'completed' => '#047857',
        'cancelled' => '#BE123C',
        'return_requested' => '#BE123C',
        'returned' => '#BE123C',
        'refunded' => '#BE123C',
    ];

    #[Url(except: '30d')]
    public string $period = '30d';

    public function mount(): void
    {
        if (! array_key_exists($this->period, $this->periods())) {
            $this->period = '30d';
        }

        $this->dispatchCharts();
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

        // Period changed → push fresh payloads so the live charts redraw in
        // place (the chart divs are wire:ignore; they update via these events).
        $this->dispatchCharts();
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

        // Boost revenue — Σ ProductBoost.amount_sen charged in the period.
        $boostRevenueSen = (int) ProductBoost::query()
            ->where('created_at', '>=', $start)
            ->sum('amount_sen');

        $ordersToday = Order::query()->where('placed_at', '>=', now()->startOfDay())->count();
        $newBuyersToday = User::query()->where('created_at', '>=', now()->startOfDay())->count();

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
            'boostRevenueSen' => $boostRevenueSen,
            'ordersToday' => $ordersToday,
            'newBuyersToday' => $newBuyersToday,
            'queues' => $this->queues(),
            'gmvChart' => $this->gmvChartPayload(),
            'statusChart' => $this->statusChartPayload(),
            'categoriesChart' => $this->categoriesChartPayload(),
            'buyersChart' => $this->buyersChartPayload(),
            'topStores' => $topStores,
        ])->title(__('Dashboard'));
    }

    /**
     * Push one fresh payload per chart (single-payload shape the foundation's
     * $wire.on handler expects — one event name per chart). Called on mount and
     * after every period change.
     */
    private function dispatchCharts(): void
    {
        $this->dispatch('admin-gmv', $this->gmvChartPayload());
        $this->dispatch('admin-status', $this->statusChartPayload());
        $this->dispatch('admin-categories', $this->categoriesChartPayload());
        $this->dispatch('admin-buyers', $this->buyersChartPayload());
    }

    private function periodStart(): CarbonInterface
    {
        return match ($this->period) {
            'today' => now()->startOfDay(),
            '7d' => now()->subDays(6)->startOfDay(),
            default => now()->subDays(29)->startOfDay(),
        };
    }

    /** @return int number of daily buckets in the active period (incl. today). */
    private function periodDays(): int
    {
        return match ($this->period) {
            'today' => 1,
            '7d' => 7,
            default => 30,
        };
    }

    /** @return Collection<int, string> ISO dates oldest→today. */
    private function dayRange(): Collection
    {
        $days = $this->periodDays();

        return collect(range($days - 1, 0))
            ->map(fn (int $back) => now()->subDays($back)->toDateString())
            ->values();
    }

    /**
     * CHART 1 — daily paid GMV over the period (emerald = money). Grouped in PHP
     * so it stays portable across SQLite/MySQL; amounts are integer sen.
     *
     * @return array{type: string, series: array, labels: array, options: array}
     */
    private function gmvChartPayload(): array
    {
        $days = $this->dayRange();

        $byDay = Order::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->where('paid_at', '>=', $this->periodStart())
            ->get(['paid_at', 'grand_total_sen'])
            ->groupBy(fn (Order $order) => $order->paid_at->toDateString())
            ->map(fn ($orders) => (int) $orders->sum('grand_total_sen'));

        $series = $days->map(fn (string $day) => (int) ($byDay[$day] ?? 0))->values();

        return [
            'type' => 'area',
            'money' => true,
            'series' => [[
                'name' => __('GMV (paid)'),
                'data' => $series->map(fn (int $sen) => intdiv($sen, 100))->all(),
            ]],
            'labels' => $days->all(),
            'options' => [
                'chart' => ['height' => 280],
                'colors' => ['#047857'],
                'xaxis' => ['categories' => $days->all(), 'type' => 'category', 'tickAmount' => 6],
                'dataLabels' => ['enabled' => false],
            ],
        ];
    }

    /**
     * CHART 2 — orders by status donut. Counts ALL sub-orders (lifetime, not
     * period-scoped — the operational backlog). Center label = total.
     *
     * @return array{type: string, series: array, labels: array, options: array}
     */
    private function statusChartPayload(): array
    {
        $counts = SubOrder::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $labels = [];
        $series = [];
        $colors = [];

        foreach (SubOrderStatus::cases() as $status) {
            $count = (int) ($counts[$status->value] ?? 0);

            if ($count === 0) {
                continue; // keep the donut readable — skip empty slices
            }

            $labels[] = $status->label();
            $series[] = $count;
            $colors[] = self::STATUS_COLORS[$status->value] ?? '#5B615D';
        }

        $total = array_sum($series);

        return [
            'type' => 'donut',
            'series' => $series,
            'labels' => $labels,
            'options' => [
                'colors' => $colors,
                'legend' => ['position' => 'bottom'],
                'plotOptions' => [
                    'pie' => [
                        'donut' => [
                            'size' => '68%',
                            'labels' => [
                                'show' => true,
                                'total' => [
                                    'show' => true,
                                    'showAlways' => true,
                                    'label' => __('Total'),
                                    'value' => (string) $total,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * CHART 3 — top categories by completed-sub-order GMV. Joins
     * order_items → products → categories, sums line_total_sen of items whose
     * sub-order is completed, grouped by category. Horizontal bar, ink-soft.
     *
     * @return array{type: string, series: array, labels: array, options: array}
     */
    private function categoriesChartPayload(): array
    {
        $rows = DB::table('order_items')
            ->join('sub_orders', 'sub_orders.id', '=', 'order_items.sub_order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('sub_orders.status', SubOrderStatus::Completed->value)
            ->groupBy('categories.id')
            ->selectRaw('categories.id as category_id, sum(order_items.line_total_sen) as gmv_sen')
            ->orderByDesc('gmv_sen')
            ->limit(self::TOP_CATEGORIES)
            ->get();

        $categories = Category::query()
            ->whereIn('id', $rows->pluck('category_id'))
            ->get()
            ->keyBy('id');

        $labels = [];
        $data = [];

        foreach ($rows as $row) {
            $category = $categories->get($row->category_id);
            $labels[] = $category?->getTranslation('name', app()->getLocale(), false) ?: __('Uncategorised');
            $data[] = intdiv((int) $row->gmv_sen, 100);
        }

        return [
            'type' => 'bar',
            'money' => true,
            'series' => [[
                'name' => __('Completed GMV'),
                'data' => $data,
            ]],
            'labels' => $labels,
            'options' => [
                'colors' => ['#5B615D'],
                'plotOptions' => ['bar' => ['horizontal' => true, 'borderRadius' => 4, 'barHeight' => '62%']],
                'xaxis' => ['categories' => $labels],
                'dataLabels' => ['enabled' => false],
                'grid' => ['xaxis' => ['lines' => ['show' => true]], 'yaxis' => ['lines' => ['show' => false]]],
            ],
        ];
    }

    /**
     * CHART 4 — new users per day over the period (line). Simplest honest
     * signal: registrations bucketed by created_at day.
     *
     * @return array{type: string, series: array, labels: array, options: array}
     */
    private function buyersChartPayload(): array
    {
        $days = $this->dayRange();

        $byDay = User::query()
            ->where('created_at', '>=', $this->periodStart())
            ->get(['created_at'])
            ->groupBy(fn (User $user) => $user->created_at->toDateString())
            ->map(fn ($users) => $users->count());

        $series = $days->map(fn (string $day) => (int) ($byDay[$day] ?? 0))->values();

        return [
            'type' => 'line',
            'series' => [[
                'name' => __('New buyers'),
                'data' => $series->all(),
            ]],
            'labels' => $days->all(),
            'options' => [
                'colors' => ['#5B615D'],
                'xaxis' => ['categories' => $days->all(), 'type' => 'category', 'tickAmount' => 6],
                'yaxis' => ['min' => 0, 'forceNiceScale' => true],
                'markers' => ['size' => 0, 'hover' => ['size' => 5]],
            ],
        ];
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
}
