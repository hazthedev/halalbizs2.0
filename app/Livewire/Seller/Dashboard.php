<?php

namespace App\Livewire\Seller;

use App\Enums\LedgerEntryType;
use App\Enums\ProductStatus;
use App\Enums\SubOrderStatus;
use App\Livewire\Concerns\CurrentStore;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreLedgerEntry;
use App\Models\SubOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Seller dashboard (docs/07 §A2) — count-up stat cards, to-do strip, recent
 * orders, an earnings strip, and three reactive ApexCharts (revenue line,
 * status donut, top-products bar) driven by the <x-ui.chart> foundation.
 *
 * Charts react to a period selector (7d/14d/30d/90d). On mount and whenever
 * the period changes we recompute and dispatch one event PER chart
 * ('seller-revenue', 'seller-status', 'seller-top') — the foundation's
 * hbChart $wire.on handler consumes a single payload per event, so each chart
 * owns its own refresh event rather than sharing one multi-payload event.
 *
 * "Real revenue" = sub_orders whose status is confirmed / processing /
 * shipped / delivered / completed (i.e. paid-and-progressing), bucketed by
 * created_at. pending_payment is excluded (not yet money) and the
 * cancelled / return_requested / returned / refunded family is excluded
 * (cancellations and reversals are not earned revenue).
 */
#[Layout('layouts.seller')]
class Dashboard extends Component
{
    use CurrentStore;

    public const LOW_STOCK_THRESHOLD = 5;

    /** Statuses that count as realised revenue (paid + progressing). */
    private const REVENUE_STATUSES = [
        SubOrderStatus::Confirmed,
        SubOrderStatus::Processing,
        SubOrderStatus::Shipped,
        SubOrderStatus::Delivered,
        SubOrderStatus::Completed,
    ];

    /** Period option => number of daily buckets. */
    private const PERIODS = [
        '7d' => 7,
        '14d' => 14,
        '30d' => 30,
        '90d' => 90,
    ];

    #[Url(except: '30d')]
    public string $period = '30d';

    // Count-up stat cards (not period-bound — kept as-is).
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
            ->lowStock() // respects each variant's low_stock_threshold (default 5)
            ->whereHas('product', fn ($query) => $query
                ->where('store_id', $store->id)
                ->where('status', ProductStatus::Live))
            ->count();

