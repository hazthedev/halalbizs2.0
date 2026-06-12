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
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
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

    {{-- 30-day GMV line (pure SVG, no JS lib) --}}
    <x-ui.card class="p-4">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-sm font-semibold">{{ __('GMV — last 30 days') }}</h2>
            <p class="text-[13px] text-ink-soft">
                {{ __('Total :total · peak day :peak', ['total' => \App\Support\Money::format($chart['totalSen']), 'peak' => \App\Support\Money::format($chart['maxSen'])]) }}
            </p>
        </div>

        <svg viewBox="0 0 600 140" class="mt-3 h-36 w-full" role="img"
             aria-label="{{ __('Daily paid GMV from :from to :to', ['from' => $chart['firstDay'], 'to' => $chart['lastDay']]) }}">
            <polyline points="{{ $chart['baseline'] }}" fill="none" stroke="var(--color-line)" stroke-width="1" />
            <polyline points="{{ $chart['points'] }}" fill="none" stroke="var(--color-emerald)" stroke-width="2"
                      stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
        </svg>

        <div class="mt-1 flex justify-between text-[12px] tabular-nums text-ink-faint">
            <span>{{ $chart['firstDay'] }}</span>
            <span>{{ $chart['lastDay'] }}</span>
        </div>
    </x-ui.card>

    <div class="grid gap-4 lg:grid-cols-2">

        {{-- Orders by status --}}
        <x-ui.card>
            <div class="border-b border-line px-4 py-3">
                <h2 class="text-sm font-semibold">{{ __('Orders by status') }}</h2>
            </div>
            <table class="w-full text-[13px]">
                <caption class="sr-only">{{ __('Sub-order counts per status') }}</caption>
                <tbody>
                    @foreach (\App\Enums\SubOrderStatus::cases() as $status)
                        <tr class="border-b border-line last:border-b-0" wire:key="status-{{ $status->value }}">
                            <td class="px-4 py-2"><x-order-status-pill :status="$status" /></td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ number_format((int) ($ordersByStatus[$status->value] ?? 0)) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.card>

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
</div>
