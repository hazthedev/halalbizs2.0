<div class="space-y-4">

    {{-- Header + period picker --}}
    <div class="flex flex-wrap items-center gap-3">
        <h1 class="font-display text-[22px] font-bold leading-tight">{{ __('Dashboard') }}</h1>

        <div class="ml-auto inline-flex rounded-lg border border-line bg-surface p-0.5" role="group" aria-label="{{ __('Stats period') }}">
            @foreach ($this->periods() as $key => $label)
                <button type="button"
                        wire:click="setPeriod('{{ $key }}')"
                        wire:key="period-{{ $key }}"
                        aria-pressed="{{ $period === $key ? 'true' : 'false' }}"
                        class="min-h-10 rounded-md px-3 text-[13px] font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $period === $key ? 'bg-ink text-paper' : 'text-ink-soft hover:text-ink' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Stat row --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
        <x-ui.card class="p-4">
            <p class="text-[13px] font-medium text-ink-soft">{{ __('GMV (paid)') }}</p>
            <p class="mt-1 font-display text-[28px] font-bold leading-tight tabular-nums">@money($gmvSen)</p>
            <p class="mt-0.5 text-[12px] text-ink-faint">{{ $this->periods()[$period] }}</p>
        </x-ui.card>
        <x-ui.card class="p-4">
            <p class="text-[13px] font-medium text-ink-soft">{{ __('Commission revenue') }}</p>
            <p class="mt-1 font-display text-[28px] font-bold leading-tight tabular-nums">
                @if ($commissionKnown) @money($commissionSen) @else — @endif
            </p>
            <p class="mt-0.5 text-[12px] text-ink-faint">{{ __('Completed sub-orders') }}</p>
        </x-ui.card>
        <x-ui.card class="p-4">
            <p class="text-[13px] font-medium text-ink-soft">{{ __('Boost revenue') }}</p>
            <p class="mt-1 font-display text-[28px] font-bold leading-tight tabular-nums">@money($boostRevenueSen)</p>
            <p class="mt-0.5 text-[12px] text-ink-faint">{{ __('Paid placements') }}</p>
        </x-ui.card>
        <x-ui.card class="p-4">
            <p class="text-[13px] font-medium text-ink-soft">{{ __('Orders today') }}</p>
            <p class="mt-1 font-display text-[28px] font-bold leading-tight tabular-nums">{{ number_format($ordersToday) }}</p>
            <p class="mt-0.5 text-[12px] text-ink-faint">{{ __('Placed since midnight') }}</p>
        </x-ui.card>
        <x-ui.card class="p-4">
            <p class="text-[13px] font-medium text-ink-soft">{{ __('New buyers today') }}</p>
            <p class="mt-1 font-display text-[28px] font-bold leading-tight tabular-nums">{{ number_format($newBuyersToday) }}</p>
            <p class="mt-0.5 text-[12px] text-ink-faint">{{ __('Registrations since midnight') }}</p>
        </x-ui.card>
    </div>

    {{-- Pending queues --}}
    <div>
        <h2 class="mb-2 text-sm font-semibold">{{ __('Pending queues') }}</h2>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ($queues as $queue)
                @if ($queue['url'] !== null)
                    <a href="{{ $queue['url'] }}" wire:navigate wire:key="queue-{{ $loop->index }}"
                       class="group rounded-[10px] border border-line bg-surface p-4 hover:border-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                        <p class="flex items-center justify-between gap-2 text-[13px] font-medium text-ink-soft">
                            {{ $queue['label'] }}
                            <svg class="size-3.5 text-ink-faint group-hover:text-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                        </p>
                        <p class="mt-1 font-display text-[28px] font-bold leading-tight tabular-nums {{ $queue['count'] > 0 ? 'text-warn' : 'text-ink' }}">{{ number_format($queue['count']) }}</p>
                    </a>
                @else
                    <x-ui.card class="p-4" wire:key="queue-{{ $loop->index }}">
                        <p class="text-[13px] font-medium text-ink-soft">{{ $queue['label'] }}</p>
                        <p class="mt-1 font-display text-[28px] font-bold leading-tight tabular-nums text-ink-faint">{{ number_format($queue['count']) }}</p>
                    </x-ui.card>
                @endif
            @endforeach
        </div>
    </div>

    {{-- GMV trend (interactive area) — emerald = money --}}
    <x-ui.card class="p-4">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-sm font-semibold">{{ __('GMV — last 30 days') }}</h2>
            <p class="text-[13px] text-ink-soft">{{ __('Paid orders, daily') }}</p>
        </div>

        <div class="mt-3">
            <x-ui.chart id="admin-gmv" :payload="$gmvChart" refresh-event="admin-gmv"
                        :height="280" aria-label="{{ __('Daily paid GMV over the selected period') }}" />
        </div>
    </x-ui.card>

    {{-- New buyers over time (interactive line) --}}
    <x-ui.card class="p-4">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-sm font-semibold">{{ __('New buyers over time') }}</h2>
            <p class="text-[13px] text-ink-soft">{{ __('Registrations, daily') }}</p>
        </div>

        <div class="mt-3">
            <x-ui.chart id="admin-buyers" :payload="$buyersChart" refresh-event="admin-buyers"
                        :height="240" aria-label="{{ __('Daily new buyer registrations over the selected period') }}" />
        </div>
    </x-ui.card>

    <div class="grid gap-4 lg:grid-cols-2">

        {{-- Orders by status (donut) --}}
        <x-ui.card class="p-4">
            <h2 class="text-sm font-semibold">{{ __('Orders by status') }}</h2>

            @if ($statusChart['series'] === [])
                <div class="flex items-center justify-center py-12 text-center" style="min-height: 280px">
                    <div>
                        <p class="font-display text-lg font-semibold">{{ __('No orders yet') }}</p>
                        <p class="mt-1 text-sm text-ink-soft">{{ __('Sub-orders appear here as buyers check out.') }}</p>
                    </div>
                </div>
            @else
                <div class="mt-3">
                    <x-ui.chart id="admin-status" :payload="$statusChart" refresh-event="admin-status"
                                :height="300" aria-label="{{ __('Sub-order counts per status') }}" />
                </div>
            @endif
        </x-ui.card>

        {{-- Top categories by completed GMV (horizontal bar) --}}
        <x-ui.card class="p-4">
            <h2 class="text-sm font-semibold">{{ __('Top categories by GMV') }}</h2>

            @if ($categoriesChart['labels'] === [])
                <div class="flex items-center justify-center py-12 text-center" style="min-height: 280px">
                    <div>
                        <p class="font-display text-lg font-semibold">{{ __('No completed orders yet') }}</p>
                        <p class="mt-1 text-sm text-ink-soft">{{ __('Categories rank here once their orders complete.') }}</p>
                    </div>
                </div>
            @else
                <div class="mt-3">
                    <x-ui.chart id="admin-categories" :payload="$categoriesChart" refresh-event="admin-categories"
                                :height="300" aria-label="{{ __('Top 5 categories by completed GMV') }}" />
                </div>
            @endif
        </x-ui.card>
    </div>

    {{-- Top stores by completed GMV --}}
    <x-ui.card>
        <div class="border-b border-line px-4 py-3">
            <h2 class="text-sm font-semibold">{{ __('Top stores by GMV') }}</h2>
        </div>

        @if ($topStores->isEmpty())
            <div class="px-4 py-10 text-center">
                <p class="font-display text-lg font-semibold">{{ __('No completed orders yet') }}</p>
                <p class="mt-1 text-sm text-ink-soft">{{ __('Stores rank here once their orders complete.') }}</p>
            </div>
        @else
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Store') }}</th>
                        <th scope="col" class="px-4 py-2.5 text-right font-medium">{{ __('Completed') }}</th>
                        <th scope="col" class="px-4 py-2.5 text-right font-medium">{{ __('GMV') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($topStores as $row)
                        <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="top-store-{{ $row->store_id }}">
                            <td class="px-4 py-2">
                                @if ($row->store !== null)
                                    <a href="{{ route('admin.sellers.stores.show', $row->store) }}" wire:navigate
                                       class="inline-flex min-h-11 items-center font-medium text-ink underline-offset-2 hover:text-emerald hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                        <span class="line-clamp-1 max-w-56">{{ $row->store->name }}</span>
                                    </a>
                                @else
                                    <span class="text-ink-faint">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums text-ink-soft">{{ number_format((int) $row->completed_count) }}</td>
                            <td class="px-4 py-2 text-right font-mono font-semibold tabular-nums whitespace-nowrap">@money((int) $row->gmv_sen)</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>
</div>
