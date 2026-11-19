<?php

namespace App\Livewire\Admin;

use App\Enums\GroupBuyTeamStatus;
use App\Enums\LiveSessionStatus;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Enums\SubOrderStatus;
use App\Enums\SubscriptionStatus;
use App\Models\AffiliateReferral;
use App\Models\Category;
use App\Models\CoinWallet;
use App\Models\GroupBuyTeam;
use App\Models\LiveSession;
use App\Models\Order;
use App\Models\Payout;
use App\Models\Product;
use App\Models\ProductBoost;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\Subscription;
use App\Models\User;
use App\Support\DisplayTime;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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

    /** Seconds the all-time dashboard blocks are reused. */
    public const ALL_TIME_TTL = 180;

    /** Status → donut colour (docs/03 §6 semantic palette). */
    private const STATUS_COLORS = [
        'pending_payment' => '#B45309',
        'confirmed' => '#1A1714',
        'processing' => '#1A1714',
        'shipped' => '#7C6F5A',
        'delivered' => '#7C6F5A',
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

        // Platform takings follow finance.manage. The dashboard ROUTE stays open
        // to every admin — it is their landing page — so this is gated here at
        // the data layer rather than only in the blade.
        //
        // Gating it in the view alone is not enough: the chart payload becomes
        // part of this component's Livewire snapshot, which is serialised into
        // the page whether or not the markup that consumes it renders. Hiding a
        // chart does not stop its numbers reaching the browser.
        $canSeeFinance = $this->canSeeFinance();
        $user = auth()->user();

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
        $commissionTotals = $commissionQuery->clone()->toBase()
            ->selectRaw('count(*) as n, coalesce(sum(commission_sen), 0) as gross_sen')
            ->first();
        $commissionKnown = (int) $commissionTotals->n > 0;
        // HB-G136: commission_sen is the GROSS snapshot (later refunds scale
        // from it), so take off each refunded share the way LedgerService and
        // RefundService book it: round(commission x refunded / total), half up.
        // Only partly refunded rows need PHP; the rest sums in SQL.
        $commissionSen = (int) $commissionTotals->gross_sen
            - (int) $commissionQuery->clone()
                ->where('refunded_sen', '>', 0)
                ->where('total_sen', '>', 0)
                ->toBase()
                ->get(['commission_sen', 'refunded_sen', 'total_sen'])
                ->sum(fn ($row) => intdiv((int) $row->commission_sen * min((int) $row->refunded_sen, (int) $row->total_sen) + intdiv((int) $row->total_sen, 2), (int) $row->total_sen));

        // Boost revenue — Σ ProductBoost.amount_sen charged in the period.
        $boostRevenueSen = (int) ProductBoost::query()
            ->where('created_at', '>=', $start)
            ->sum('amount_sen');

        // Volume tiles are platform-wide numbers too, so they follow the
        // permission that owns their section (same rule as queues() below).
        $ordersToday = ($user?->can('orders.manage') ?? false)
            ? Order::query()->where('placed_at', '>=', DisplayTime::dayStart())->count()
            : 0;
        $newBuyersToday = ($user?->can('sellers.manage') ?? false)
            ? User::query()->where('created_at', '>=', DisplayTime::dayStart())->count()
            : 0;

        // Ranking is all-time (cached as plain rows); only the 5 store names are read live.
        $topRows = $canSeeFinance ? $this->allTime('top-stores', fn () => SubOrder::query()
            ->where('status', SubOrderStatus::Completed)
            ->toBase()
            ->selectRaw('store_id, sum(total_sen) as gmv_sen, count(*) as completed_count')
            ->groupBy('store_id')
            ->orderByDesc('gmv_sen')
            ->limit(self::TOP_STORES)
            ->get()
            ->map(fn ($row) => ['store_id' => (int) $row->store_id, 'gmv_sen' => (int) $row->gmv_sen, 'completed_count' => (int) $row->completed_count])
            ->all()) : [];
        $stores = Store::query()->whereIn('id', array_column($topRows, 'store_id'))->get()->keyBy('id');
        $topStores = collect($topRows)->map(fn (array $row) => (object) ($row + ['store' => $stores->get($row['store_id'])]));

        // Take-rate = commission revenue as a share of completed GMV (M1.5).
        // HB-G136: both net of refunds, so a partial refund does not read low.
        // RefundService caps refunded_sen at total_sen, so the difference is >= 0.
        $completedGmvSen = (int) SubOrder::query()
            ->where('status', SubOrderStatus::Completed)
            ->where('completed_at', '>=', $start)
            ->sum(DB::raw('total_sen - refunded_sen'));
        $takeRateBp = $completedGmvSen > 0 ? intdiv($commissionSen * 10000, $completedGmvSen) : 0;

        return view('livewire.admin.dashboard', [
            'canSeeFinance' => $canSeeFinance,
            'gmvSen' => $canSeeFinance ? $gmvSen : 0,
            'commissionKnown' => $canSeeFinance && $commissionKnown,
            'commissionSen' => $canSeeFinance ? $commissionSen : 0,
            'takeRateBp' => $canSeeFinance ? $takeRateBp : 0,
            'boostRevenueSen' => $canSeeFinance ? $boostRevenueSen : 0,
            'ordersToday' => $ordersToday,
            'newBuyersToday' => $newBuyersToday,
            'queues' => $this->queues(),
            'gmvChart' => $canSeeFinance ? $this->gmvChartPayload() : ['series' => [], 'categories' => []],
            'statusChart' => $this->statusChartPayload(),
            // Per-category completed GMV is platform takings sliced another way.
            'categoriesChart' => $canSeeFinance
                ? $this->categoriesChartPayload()
                : ['type' => 'bar', 'series' => [], 'labels' => []],
            'buyersChart' => $this->buyersChartPayload(),
            'topStores' => $topStores,
            'm2' => $this->m2Metrics(),
        ])->title(__('Dashboard'));
    }

    /** Platform takings follow finance.manage — see render(). */
    private function canSeeFinance(): bool
    {
        return auth()->user()?->can('finance.manage') ?? false;
    }

    /**
     * Engagement KPIs for the M2 features (coins, subscriptions, affiliate,
     * live, group-buy). Lifetime snapshots — cheap aggregate counts.
     *
     * Each number carries the permission that owns its section, exactly like
     * queues(): these are platform-wide totals (coin liability, subscription
     * and referral volume), not the viewer's own data, so they are gated here
     * at the data layer and the count only runs for an admin who may see it.
     *
     * @return array<string, int>
     */
    private function m2Metrics(): array
    {
        $user = auth()->user();
        // Lifetime snapshots: each count is reused for a few minutes.
        $gated = fn (string $name, string $permission, callable $count): int => ($user?->can($permission) ?? false)
            ? (int) $this->allTime('m2.'.$name, $count)
            : 0;

        return [
            'coin_circulation' => $gated('coin_circulation', 'finance.manage', fn () => (int) CoinWallet::sum('balance')),
            'active_subscriptions' => $gated('active_subscriptions', 'orders.manage', fn () => Subscription::where('status', SubscriptionStatus::Active)->count()),
            'affiliate_referrals' => $gated('affiliate_referrals', 'finance.manage', fn () => AffiliateReferral::count()),
            'live_now' => $gated('live_now', 'cms.manage', fn () => LiveSession::where('status', LiveSessionStatus::Live)->count()),
            'group_unlock_rate_pct' => $gated('group_unlock_rate_pct', 'orders.manage', function (): int {
                $totalTeams = GroupBuyTeam::count();

                return $totalTeams > 0
                    ? intdiv(GroupBuyTeam::where('status', GroupBuyTeamStatus::Unlocked)->count() * 100, $totalTeams)
                    : 0;
            }),
        ];
    }

    /**
     * Chart payloads built once per request (dispatch + render share them),
     * keyed by period so a period change cannot serve the old one.
     *
     * @var array<string, array>
     */
    private array $payloads = [];

    private function memo(string $chart, callable $build): array
    {
        return $this->payloads[$chart.$this->period] ??= $build();
    }

    /**
     * All-time blocks ignore the period, so a period click must not recompute
     * them: kept a few minutes as plain arrays/scalars (never models).
     */
    private function allTime(string $key, callable $build): mixed
    {
        return Cache::remember('admin.dashboard.'.$key.'.'.app()->getLocale(), self::ALL_TIME_TTL, $build);
    }

    /**
     * Push one fresh payload per chart (single-payload shape the foundation's
     * $wire.on handler expects — one event name per chart). Called on mount and
     * after every period change.
     */
    private function dispatchCharts(): void
    {
        // The GMV and top-category series are platform takings, so they follow
        // finance.manage here too. This is a SEPARATE delivery channel from the
        // rendered chart —
        // dispatch() puts the payload on the wire whether or not the markup
        // that would consume it exists, so gating the blade alone would still
        // hand the numbers to anyone reading the response.
        if ($this->canSeeFinance()) {
            $this->dispatch('admin-gmv', $this->gmvChartPayload());
            $this->dispatch('admin-categories', $this->categoriesChartPayload());
        }

        $this->dispatch('admin-status', $this->statusChartPayload());
        $this->dispatch('admin-buyers', $this->buyersChartPayload());
    }

    private function periodStart(): CarbonInterface
    {
        return match ($this->period) {
            'today' => DisplayTime::dayStart(),
            '7d' => DisplayTime::dayStart(DisplayTime::now()->subDays(6)),
            default => DisplayTime::dayStart(DisplayTime::now()->subDays(29)),
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
            ->map(fn (int $back) => DisplayTime::now()->subDays($back)->toDateString())
            ->values();
    }

    /**
     * CHART 1 — daily paid GMV over the period (emerald = money). Grouped per display-zone day in SQL;
     * amounts are integer sen.
     *
     * @return array{type: string, series: array, labels: array, options: array}
     */
    private function gmvChartPayload(): array
    {
        return $this->memo('gmv', fn () => $this->buildGmvChartPayload());
    }

    private function buildGmvChartPayload(): array
    {
        $days = $this->dayRange();

        $byDay = Order::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->where('paid_at', '>=', $this->periodStart())
            ->toBase()
            ->selectRaw(DisplayTime::dayBucketSql('paid_at').' as day, SUM(grand_total_sen) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->map(fn ($total) => (int) $total);

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
        return $this->allTime('status', fn () => $this->buildStatusChartPayload());
    }

    private function buildStatusChartPayload(): array
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
            $colors[] = self::STATUS_COLORS[$status->value] ?? '#5C544B';
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
        return $this->allTime('categories', fn () => $this->buildCategoriesChartPayload());
    }

    private function buildCategoriesChartPayload(): array
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
                'colors' => ['#5C544B'],
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
        return $this->memo('buyers', fn () => $this->buildBuyersChartPayload());
    }

    private function buildBuyersChartPayload(): array
    {
        $days = $this->dayRange();

        $byDay = User::query()
            ->where('created_at', '>=', $this->periodStart())
            ->toBase()
            ->selectRaw(DisplayTime::dayBucketSql('created_at').' as day, count(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->map(fn ($total) => (int) $total);

        $series = $days->map(fn (string $day) => (int) ($byDay[$day] ?? 0))->values();

        return [
            'type' => 'line',
            'series' => [[
                'name' => __('New buyers'),
                'data' => $series->all(),
            ]],
            'labels' => $days->all(),
            'options' => [
                'chart' => ['height' => 240],
                // Small y-axis labels leave no gutter, so the first rotated date was clipped.
                'grid' => ['padding' => ['left' => 28]],
                'colors' => ['#5C544B'],
                'xaxis' => ['categories' => $days->all(), 'type' => 'category', 'tickAmount' => 6],
                'yaxis' => ['min' => 0, 'forceNiceScale' => true],
                'markers' => ['size' => 0, 'hover' => ['size' => 5]],
            ],
        ];
    }

    /**
     * Pending-work tiles. Each carries the permission that owns its section, so
     * an admin is never shown a count for — or a link into — a section they
     * would be 403'd out of. The tile is built only when they may see it, so
     * the underlying count query does not run either.
     *
     * @return list<array{label: string, count: int, url: ?string}>
     */
    private function queues(): array
    {
        $user = auth()->user();

        $candidates = [
            ['permission' => 'sellers.manage', 'build' => fn () => [
                'label' => __('Seller applications'),
                'count' => Store::query()->where('status', StoreStatus::Pending)->count(),
                'url' => Route::has('admin.sellers.applications') ? route('admin.sellers.applications') : null,
            ]],
            ['permission' => 'products.moderate', 'build' => fn () => [
                'label' => __('Products pending review'),
                'count' => Product::query()->where('status', ProductStatus::PendingReview)->count(),
                'url' => Route::has('admin.catalog.moderation') ? route('admin.catalog.moderation') : null,
            ]],
            ['permission' => 'finance.manage', 'build' => fn () => [
                'label' => __('Payout requests'),
                'count' => Payout::query()->where('status', PayoutStatus::Requested)->count(),
                'url' => Route::has('admin.finance.payouts') ? route('admin.finance.payouts') : null,
            ]],
            ['permission' => 'orders.manage', 'build' => fn () => [
                // Returns engine arrives with M8 — placeholder card.
                'label' => __('Return escalations'),
                'count' => 0,
                'url' => null,
            ]],
        ];

        return array_values(array_map(
            fn (array $c) => ($c['build'])(),
            array_filter($candidates, fn (array $c) => $user?->can($c['permission']) ?? false),
        ));
    }
}