        $this->dispatchCharts();
    }

    public function updatedPeriod(): void
    {
        // Keep the URL/option sane if a bad value sneaks in.
        if (! array_key_exists($this->period, self::PERIODS)) {
            $this->period = '30d';
        }

        $this->dispatchCharts();
    }

    /** Number of daily buckets for the active period. */
    private function periodDays(): int
    {
        return self::PERIODS[$this->period] ?? self::PERIODS['30d'];
    }

    /** Inclusive start of the active period (midnight, `days - 1` ago). */
    private function periodStart(): Carbon
    {
        return now()->subDays($this->periodDays() - 1)->startOfDay();
    }

    /**
     * Push a fresh payload to each chart. One dispatch per chart because the
     * foundation's hbChart handler reads a single payload from the event.
     */
    private function dispatchCharts(): void
    {
        $this->dispatch('seller-revenue', ...$this->revenuePayload());
        $this->dispatch('seller-status', ...$this->statusPayload());
        $this->dispatch('seller-top', ...$this->topProductsPayload());
    }

    /**
     * CHART 1 — revenue over time. Daily Σ total_sen for revenue-status
     * sub-orders of THIS store, bucketed by created_at across the period.
     * Money stays integer sen end to end; ApexCharts receives plain ints.
     *
     * @return array{type: string, series: array<int, mixed>, labels: array<int, string>, options: array<string, mixed>}
     */
    private function revenuePayload(): array
    {
        $store = $this->currentStore();
        $days = $this->dayKeys();

        $byDay = $store->subOrders()
            ->whereIn('status', self::REVENUE_STATUSES)
            ->where('created_at', '>=', $this->periodStart())
            ->get(['created_at', 'total_sen'])
            ->groupBy(fn (SubOrder $subOrder) => $subOrder->created_at->toDateString())
            ->map(fn (Collection $rows) => (int) $rows->sum('total_sen'));

        $data = $days->map(fn (string $day) => (int) ($byDay[$day] ?? 0))->values()->all();

        return [
            'type' => 'area',
            'money' => true,
            'series' => [[
                'name' => __('Revenue (RM)'),
                'data' => array_map(fn (int $sen) => intdiv($sen, 100), $data), // whole-ringgit axis; source is integer sen
            ]],
            'labels' => $this->dayLabels()->all(),
            'options' => [
                'colors' => ['#047857'], // emerald — money series
                'xaxis' => ['categories' => $this->dayLabels()->all()],
            ],
        ];
    }

    /**
     * CHART 2 — orders by status donut. Counts THIS store's sub-orders by
     * status across the period, coloured by the shared status palette.
     *
     * @return array{type: string, series: array<int, int>, labels: array<int, string>, options: array<string, mixed>}
     */
    private function statusPayload(): array
    {
        $store = $this->currentStore();

        $counts = $store->subOrders()
            ->where('created_at', '>=', $this->periodStart())
            ->get(['status'])
            ->groupBy(fn (SubOrder $subOrder) => $subOrder->status->value)
            ->map(fn (Collection $rows) => $rows->count());

        // Only show statuses that actually occur, in enum order.
        $present = collect(SubOrderStatus::cases())
            ->filter(fn (SubOrderStatus $status) => ($counts[$status->value] ?? 0) > 0)
            ->values();

        $series = $present->map(fn (SubOrderStatus $status) => (int) $counts[$status->value])->all();
        $labels = $present->map(fn (SubOrderStatus $status) => $status->label())->all();
        $colors = $present->map(fn (SubOrderStatus $status) => $this->statusColor($status))->all();

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
                            'labels' => [
                                'show' => true,
                                'total' => [
                                    'show' => true,
                                    'label' => __('Orders'),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * CHART 3 — top products bar. Top 5 of THIS store's products ranked by
     * units sold in the period (Σ order_items.qty for revenue-status
     * sub-orders), falling back to lifetime sold_count when no period sales.
     * Horizontal bar, ink-soft series.
     *
     * @return array{type: string, series: array<int, mixed>, options: array<string, mixed>}
     */
    private function topProductsPayload(): array
    {
        $store = $this->currentStore();

        $soldInPeriod = OrderItem::query()
            ->whereHas('subOrder', fn ($query) => $query
                ->where('store_id', $store->id)
                ->whereIn('status', self::REVENUE_STATUSES)
                ->where('created_at', '>=', $this->periodStart()))
            ->selectRaw('product_id, SUM(qty) as units')
            ->groupBy('product_id')
            ->orderByDesc('units')
            ->limit(5)
            ->get();

        if ($soldInPeriod->isNotEmpty()) {
            $names = Product::query()
                ->whereIn('id', $soldInPeriod->pluck('product_id'))
                ->get(['id', 'name'])
                ->keyBy('id');

            $rows = $soldInPeriod->map(fn ($row) => [
                'name' => (string) ($names[$row->product_id]?->getTranslation('name', app()->getLocale(), false)
                    ?: $names[$row->product_id]?->getTranslation('name', 'en')
                    ?: __('Product')),
                'units' => (int) $row->units,
            ]);
        } else {
            // No period sales yet — show the store's best lifetime sellers so
            // the chart is meaningful rather than empty.
            $rows = $store->products()
                ->orderByDesc('sold_count')
                ->limit(5)
                ->get(['id', 'name', 'sold_count'])
                ->map(fn (Product $product) => [
                    'name' => (string) ($product->getTranslation('name', app()->getLocale(), false)
                        ?: $product->getTranslation('name', 'en')
                        ?: __('Product')),
                    'units' => (int) $product->sold_count,
                ]);
        }

        return [
            'type' => 'bar',
            'series' => [[
                'name' => __('Units sold'),
                'data' => $rows->pluck('units')->map(fn ($units) => (int) $units)->all(),
            ]],
            'options' => [
                'colors' => ['#5C544B'], // ink-soft
                'plotOptions' => ['bar' => ['horizontal' => true, 'borderRadius' => 4]],
                'xaxis' => ['categories' => $rows->pluck('name')->all()],
            ],
        ];
    }

    /** ISO day-string keys (oldest → newest) spanning the period. */
    private function dayKeys(): Collection
    {
        return collect(range($this->periodDays() - 1, 0))
            ->map(fn (int $back) => now()->subDays($back)->toDateString());
    }

    /** Human day labels (e.g. "5 Jun") matching dayKeys order. */
    private function dayLabels(): Collection
    {
        return collect(range($this->periodDays() - 1, 0))
            ->map(fn (int $back) => now()->subDays($back)->format('j M'));
    }

    private function statusColor(SubOrderStatus $status): string
    {
        return match ($status) {
            SubOrderStatus::PendingPayment => '#B45309',
            SubOrderStatus::Confirmed, SubOrderStatus::Processing => '#1A1714',
            SubOrderStatus::Shipped, SubOrderStatus::Delivered => '#7C6F5A',
            SubOrderStatus::Completed => '#047857',
            SubOrderStatus::Cancelled,
            SubOrderStatus::ReturnRequested,
            SubOrderStatus::Returned,
            SubOrderStatus::Refunded => '#BE123C',
        };
    }

    /**
     * Earnings strip figures (all integer sen):
     *  - available: SUM available ledger entries (Store::availableBalanceSen)
     *  - gross: Σ revenue-status sub-order totals this period
     *  - commission: Σ commission debits (negative) this period, shown absolute
     *
     * @return array{available: int, gross: int, commission: int}
     */
    private function earnings(): array
    {
        $store = $this->currentStore();

        $gross = (int) $store->subOrders()
            ->whereIn('status', self::REVENUE_STATUSES)
            ->where('created_at', '>=', $this->periodStart())
            ->sum('total_sen');

        $commission = (int) StoreLedgerEntry::query()
            ->where('store_id', $store->id)
            ->where('type', LedgerEntryType::Commission)
            ->where('created_at', '>=', $this->periodStart())
            ->sum('amount_sen');

        return [
            'available' => $store->availableBalanceSen(),
            'gross' => $gross,
            'commission' => abs($commission), // entries are signed (negative) — display the charge
        ];
    }

    public function render()
    {
        $store = $this->currentStore();

        $recentOrders = $store->subOrders()
            ->latest()
            ->limit(5)
            ->get();

        return view('livewire.seller.dashboard', [
            'recentOrders' => $recentOrders,
            'revenuePayload' => $this->revenuePayload(),
            'statusPayload' => $this->statusPayload(),
            'topProductsPayload' => $this->topProductsPayload(),
            'earnings' => $this->earnings(),
            'periods' => array_keys(self::PERIODS),
        ])->title(__('Dashboard'));
    }

    /**
     * Test-facing helper: the revenue dataset (whole-ringgit ints) for the
     * active period. Lets the suite assert the series is non-zero without
     * re-deriving the bucketing logic.
     *
     * @return array<int, int>
     */
    public function revenueData(): array
    {
        return $this->revenuePayload()['series'][0]['data'];
    }

    /**
     * Test-facing helper: status => count map for the active period donut.
     *
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $payload = $this->statusPayload();

        return array_combine($payload['labels'], $payload['series']);
    }

    /**
     * Test-facing helper: ordered product names in the top-products bar.
     *
     * @return array<int, string>
     */
    public function topProductNames(): array
    {
        return $this->topProductsPayload()['options']['xaxis']['categories'];
    }
}
