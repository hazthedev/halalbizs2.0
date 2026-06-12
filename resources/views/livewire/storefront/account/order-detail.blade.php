<x-account-shell active="orders" :title="__('Order details')">
    <div class="space-y-4">
        <style>
            @keyframes ot-pulse {
                0% { box-shadow: 0 0 0 0 rgb(4 120 87 / 0.40); }
                100% { box-shadow: 0 0 0 9px rgb(4 120 87 / 0); }
            }
            @keyframes ot-in {
                from { opacity: 0; transform: translateY(6px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .ot-row { animation: ot-in 150ms ease-out both; animation-delay: calc(var(--i) * 40ms); }
            .ot-dot-current { animation: ot-pulse 1s ease-out 1; }
            @media (prefers-reduced-motion: reduce) {
                .ot-row, .ot-dot-current { animation: none; }
            }
        </style>

        <a href="{{ route('account.orders') }}" wire:navigate
           class="inline-flex min-h-11 items-center gap-1.5 text-sm font-medium text-ink-soft hover:text-ink">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
            {{ __('Back to orders') }}
        </a>

        {{-- Header: order number, status, placed date, contextual actions --}}
        <x-ui.card class="p-4 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h2 class="font-mono text-base font-medium text-ink">{{ $subOrder->sub_order_no }}</h2>
                        <x-order-status-pill :status="$subOrder->status" />
                    </div>
                    <p class="mt-1 text-xs text-ink-soft">{{ __('Placed :date', ['date' => $subOrder->order->placed_at->format('j M Y, g:i a')]) }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($showInvoice)
                        {{-- Plain anchor: file download must not go through wire:navigate --}}
                        <a href="{{ route('account.orders.invoice', $subOrder) }}"
                           class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-ink px-4 py-2.5 text-sm font-semibold text-ink transition-colors duration-150 hover:bg-paper">
                            {{ __('Download invoice') }}
                        </a>
                    @endif
                    @if ($subOrder->status === \App\Enums\SubOrderStatus::Delivered)
                        <x-ui.button variant="primary"
                                     wire:click="confirmReceived"
                                     wire:confirm="{{ __('Confirm you received this order? This completes the order.') }}"
                                     wire:loading.attr="disabled">
                            {{ __('Order received') }}
                        </x-ui.button>
                    @elseif ($canCancel && ! $cancelling)
                        <x-ui.button variant="danger" wire:click="$set('cancelling', true)">
                            {{ __('Cancel order') }}
                        </x-ui.button>
                    @endif
                </div>
            </div>

            @if ($subOrder->status === \App\Enums\SubOrderStatus::Shipped)
                <p class="mt-3 border-t border-line pt-3 text-sm text-ink-faint">{{ __('Waiting for delivery — the seller marked this shipped.') }}</p>
            @endif

            @if ($cancelling)
                <div class="mt-4 rounded-lg border border-danger/40 bg-danger-tint/40 p-4">
                    <label for="cancel-reason" class="block text-[13px] font-medium text-ink">{{ __('Why are you cancelling?') }}</label>
                    <select id="cancel-reason" wire:model="cancelReasonId"
                            class="mt-2 block w-full max-w-sm rounded-lg border border-line-strong bg-surface px-3 py-2.5 text-sm text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald">
                        <option value="">{{ __('Pick a reason') }}</option>
                        @foreach ($cancelReasons as $reason)
                            <option value="{{ $reason->id }}">{{ $reason->label }}</option>
                        @endforeach
                    </select>
                    @error('cancelReasonId')
                        <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
                    @enderror
                    <div class="mt-3 flex flex-wrap gap-2">
                        <x-ui.button variant="danger-fill"
                                     wire:click="cancel"
                                     wire:confirm="{{ __('Cancel this order? Items go back into stock.') }}"
                                     wire:loading.attr="disabled">
                            {{ __('Confirm cancellation') }}
                        </x-ui.button>
                        <x-ui.button variant="ghost" wire:click="$set('cancelling', false)">{{ __('Keep order') }}</x-ui.button>
                    </div>
                </div>
            @endif
        </x-ui.card>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-start">
            <div class="space-y-4">
                {{-- Status timeline (docs/03 §7): vertical, history ascending, current pulses once --}}
                <x-ui.card class="p-4 sm:p-5">
                    <h3 class="font-display text-lg font-semibold">{{ __('Order status') }}</h3>
                    <ol class="mt-4">
                        @foreach ($histories as $history)
                            @php($isCurrent = $loop->last)
                            <li class="ot-row relative flex gap-3 pb-6 last:pb-0" style="--i: {{ $loop->index }}" wire:key="history-{{ $history->id }}">
                                @unless ($loop->last && $futureStatuses === [])
                                    <span class="absolute left-[5px] top-4 h-full w-px {{ $isCurrent ? 'bg-line' : 'bg-emerald/30' }}" aria-hidden="true"></span>
                                @endunless
                                <span class="relative mt-1.5 size-[11px] shrink-0 rounded-full {{ $isCurrent ? 'ot-dot-current bg-emerald ring-2 ring-emerald-tint' : 'bg-emerald' }}" aria-hidden="true"></span>
                                <div class="min-w-0">
                                    <p class="text-sm {{ $isCurrent ? 'font-semibold text-ink' : 'font-medium text-ink' }}">
                                        {{ \App\Enums\SubOrderStatus::tryFrom($history->to_status)?->label() ?? $history->to_status }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-ink-soft">
                                        {{ $history->actor_type->label() }} · {{ $history->created_at->format('j M Y, g:i a') }}
                                    </p>
                                    @if ($history->note)
                                        <p class="mt-1 text-xs text-ink-soft">{{ $history->note }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach

                        {{-- Future steps, muted --}}
                        @foreach ($futureStatuses as $future)
                            <li class="relative flex gap-3 pb-6 last:pb-0" wire:key="future-{{ $future->value }}">
                                @unless ($loop->last)
                                    <span class="absolute left-[5px] top-4 h-full w-px bg-line" aria-hidden="true"></span>
                                @endunless
                                <span class="relative mt-1.5 size-[11px] shrink-0 rounded-full border border-line-strong bg-paper" aria-hidden="true"></span>
                                <p class="text-sm text-ink-faint">{{ $future->label() }}</p>
                            </li>
                        @endforeach
                    </ol>
                </x-ui.card>

                {{-- Items — snapshot fields only (hard rule 5); thumbs from live product if it still exists --}}
                <x-ui.card class="overflow-hidden">
                    <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
                        <a href="{{ route('store.show', $subOrder->store) }}" wire:navigate
                           class="text-sm font-semibold text-ink hover:text-emerald">{{ $subOrder->store->name }}</a>
                        <span class="text-xs text-ink-soft">{{ trans_choice('{1}:count item|[2,*]:count items', $subOrder->items->count(), ['count' => $subOrder->items->count()]) }}</span>
                    </div>
                    <ul class="divide-y divide-line">
                        @foreach ($subOrder->items as $item)
                            <li class="flex gap-3 px-4 py-3" wire:key="item-{{ $item->id }}">
                                <span class="block size-14 shrink-0 overflow-hidden rounded-lg border border-line bg-paper">
                                    @if ($item->product?->getFirstMediaUrl('images'))
                                        <img src="{{ $item->product->getFirstMediaUrl('images') }}"
                                             alt="{{ $item->product_name }}{{ $item->variant_label ? ' — '.$item->variant_label : '' }}"
                                             class="size-full object-cover" loading="lazy">
                                    @endif
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="line-clamp-2 text-sm font-medium text-ink">{{ $item->product_name }}</p>
                                    @if ($item->variant_label)
                                        <p class="mt-0.5 text-xs text-ink-soft">{{ $item->variant_label }}</p>
                                    @endif
                                    <p class="mt-0.5 text-xs text-ink-soft" style="font-feature-settings: 'tnum'">@money($item->unit_price_sen) × {{ $item->qty }}</p>
                                </div>
                                <p class="shrink-0 text-sm font-bold text-ink" style="font-feature-settings: 'tnum'">@money($item->line_total_sen)</p>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            </div>

            <div class="space-y-4">
                {{-- Tracking (visible once shipped) --}}
                @if ($subOrder->tracking_no && $subOrder->shipped_at)
                    <x-ui.card class="p-4">
                        <h3 class="text-sm font-semibold text-ink">{{ __('Tracking') }}</h3>
                        <p class="mt-2 text-sm text-ink">{{ $subOrder->tracking_courier }}</p>
                        <div class="mt-1 flex items-center justify-between gap-2">
                            <p class="truncate font-mono text-sm text-ink">{{ $subOrder->tracking_no }}</p>
                            <button type="button" x-data
                                    x-on:click="navigator.clipboard.writeText(@js($subOrder->tracking_no)).then(() => $store.toasts.push(@js(__('Tracking number copied'))))"
                                    class="flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-lg text-ink-soft hover:text-ink"
                                    aria-label="{{ __('Copy tracking number') }}">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75"/></svg>
                            </button>
                        </div>
                        @if ($subOrder->shipped_at)
                            <p class="mt-2 text-xs text-ink-soft">{{ __('Shipped :date', ['date' => $subOrder->shipped_at->format('j M Y')]) }}</p>
                        @endif
                    </x-ui.card>
                @endif

                {{-- Delivery address (snapshot from the parent order) --}}
                @php($address = $subOrder->order->shipping_address)
                <x-ui.card class="p-4">
                    <h3 class="text-sm font-semibold text-ink">{{ __('Delivery address') }}</h3>
                    <p class="mt-2 text-sm font-medium text-ink">{{ $address['recipient_name'] ?? '' }}</p>
                    @if (! empty($address['phone']))
                        <p class="text-sm text-ink-soft">{{ $address['phone'] }}</p>
                    @endif
                    <p class="mt-1 text-sm leading-relaxed text-ink-soft">
                        {{ $address['line1'] ?? '' }}@if (! empty($address['line2'])), {{ $address['line2'] }}@endif<br>
                        {{ $address['postcode'] ?? '' }} {{ $address['city'] ?? '' }}<br>
                        {{ $address['state'] ?? '' }}
                    </p>
                </x-ui.card>

                {{-- Totals + payment --}}
                <x-ui.card class="p-4">
                    <h3 class="text-sm font-semibold text-ink">{{ __('Order summary') }}</h3>
                    <dl class="mt-3 space-y-2 text-sm" style="font-feature-settings: 'tnum'">
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-soft">{{ __('Items subtotal') }}</dt>
                            <dd class="text-ink">@money($subOrder->items_subtotal_sen)</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-soft">{{ __('Shipping') }}</dt>
                            <dd class="text-ink">@money($subOrder->shipping_fee_sen)</dd>
                        </div>
                        @if ($subOrder->shop_discount_sen > 0)
                            <div class="flex justify-between gap-3">
                                <dt class="text-ink-soft">{{ __('Shop discount') }}</dt>
                                <dd class="text-emerald">-@money($subOrder->shop_discount_sen)</dd>
                            </div>
                        @endif
                        <div class="flex justify-between gap-3 border-t border-line pt-2">
                            <dt class="font-semibold text-ink">{{ __('Total') }}</dt>
                            <dd class="text-base font-bold text-ink">@money($subOrder->total_sen)</dd>
                        </div>
                    </dl>

                    <div class="mt-4 space-y-1.5 border-t border-line pt-3 text-xs text-ink-soft">
                        <p>{{ __('Payment method') }}: {{ $subOrder->order->payment_method->label() }}</p>
                        <p>{{ __('Order number') }}: <span class="font-mono text-ink">{{ $subOrder->order->order_no }}</span></p>
                        @if ($subOrder->order->paid_at)
                            <p>{{ __('Paid :date', ['date' => $subOrder->order->paid_at->format('j M Y, g:i a')]) }}</p>
                        @endif
                    </div>
                </x-ui.card>
            </div>
        </div>
    </div>
</x-account-shell>
