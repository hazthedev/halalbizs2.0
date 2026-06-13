<?php

namespace App\Livewire\Storefront\Account;

use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SubOrder;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Buyer overview (docs/05) — spend over time, orders by status, quick-status
 * shortcuts and recent activity. Money stays integer sen end to end; the spend
 * chart only divides to ringgit for presentation (CLAUDE.md hard rule 1).
 */
#[Layout('layouts.storefront')]
class Dashboard extends Component
{
    public const RECENT_LIMIT = 5;

    #[Url(except: '6m')]
    public string $period = '6m';

    public function mount(): void
    {
        if (! array_key_exists($this->period, $this->periods())) {
            $this->period = '6m';
        }
    }

    /** @return array<string, string> period key → label */
    public function periods(): array
    {
        return [
            '3m' => __('3 months'),
            '6m' => __('6 months'),
            '12m' => __('12 months'),
        ];
    }

    /**
     * Period change recomputes the spend series and pushes the fresh payload to
     * the live ApexChart (it's wire:ignore, so the Alpine driver owns updates).
     */
    public function updatedPeriod(): void
    {
        if (! array_key_exists($this->period, $this->periods())) {
            $this->period = '6m';
        }

        $this->dispatch('buyer-charts', $this->spendChart());
    }

    public function render()
    {
        return view('livewire.storefront.account.dashboard', [
            'totalSpentSen' => $this->totalSpentSen(),
            'ordersPlaced' => $this->ordersPlaced(),
            'itemsBought' => $this->itemsBought(),
            'reviewsWritten' => $this->reviewsWritten(),
            'wishlistSaved' => $this->wishlistSaved(),
            'spendChart' => $this->spendChart(),
            'statusChart' => $this->statusChart(),
            'quickStatus' => $this->quickStatus(),
            'recentOrders' => $this->recentOrders(),
            'hasOrders' => $this->ordersPlaced() > 0,
        ])->title(__('Overview'));
    }

    // ── Stat totals (all scoped to the authenticated buyer) ─────────────

    /** Σ grand_total of the buyer's PAID orders (their honest lifetime spend). */
    private function totalSpentSen(): int
    {
        return (int) Order::query()
            ->where('user_id', auth()->id())
            ->where('payment_status', PaymentStatus::Paid)
            ->sum('grand_total_sen');
    }

    private function ordersPlaced(): int
    {
        return Order::query()->where('user_id', auth()->id())->count();
    }

    /** Σ qty across every order item in the buyer's sub-orders. */
    private function itemsBought(): int
    {
        return (int) OrderItem::query()
            ->whereHas('subOrder.order', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->sum('qty');
    }

    private function reviewsWritten(): int
    {
        return (int) auth()->user()->reviews()->count();
    }

    private function wishlistSaved(): int
    {
        return (int) auth()->user()->wishlists()->count();
    }

    // ── Chart 1: spend over time ────────────────────────────────────────

    private function monthsInPeriod(): int
    {
        return match ($this->period) {
            '3m' => 3,
            '12m' => 12,
            default => 6,
        };
    }

    private function periodStart(): CarbonInterface
    {
        return now()->startOfMonth()->subMonths($this->monthsInPeriod() - 1);
    }

    /**
     * Monthly paid-spend buckets across the selected window. Sums stay integer
     * sen; we divide to ringgit ONLY for the chart series (presentation), as
     * ApexCharts y-values must be plain numbers.
     *
     * @return array{type: string, series: list<array{name: string, data: list<int|float>}>, labels: list<string>, options: array<string, mixed>}
     */
    private function spendChart(): array
    {
        $months = collect(range($this->monthsInPeriod() - 1, 0))
            ->map(fn (int $back) => now()->startOfMonth()->subMonths($back));

        $byMonth = Order::query()
            ->where('user_id', auth()->id())
            ->where('payment_status', PaymentStatus::Paid)
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $this->periodStart())
            ->get(['paid_at', 'grand_total_sen'])
            ->groupBy(fn (Order $order) => $order->paid_at->format('Y-m'))
            ->map(fn ($orders) => (int) $orders->sum('grand_total_sen'));

        $labels = $months->map(fn (Carbon $month) => $month->format('M Y'))->values()->all();

        // Integer sen per bucket; ringgit (sen/100) only as the display value.
        $data = $months
            ->map(fn (Carbon $month) => (int) ($byMonth[$month->format('Y-m')] ?? 0))
            ->map(fn (int $sen) => round($sen / 100, 2))
            ->values()
            ->all();

        return [
            'type' => 'area',
            'money' => true,
            'series' => [['name' => __('Spend (RM)'), 'data' => $data]],
            'labels' => $labels,
            'options' => [
                'colors' => ['#047857'], // emerald — it's money
                'xaxis' => ['categories' => $labels],
            ],
        ];
    }

