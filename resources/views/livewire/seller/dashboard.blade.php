<div class="space-y-4">
    <h1 class="font-display text-[22px] font-bold leading-tight">{{ __('Dashboard') }}</h1>

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

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- ===== Recent orders ===== --}}
        <x-ui.card class="lg:col-span-2">
            <div class="border-b border-line px-4 py-3">
                <h2 class="text-sm font-semibold">{{ __('Recent orders') }}</h2>
            </div>

            @if ($recentOrders->isEmpty())
                <div class="px-4 py-10 text-center">
                    <p class="font-display text-lg font-semibold">{{ __('No orders yet') }}</p>
                    <p class="mt-1 text-sm text-ink-soft">{{ __('New orders appear here the moment a buyer pays.') }}</p>
                </div>
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

        {{-- ===== 14-day sales sparkline (placeholder data until M4) ===== --}}
        <x-ui.card class="p-4">
            <h2 class="text-sm font-semibold">{{ __('Sales (14 days)') }}</h2>
            @php
                $maxSen = max(1, $sparkline->max('total_sen'));
                $points = $sparkline->values()->map(function ($day, $i) use ($maxSen) {
                    $x = round($i * (280 / 13), 1);
                    $y = round(58 - ($day['total_sen'] / $maxSen) * 50, 1);

                    return "$x,$y";
                })->implode(' ');
            @endphp
            <svg viewBox="0 0 280 64" class="mt-3 h-16 w-full" role="img" aria-label="{{ __('Sales (14 days)') }}">
                <line x1="0" y1="58" x2="280" y2="58" stroke="var(--color-line)" stroke-width="1" />
                <polyline points="{{ $points }}" fill="none" stroke="var(--color-emerald)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <div class="mt-2 flex justify-between font-mono text-[11px] text-ink-faint">
                <span>{{ $sparkline->first()['date']->format('j M') }}</span>
                <span>{{ $sparkline->last()['date']->format('j M') }}</span>
            </div>
            <p class="mt-2 text-[13px] text-ink-soft">{{ __('Sales tracking starts with your first order.') }}</p>
        </x-ui.card>
    </div>
</div>
