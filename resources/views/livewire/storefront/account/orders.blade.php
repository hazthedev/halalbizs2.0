<x-account-shell active="orders" :title="__('Orders')">
    <div class="space-y-4">
        {{-- Shopee-style status tabs (docs/03 §6): horizontal scroll, active = ink underline, emerald-tint count chips --}}
        <div class="-mx-4 overflow-x-auto px-4 lg:mx-0 lg:px-0">
            <nav class="flex min-w-max border-b border-line" aria-label="{{ __('Order status') }}">
                @foreach ($tabs as $key => $label)
                    @php($isActive = $tab === $key)
                    <button type="button" wire:click="setTab('{{ $key }}')"
                            @if ($isActive) aria-current="true" @endif
                            class="-mb-px flex min-h-11 shrink-0 items-center gap-1.5 whitespace-nowrap border-b-2 px-3 text-sm transition-colors duration-150 {{ $isActive
                                ? 'border-ink font-semibold text-ink'
                                : 'border-transparent font-medium text-ink-soft hover:text-ink' }}">
                        {{ $label }}
                        @if (($counts[$key] ?? 0) > 0)
                            <span class="rounded-full bg-emerald-tint px-1.5 py-0.5 text-[11px] font-semibold leading-none text-emerald">{{ $counts[$key] }}</span>
                        @endif
                    </button>
                @endforeach
            </nav>
        </div>

        {{-- Order search — narrows the active tab by order no, sub-order no or product name --}}
        <div class="relative max-w-sm">
            <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/></svg>
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('Search order no. or product') }}"
                aria-label="{{ __('Search your orders') }}"
                class="block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface py-2 pl-9 pr-3 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald"
            >
        </div>

        <div class="space-y-3" wire:loading.class="opacity-60" wire:target="setTab, search">
            @if ($tab === 'to-pay')
                {{-- Parent orders awaiting iPay88 payment --}}
                @forelse ($orders as $order)
                    <x-ui.card class="p-4" wire:key="order-{{ $order->id }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-mono text-sm font-medium text-ink">{{ $order->order_no }}</p>
                                <p class="mt-0.5 text-xs text-ink-soft">
                                    {{ __('Placed :date', ['date' => $order->placed_at->format('j M Y, g:i a')]) }}
                                    · {{ trans_choice('{1}:count seller|[2,*]:count sellers', $order->subOrders->count(), ['count' => $order->subOrders->count()]) }}
                                </p>
                            </div>
                            @if ($order->expires_at)
                                @php($minutesLeft = max(0, (int) ceil(now()->diffInMinutes($order->expires_at, false))))
                                @php($timeLeft = $minutesLeft >= 60 ? sprintf('%dh %dm', intdiv($minutesLeft, 60), $minutesLeft % 60) : sprintf('%dm', $minutesLeft))
                                <x-ui.badge variant="warn">{{ __('Expires in :time', ['time' => $timeLeft]) }}</x-ui.badge>
                            @endif
                        </div>

                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-3">
                            <p class="text-sm text-ink-soft">
                                {{ __('Total') }}
                                <span class="ml-1 text-base font-bold text-ink" style="font-feature-settings: 'tnum'">@money($order->grand_total_sen)</span>
                            </p>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-ui.button variant="danger"
                                             wire:click="cancelUnpaidOrder({{ $order->id }})"
                                             wire:confirm="{{ __('Cancel this order? Items go back into stock and nothing will be charged.') }}"
                                             wire:loading.attr="disabled">
                                    {{ __('Cancel order') }}
                                </x-ui.button>
                                <a href="{{ route('payments.ipay88.pay', $order) }}"
                                   class="inline-flex min-h-11 items-center justify-center gap-2 rounded-[var(--radius-control)] bg-emerald px-4 py-2.5 text-sm font-semibold text-white transition-colors duration-150 hover:bg-emerald-deep">
                                    {{ __('Pay now') }}
                                </a>
                            </div>
                        </div>
                    </x-ui.card>
                @empty
                    @if (trim($search) !== '')
                        @php $noMatchTitle = __('No orders match ":term".', ['term' => trim($search)]); @endphp
                        <x-ui.empty-state :title="$noMatchTitle" :message="__('Check the spelling or try another tab — the search only looks within this one.')">
                            <x-ui.button wire:click="$set('search', '')">{{ __('Clear search') }}</x-ui.button>
                        </x-ui.empty-state>
                    @else
                        <x-ui.empty-state :title="__('Nothing waiting for payment.')" :message="__('Orders paid by FPX, card or e-wallet sit here until payment clears.')">
                            <x-ui.button href="{{ route('home') }}">{{ __('Start shopping') }}</x-ui.button>
                        </x-ui.empty-state>
                    @endif
                @endforelse

                <div>{{ $orders->links() }}</div>
            @else
                {{-- Sub-orders for the selected status group --}}
                @forelse ($subOrders as $subOrder)
                    @php($firstItem = $subOrder->items->first())
                    <x-ui.card wire:key="sub-order-{{ $subOrder->id }}" class="overflow-hidden">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-2.5">
                            <div class="flex min-w-0 items-center gap-2.5">
                                <a href="{{ $subOrder->store->storefrontUrl() }}" wire:navigate
                                   class="truncate text-sm font-semibold text-ink hover:text-emerald">{{ $subOrder->store->name }}</a>
                                <span class="hidden font-mono text-xs text-ink-faint sm:inline">{{ $subOrder->sub_order_no }}</span>
                            </div>
                            <x-order-status-pill :status="$subOrder->status" />
                        </div>

                        <a href="{{ route('account.orders.show', $subOrder) }}" wire:navigate
                           class="flex gap-3 px-4 py-3 transition-colors duration-150 hover:bg-paper">
                            <span class="block size-16 shrink-0 overflow-hidden rounded-[var(--radius-card)] border border-line bg-paper">
                                @if ($firstItem?->product?->getFirstMediaUrl('images', 'thumb'))
                                    <img src="{{ $firstItem->product->getFirstMediaUrl('images', 'thumb') }}"
                                         alt="{{ $firstItem->product_name }}{{ $firstItem->variant_label ? ' — '.$firstItem->variant_label : '' }}"
                                         class="size-full object-cover" loading="lazy">
                                @endif
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="line-clamp-2 text-sm font-medium text-ink">{{ $firstItem?->product_name }}</span>
                                <span class="mt-0.5 block text-xs text-ink-soft">
                                    @if ($firstItem?->variant_label){{ $firstItem->variant_label }} · @endif× {{ $firstItem?->qty }}
                                </span>
                                @if ($subOrder->items->count() > 1)
                                    <span class="mt-1 block text-xs text-ink-faint">{{ __('+:count more', ['count' => $subOrder->items->count() - 1]) }}</span>
                                @endif
                                @if ($subOrder->tracking_no && in_array($subOrder->status, [\App\Enums\SubOrderStatus::Shipped, \App\Enums\SubOrderStatus::Delivered], true))
                                    <span class="mt-1 block text-xs text-ink-soft">{{ $subOrder->tracking_courier }} · <span class="font-mono">{{ $subOrder->tracking_no }}</span></span>
                                @endif
                            </span>
                        </a>

                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-4 py-3">
                            <p class="text-sm text-ink-soft">
                                {{ __('Order total') }}
                                <span class="ml-1 text-base font-bold text-ink" style="font-feature-settings: 'tnum'">@money($subOrder->total_sen)</span>
                            </p>
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($subOrder->status === \App\Enums\SubOrderStatus::Shipped)
                                    <p class="text-xs text-ink-faint">{{ __('Waiting for delivery — the seller marked this shipped.') }}</p>
                                @elseif ($subOrder->status === \App\Enums\SubOrderStatus::Delivered)
                                    <x-ui.button variant="primary"
                                                 wire:click="confirmReceived({{ $subOrder->id }})"
                                                 wire:confirm="{{ __('Confirm you received this order? This completes the order.') }}"
                                                 wire:loading.attr="disabled">
                                        {{ __('Order received') }}
                                    </x-ui.button>
                                @elseif ($subOrder->status === \App\Enums\SubOrderStatus::Completed)
                                    <x-ui.button variant="secondary" wire:click="buyAgain({{ $subOrder->id }})" wire:loading.attr="disabled">
                                        {{ __('Buy again') }}
                                    </x-ui.button>
                                @endif
                                <x-ui.button variant="ghost" :href="route('account.orders.show', $subOrder)">
                                    {{ __('View details') }}
                                </x-ui.button>
                            </div>
                        </div>

                        {{-- Review panel (M8): "Rate order" expands per-item forms; turns into "Reviewed ✓" once done --}}
                        @if ($subOrder->status === \App\Enums\SubOrderStatus::Completed)
                            <livewire:storefront.account.review-order :sub-order="$subOrder" :key="'review-order-'.$subOrder->id" />
                        @endif
                    </x-ui.card>
                @empty
                    @php
                        if (trim($search) !== '') {
                            $emptyTitle = __('No orders match ":term".', ['term' => trim($search)]);
                            $emptyMessage = __('Check the spelling or try another tab — the search only looks within this one.');
                        } elseif ($tab === 'to-ship') {
                            $emptyTitle = __('No orders to ship.');
                            $emptyMessage = __('Paid orders appear here while the seller packs them.');
                        } elseif ($tab === 'to-receive') {
                            $emptyTitle = __('Nothing on the way.');
                            $emptyMessage = __('Shipped orders appear here with their tracking numbers.');
                        } elseif ($tab === 'completed') {
                            $emptyTitle = __('No completed orders yet.');
                            $emptyMessage = __('Orders you confirm as received are kept here.');
                        } elseif ($tab === 'cancelled') {
                            $emptyTitle = __('No cancelled orders.');
                            $emptyMessage = __('Cancelled orders stay here for your records.');
                        } else {
                            $emptyTitle = __('No returns or refunds.');
                            $emptyMessage = __('Return requests and refunds appear here.');
                        }
                    @endphp
                    <x-ui.empty-state :title="$emptyTitle" :message="$emptyMessage">
                        @if (trim($search) !== '')
                            <x-ui.button wire:click="$set('search', '')">{{ __('Clear search') }}</x-ui.button>
                        @else
                            <x-ui.button href="{{ route('home') }}">{{ __('Start shopping') }}</x-ui.button>
                        @endif
                    </x-ui.empty-state>
                @endforelse

                <div>{{ $subOrders->links() }}</div>
            @endif
        </div>
    </div>
</x-account-shell>
