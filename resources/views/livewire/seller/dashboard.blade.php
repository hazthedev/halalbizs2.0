<div class="space-y-4">
    <x-ui.section-heading as="h1" :title="__('Dashboard')" />

    {{-- ===== Stat cards (count-up per design §7, reduced-motion renders final) ===== --}}
    @php
        $stats = [
            ['label' => __("Today's orders"), 'value' => $todayOrders],
            ['label' => __('To ship'), 'value' => $toShip],
            ['label' => __('Live products'), 'value' => $liveProducts],
            ['label' => __('Low stock'), 'value' => $lowStock, 'warn' => $lowStock > 0],
        ];
    @endphp
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ($stats as $stat)
            <x-ui.card class="p-4">
                <p class="text-[13px] font-medium text-ink-soft">{{ $stat['label'] }}</p>
                <p
                    class="mt-1 font-display text-[28px] font-bold leading-tight tabular-nums {{ ($stat['warn'] ?? false) ? 'text-warn' : 'text-ink' }}"
                    x-data="{ target: {{ $stat['value'] }}, display: 0 }"
                    x-init="
                        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || target === 0) {
                            display = target;
                        } else {
                            const start = performance.now();
                            const tick = (now) => {
                                const progress = Math.min((now - start) / 400, 1);
                                display = Math.round(target * progress);
                                if (progress < 1) requestAnimationFrame(tick);
                            };
                            requestAnimationFrame(tick);
                        }
                    "
                    x-text="display.toLocaleString()"
                >{{ number_format($stat['value']) }}</p>
            </x-ui.card>
        @endforeach
    </div>

    {{-- ===== To-do strip ===== --}}
    @if ($toShip > 0 || $lowStock > 0)
        <x-ui.card class="flex flex-wrap items-center gap-2 p-4">
            <p class="text-[13px] font-semibold text-ink">{{ __('To do') }}</p>

            @if ($toShip > 0)
                @if (Illuminate\Support\Facades\Route::has('seller.orders.index'))
                    <a href="{{ route('seller.orders.index') }}" wire:navigate
                       class="inline-flex min-h-8 items-center gap-1.5 rounded-full border border-line px-3 py-1 text-[13px] font-medium text-emerald hover:border-emerald">
                        {{ trans_choice(':count order waiting to ship|:count orders waiting to ship', $toShip, ['count' => $toShip]) }}
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                    </a>
                @else
                    <span class="inline-flex min-h-8 items-center rounded-full border border-line px-3 py-1 text-[13px] font-medium text-ink-soft">
                        {{ trans_choice(':count order waiting to ship|:count orders waiting to ship', $toShip, ['count' => $toShip]) }}
                    </span>
                @endif
            @endif

            @if ($lowStock > 0)
                <a href="{{ route('seller.products.index', ['low_stock' => 1]) }}" wire:navigate
                   class="inline-flex min-h-8 items-center gap-1.5 rounded-full border border-line px-3 py-1 text-[13px] font-medium text-emerald hover:border-emerald">
                    {{ trans_choice(':count product low on stock|:count products low on stock', $lowStock, ['count' => $lowStock]) }}
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </a>
            @endif
        </x-ui.card>
    @endif

    {{-- ===== Period selector + earnings strip ===== --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="inline-flex rounded-full border border-line p-1" role="group" aria-label="{{ __('Chart period') }}">
            @foreach ($periods as $option)
                <button
                    type="button"
                    wire:click="$set('period', '{{ $option }}')"
                    wire:key="period-{{ $option }}"
                    aria-pressed="{{ $period === $option ? 'true' : 'false' }}"
                    class="inline-flex min-h-8 items-center rounded-full px-3 text-[13px] font-medium tabular-nums focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-2 {{ $period === $option ? 'bg-ink text-surface' : 'text-ink-soft hover:text-ink' }}"
                >
                    {{ __(':n days', ['n' => (int) $option]) }}
                </button>
            @endforeach
        </div>

        <dl class="flex flex-wrap items-center gap-x-6 gap-y-1 text-[13px]">
            <div class="flex items-baseline gap-1.5">
                <dt class="text-ink-soft">{{ __('Available balance') }}</dt>
                <dd class="font-mono font-semibold tabular-nums text-emerald">@money($earnings['available'])</dd>
            </div>
            <div class="flex items-baseline gap-1.5">
                <dt class="text-ink-soft">{{ __('Gross (period)') }}</dt>
                <dd class="font-mono font-semibold tabular-nums text-ink">@money($earnings['gross'])</dd>
            </div>
            <div class="flex items-baseline gap-1.5">
                <dt class="text-ink-soft">{{ __('Commission (period)') }}</dt>
                <dd class="font-mono font-semibold tabular-nums text-ink-soft">@money($earnings['commission'])</dd>
            </div>
        </dl>
    </div>

    {{-- ===== Charts: revenue (emerald, money), status donut, top products ===== --}}
    <div class="grid gap-4 lg:grid-cols-3">
        {{-- CHART 1 — revenue over time (replaces the old zero sparkline). Own
             refresh event so the foundation's single-payload handler updates it. --}}
        <x-ui.card class="p-4 lg:col-span-2">
            <h2 class="text-sm font-semibold">{{ __('Revenue over time') }}</h2>
            <p class="mt-0.5 text-[13px] text-ink-soft">{{ __('Confirmed and progressing orders, by day (RM).') }}</p>
            <div class="mt-3">
                <x-ui.chart
                    id="seller-revenue"
                    :payload="$revenuePayload"
                    refresh-event="seller-revenue"
                    :height="260"
                    aria-label="{{ __('Revenue over time') }}"
                />
            </div>
        </x-ui.card>

        {{-- CHART 2 — orders by status donut --}}
        <x-ui.card class="p-4">
            <h2 class="text-sm font-semibold">{{ __('Orders by status') }}</h2>
            <div class="mt-3">
                <x-ui.chart
                    id="seller-status"
                    :payload="$statusPayload"
                    refresh-event="seller-status"
                    :height="260"
                    aria-label="{{ __('Orders by status') }}"
                />
            </div>
        </x-ui.card>
    </div>

    {{-- CHART 3 — top products bar --}}
    <x-ui.card class="p-4">
        <h2 class="text-sm font-semibold">{{ __('Top products') }}</h2>
        <p class="mt-0.5 text-[13px] text-ink-soft">{{ __('Best sellers this period by units sold.') }}</p>
        <div class="mt-3">
            <x-ui.chart
                id="seller-top"
                :payload="$topProductsPayload"
                refresh-event="seller-top"
                :height="240"
                aria-label="{{ __('Top products') }}"
            />
        </div>
    </x-ui.card>

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- ===== Recent orders ===== --}}
        <x-ui.card class="lg:col-span-3">
            <div class="border-b border-line px-4 py-3">
                <h2 class="text-sm font-semibold">{{ __('Recent orders') }}</h2>
            </div>

            @if ($recentOrders->isEmpty())
                <x-ui.empty-state :title="__('No orders yet')" :message="__('New orders appear here the moment a buyer pays.')" />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]">
                        <thead>
                            <tr class="border-b border-line text-left text-ink-soft">
                                <th class="px-4 py-2 font-medium">{{ __('Order no.') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('Status') }}</th>
                                <th class="px-4 py-2 text-right font-medium">{{ __('Total') }}</th>
                                <th class="px-4 py-2 text-right font-medium">{{ __('Placed') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentOrders as $subOrder)
                                @php
                                    $badgeVariant = match ($subOrder->status) {
                                        \App\Enums\SubOrderStatus::PendingPayment => 'warn',
                                        \App\Enums\SubOrderStatus::Completed => 'sale',
                                        \App\Enums\SubOrderStatus::Cancelled,
                                        \App\Enums\SubOrderStatus::ReturnRequested,
                                        \App\Enums\SubOrderStatus::Returned,
                                        \App\Enums\SubOrderStatus::Refunded => 'danger',
                                        default => 'neutral',
                                    };
                                @endphp
                                <tr class="border-b border-line last:border-0 hover:bg-paper">
                                    <td class="px-4 py-2.5 font-mono font-medium">{{ $subOrder->sub_order_no }}</td>
                                    <td class="px-4 py-2.5"><x-ui.badge :variant="$badgeVariant">{{ $subOrder->status->label() }}</x-ui.badge></td>
                                    <td class="px-4 py-2.5 text-right font-mono tabular-nums">@money($subOrder->total_sen)</td>
                                    <td class="px-4 py-2.5 text-right text-ink-soft">{{ $subOrder->created_at->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    </div>
</div>
