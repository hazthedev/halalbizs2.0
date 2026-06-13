<div>
    <x-account-shell active="dashboard" :title="__('Overview')">
        @if (! $hasOrders)
            {{-- Empty state (design §6): one display line, one sentence, one emerald action. --}}
            <x-ui.card class="px-6 py-16 text-center">
                <p class="font-display text-[22px] font-bold leading-tight">{{ __('Your shopping starts here') }}</p>
                <p class="mx-auto mt-2 max-w-md text-sm text-ink-soft">
                    {{ __('Once you place an order, your spending, deliveries and saved items show up on this overview.') }}
                </p>
                <a href="{{ route('home') }}" wire:navigate
                   class="mt-6 inline-flex min-h-11 items-center rounded-lg bg-emerald px-5 text-sm font-semibold text-white hover:bg-emerald-deep focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-2">
                    {{ __('Start shopping') }}
                </a>
            </x-ui.card>

            {{-- Even first-timers get picks (cold-start → popular). --}}
            <livewire:storefront.recommended-products context="dashboard" wire:key="rec-dash-empty" />
        @else
            <div class="space-y-6">

                {{-- Header + period picker (mirrors the admin dashboard) --}}
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="font-display text-lg font-semibold leading-tight">{{ __('Your activity') }}</h2>

                    <div class="ml-auto inline-flex rounded-lg border border-line bg-surface p-0.5" role="group" aria-label="{{ __('Spend period') }}">
                        @foreach ($this->periods() as $key => $label)
                            <button type="button"
                                    wire:click="$set('period', '{{ $key }}')"
                                    wire:key="period-{{ $key }}"
                                    aria-pressed="{{ $period === $key ? 'true' : 'false' }}"
                                    class="min-h-10 rounded-md px-3 text-[13px] font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $period === $key ? 'bg-ink text-paper' : 'text-ink-soft hover:text-ink' }}">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Stat row — count up on first load (design §7, app.js countUp) --}}
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                    <x-ui.card class="p-4">
                        <p class="text-[13px] font-medium text-ink-soft">{{ __('Total spent') }}</p>
                        <p class="mt-1 font-display text-[28px] font-bold leading-tight tabular-nums text-emerald">@money($totalSpentSen)</p>
                        <p class="mt-0.5 text-[12px] text-ink-faint">{{ __('Paid orders') }}</p>
                    </x-ui.card>

                    <x-ui.card class="p-4" x-data="countUp({{ $ordersPlaced }})">
                        <p class="text-[13px] font-medium text-ink-soft">{{ __('Orders placed') }}</p>
                        <p class="mt-1 font-display text-[28px] font-bold leading-tight tabular-nums" x-text="display.toLocaleString()">{{ number_format($ordersPlaced) }}</p>
                        <p class="mt-0.5 text-[12px] text-ink-faint">{{ __('All time') }}</p>
                    </x-ui.card>

                    <x-ui.card class="p-4" x-data="countUp({{ $itemsBought }})">
                        <p class="text-[13px] font-medium text-ink-soft">{{ __('Items bought') }}</p>
                        <p class="mt-1 font-display text-[28px] font-bold leading-tight tabular-nums" x-text="display.toLocaleString()">{{ number_format($itemsBought) }}</p>
                        <p class="mt-0.5 text-[12px] text-ink-faint">{{ __('Across all orders') }}</p>
                    </x-ui.card>

                    <x-ui.card class="p-4" x-data="countUp({{ $reviewsWritten }})">
                        <p class="text-[13px] font-medium text-ink-soft">{{ __('Reviews written') }}</p>
                        <p class="mt-1 font-display text-[28px] font-bold leading-tight tabular-nums" x-text="display.toLocaleString()">{{ number_format($reviewsWritten) }}</p>
                        <p class="mt-0.5 text-[12px] text-ink-faint">{{ __('Thanks for sharing') }}</p>
                    </x-ui.card>

                    <x-ui.card class="p-4" x-data="countUp({{ $wishlistSaved }})">
                        <p class="text-[13px] font-medium text-ink-soft">{{ __('Wishlist saved') }}</p>
                        <p class="mt-1 font-display text-[28px] font-bold leading-tight tabular-nums" x-text="display.toLocaleString()">{{ number_format($wishlistSaved) }}</p>
                        <p class="mt-0.5 text-[12px] text-ink-faint">{{ __('Items you love') }}</p>
                    </x-ui.card>
                </div>

                {{-- Quick-status shortcuts → order tabs --}}
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    @foreach ($quickStatus as $shortcut)
                        <a href="{{ $shortcut['url'] }}" wire:navigate wire:key="quick-{{ $shortcut['key'] }}"
                           class="group flex min-h-11 items-center justify-between rounded-[10px] border border-line bg-surface p-4 hover:border-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                            <span class="text-[13px] font-medium text-ink-soft group-hover:text-ink">{{ $shortcut['label'] }}</span>
                            <span class="font-display text-[22px] font-bold tabular-nums {{ $shortcut['count'] > 0 ? 'text-ink' : 'text-ink-faint' }}">{{ number_format($shortcut['count']) }}</span>
                        </a>
                    @endforeach
                </div>

                {{-- Charts --}}
                <div class="grid gap-4 lg:grid-cols-2">
                    {{-- Spend over time --}}
                    <x-ui.card class="p-4">
                        <h2 class="text-sm font-semibold">{{ __('Spend over time') }}</h2>
                        <p class="mt-0.5 text-[12px] text-ink-faint">{{ __('Paid orders by month') }}</p>
                        <div class="mt-3">
                            <x-ui.chart id="buyer-spend" :payload="$spendChart" refresh-event="buyer-charts"
                                        :height="280"
                                        aria-label="{{ __('Monthly spend on paid orders') }}" />
                        </div>
                    </x-ui.card>

                    {{-- Orders by status --}}
                    <x-ui.card class="p-4">
                        <h2 class="text-sm font-semibold">{{ __('Orders by status') }}</h2>
                        <p class="mt-0.5 text-[12px] text-ink-faint">{{ __('Where your orders are right now') }}</p>
                        <div class="mt-3">
                            <x-ui.chart id="buyer-status" :payload="$statusChart"
                                        :height="280"
                                        aria-label="{{ __('Sub-orders grouped by status') }}" />
                        </div>
                    </x-ui.card>
                </div>

                {{-- Recent orders mini-list --}}
                <x-ui.card>
                    <div class="flex items-center justify-between border-b border-line px-4 py-3">
                        <h2 class="text-sm font-semibold">{{ __('Recent orders') }}</h2>
                        <a href="{{ route('account.orders') }}" wire:navigate
                           class="text-[13px] font-medium text-emerald underline-offset-2 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                            {{ __('View all') }}
                        </a>
                    </div>

                    <ul class="divide-y divide-line">
                        @foreach ($recentOrders as $subOrder)
                            <li wire:key="recent-{{ $subOrder->id }}">
                                <a href="{{ route('account.orders.show', $subOrder) }}" wire:navigate
                                   class="flex min-h-11 items-center gap-3 px-4 py-3 hover:bg-paper focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-ink">{{ $subOrder->store?->name ?? __('Store') }}</p>
                                        <p class="truncate font-mono text-[12px] text-ink-soft">{{ $subOrder->sub_order_no }}</p>
                                    </div>
                                    <x-order-status-pill :status="$subOrder->status" />
                                    <span class="font-semibold tabular-nums whitespace-nowrap text-ink">@money($subOrder->total_sen)</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>

                {{-- Picked for you --}}
                <livewire:storefront.recommended-products context="dashboard" wire:key="rec-dash" />

            </div>
        @endif
    </x-account-shell>
</div>