    // ── Chart 2: orders by status (donut) ───────────────────────────────

    private function statusCounts(): Collection
    {
        return $this->subOrderQuery()
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
    }

    /**
     * Donut of the buyer's sub-orders grouped by status. Colours match the
     * shared status palette (window.hbStatusColor) so the legend reads the same
     * across dashboards. Only statuses the buyer actually has are charted.
     *
     * @return array{type: string, series: list<int>, labels: list<string>, options: array<string, mixed>, total: int}
     */
    private function statusChart(): array
    {
        $counts = $this->statusCounts();

        $series = [];
        $labels = [];
        $colors = [];

        foreach (SubOrderStatus::cases() as $status) {
            $count = (int) ($counts[$status->value] ?? 0);

            if ($count === 0) {
                continue;
            }

            $series[] = $count;
            $labels[] = $status->label();
            $colors[] = $this->statusColor($status);
        }

        return [
            'type' => 'donut',
            'series' => $series,
            'labels' => $labels,
            'total' => array_sum($series),
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

    /** Mirror of window.hbStatusColor (app.js) so server + client agree. */
    private function statusColor(SubOrderStatus $status): string
    {
        return match ($status) {
            SubOrderStatus::PendingPayment => '#B45309',
            SubOrderStatus::Confirmed, SubOrderStatus::Processing => '#191B1A',
            SubOrderStatus::Shipped, SubOrderStatus::Delivered => '#475569',
            SubOrderStatus::Completed => '#047857',
            SubOrderStatus::Cancelled, SubOrderStatus::ReturnRequested,
            SubOrderStatus::Returned, SubOrderStatus::Refunded => '#BE123C',
        };
    }

    // ── Quick-status shortcuts (mirror the Orders tab groupings) ────────

    /** @return list<array{key: string, label: string, count: int, url: string}> */
    private function quickStatus(): array
    {
        $counts = $this->statusCounts();

        $sum = fn (SubOrderStatus ...$statuses) => collect($statuses)
            ->sum(fn (SubOrderStatus $status) => (int) ($counts[$status->value] ?? 0));

        return [
            [
                'key' => 'to-pay',
                'label' => __('To Pay'),
                'count' => $sum(SubOrderStatus::PendingPayment),
                'url' => route('account.orders', ['tab' => 'to-pay']),
            ],
            [
                'key' => 'to-ship',
                'label' => __('To Ship'),
                'count' => $sum(SubOrderStatus::Confirmed, SubOrderStatus::Processing),
                'url' => route('account.orders', ['tab' => 'to-ship']),
            ],
            [
                'key' => 'to-receive',
                'label' => __('To Receive'),
                'count' => $sum(SubOrderStatus::Shipped, SubOrderStatus::Delivered),
                'url' => route('account.orders', ['tab' => 'to-receive']),
            ],
            [
                'key' => 'completed',
                'label' => __('Completed'),
                'count' => $sum(SubOrderStatus::Completed),
                'url' => route('account.orders', ['tab' => 'completed']),
            ],
        ];
    }

    // ── Recent activity ─────────────────────────────────────────────────

    private function recentOrders()
    {
        return $this->subOrderQuery()
            ->with(['store', 'order'])
            ->latest('id')
            ->limit(self::RECENT_LIMIT)
            ->get();
    }

    private function subOrderQuery(): Builder
    {
        return SubOrder::query()
            ->whereHas('order', fn (Builder $query) => $query->where('user_id', auth()->id()));
    }
}
