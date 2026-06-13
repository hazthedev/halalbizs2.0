@php
    use App\Enums\SubOrderStatus;

    $placedAt = $subOrder->order->placed_at ?? $subOrder->order->created_at;
    $address = $subOrder->order->shipping_address ?? [];
    $payment = $subOrder->order->payment;
@endphp

<div class="space-y-4">

    {{-- Header --}}
    <div>
        <a href="{{ route('admin.orders.index') }}" wire:navigate
           class="inline-flex min-h-11 items-center gap-1.5 text-[13px] font-medium text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">
            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
            {{ __('Orders') }}
        </a>
        <div class="mt-1 flex flex-wrap items-center gap-3">
            <h1 class="font-mono text-xl font-semibold text-ink">{{ $subOrder->sub_order_no }}</h1>
            <x-order-status-pill :status="$subOrder->status" />
        </div>
        <p class="mt-1 text-[13px] text-ink-soft">
            {{ __('Order :no', ['no' => $subOrder->order->order_no]) }}
            · {{ __('Placed :date', ['date' => $placedAt->format('j M Y, g:ia')]) }}
            · {{ __('Store: :name', ['name' => $subOrder->store->name]) }}
            · {{ __('Buyer: :name', ['name' => $subOrder->order->user->name]) }}
        </p>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">

        {{-- Left: items + timeline --}}
        <div class="space-y-4 lg:col-span-2">

            {{-- Items (snapshots are sacred — read-only by design, never live product data) --}}
            <x-ui.card class="overflow-x-auto">
                <div class="border-b border-line px-4 py-3">
                    <h2 class="text-sm font-semibold">{{ __('Items') }}</h2>
                </div>
                <table class="w-full min-w-[520px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-ink-soft">
                            <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Product') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-right font-medium">{{ __('Unit price') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-right font-medium">{{ __('Qty') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-right font-medium">{{ __('Line total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($subOrder->items as $item)
                            <tr class="border-b border-line last:border-b-0" wire:key="item-{{ $item->id }}">
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-3">
                                        @if ($url = $item->product?->getFirstMediaUrl('images', 'thumb'))
                                            <img src="{{ $url }}" alt="{{ trim($item->product_name.' '.($item->variant_label ?? '')) }}"
                                                 class="size-10 shrink-0 rounded-lg border border-line bg-paper object-cover">
                                        @else
                                            <span class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-line bg-paper text-ink-faint">
                                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 19.5h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Z"/></svg>
                                            </span>
                                        @endif
                                        <div class="min-w-0">
                                            <p class="line-clamp-2 font-medium text-ink">{{ $item->product_name }}</p>
                                            @if ($item->variant_label)
                                                <p class="text-[12px] text-ink-soft">{{ $item->variant_label }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono tabular-nums whitespace-nowrap">@money($item->unit_price_sen)</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ $item->qty }}</td>
                                <td class="px-4 py-2.5 text-right font-mono font-semibold tabular-nums whitespace-nowrap">@money($item->line_total_sen)</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-ui.card>

            {{-- Status timeline --}}
            <x-ui.card class="p-4">
                <h2 class="text-sm font-semibold">{{ __('Timeline') }}</h2>
                <ol class="mt-3 space-y-3">
                    @foreach ($subOrder->statusHistories as $history)
                        <li class="flex gap-3" wire:key="history-{{ $history->id }}">
                            <span class="mt-1 flex flex-col items-center self-stretch">
                                <span class="size-2 shrink-0 rounded-full {{ $loop->last ? 'bg-emerald' : 'bg-line-strong' }}"></span>
                                @unless ($loop->last)
                                    <span class="mt-1 w-px flex-1 bg-line"></span>
                                @endunless
                            </span>
                            <div class="pb-1">
                                <p class="text-[13px] font-medium text-ink">{{ SubOrderStatus::from($history->to_status)->label() }}</p>
                                <p class="text-[12px] text-ink-soft">
                                    {{ $history->created_at->format('j M Y, g:ia') }} · {{ $history->actor_type->label() }}
                                </p>
                                @if ($history->note)
                                    <p class="mt-0.5 text-[12px] text-ink-soft">{{ $history->note }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </x-ui.card>
        </div>

        {{-- Right: admin powers, payment, address, totals --}}
        <div class="space-y-4">

            {{-- Admin powers (docs/08 §E) --}}
            <x-ui.card class="space-y-3 p-4">
                <h2 class="text-sm font-semibold">{{ __('Admin actions') }}</h2>

                @if ($canForceCancel)
                    <div class="space-y-2">
                        <label for="cancel-reason" class="block text-[13px] font-medium text-ink">{{ __('Cancellation reason') }}</label>
                        <input id="cancel-reason" type="text" wire:model="cancelReason"
                               placeholder="{{ __('e.g. Fraudulent order, seller unreachable') }}"
                               class="block min-h-11 w-full rounded-[var(--radius-control)] border bg-surface px-3 text-[13px] text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('cancelReason') ? 'border-danger' : 'border-line-strong' }}">
                        @error('cancelReason')
                            <p class="text-[13px] text-danger">{{ $message }}</p>
                        @enderror
                        <button type="button" wire:click="forceCancel" wire:loading.attr="disabled"
                                wire:confirm="{{ __('Force-cancel this sub-order? Items return to stock and the buyer is notified — this cannot be undone.') }}"
                                class="inline-flex min-h-11 w-full items-center justify-center rounded-[var(--radius-control)] border border-danger px-4 text-sm font-semibold text-danger hover:bg-danger-tint disabled:cursor-not-allowed disabled:opacity-50 focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-2">
                            {{ __('Force cancel') }}
                        </button>
                    </div>
                @endif

                @if ($canMarkRefunded)
                    <div class="space-y-2 {{ $canForceCancel ? 'border-t border-line pt-3' : '' }}">
                        <label for="refund-reference" class="block text-[13px] font-medium text-ink">{{ __('iPay88 portal reference') }}</label>
                        <input id="refund-reference" type="text" wire:model="refundReference"
                               placeholder="{{ __('Reference from the iPay88 merchant portal') }}"
                               class="block min-h-11 w-full rounded-[var(--radius-control)] border bg-surface px-3 font-mono text-[13px] text-ink placeholder:font-sans placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('refundReference') ? 'border-danger' : 'border-line-strong' }}">
                        @error('refundReference')
                            <p class="text-[13px] text-danger">{{ $message }}</p>
                        @enderror
                        <p class="text-[12px] text-ink-soft">{{ __('Process the refund in the iPay88 merchant portal first, then record its reference here.') }}</p>
                        <button type="button" wire:click="markRefunded" wire:loading.attr="disabled"
                                wire:confirm="{{ __('Mark this sub-order refunded? The order payment status flips to refunded — this cannot be undone.') }}"
                                class="inline-flex min-h-11 w-full items-center justify-center rounded-[var(--radius-control)] border border-danger px-4 text-sm font-semibold text-danger hover:bg-danger-tint disabled:cursor-not-allowed disabled:opacity-50 focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-2">
                            {{ __('Mark refunded') }}
                        </button>
                    </div>
                @endif

                @if (! $canForceCancel && ! $canMarkRefunded)
                    <p class="text-[13px] text-ink-soft">{{ __('No admin actions are available in this status.') }}</p>
                @endif

                @if ($subOrder->status === SubOrderStatus::Cancelled && $subOrder->cancel_reason)
                    <p class="text-[13px] text-ink-soft">{{ __('Cancelled — reason: :reason', ['reason' => $subOrder->cancel_reason]) }}</p>
                @endif

                {{-- Snapshots are sacred (hard rule 5) — no edits, by design. --}}
                <p class="border-t border-line pt-3 text-[12px] text-ink-faint">
                    {{ __('Items, prices and address are purchase-time snapshots — read-only by design.') }}
                </p>
            </x-ui.card>

            {{-- Payment --}}
            <x-ui.card class="p-4">
                <h2 class="text-sm font-semibold">{{ __('Payment') }}</h2>
                <dl class="mt-2 space-y-1.5 text-[13px]">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-ink-soft">{{ __('Method') }}</dt>
                        <dd class="font-medium">{{ $subOrder->order->payment_method->label() }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-ink-soft">{{ __('Order payment status') }}</dt>
                        <dd class="font-medium">{{ $subOrder->order->payment_status->label() }}</dd>
                    </div>
                    @if ($payment)
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-ink-soft">{{ __('Gateway ref') }}</dt>
                            <dd class="font-mono">{{ $payment->ref_no }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-ink-soft">{{ __('Gateway status') }}</dt>
                            <dd class="font-medium">{{ $payment->status->label() }}</dd>
                        </div>
                        @if ($payment->paid_at)
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-ink-soft">{{ __('Paid at') }}</dt>
                                <dd>{{ $payment->paid_at->format('j M Y, g:ia') }}</dd>
                            </div>
                        @endif
                    @endif
                </dl>
                <a href="{{ route('admin.payments.index') }}" wire:navigate
                   class="mt-3 inline-flex min-h-11 items-center border-t border-line pt-2 text-[13px] font-medium text-emerald hover:text-emerald-deep focus-visible:ring-2 focus-visible:ring-emerald">
                    {{ __('Open payments reconciliation') }}
                </a>
            </x-ui.card>

            {{-- Address (order snapshot — sacred) --}}
            <x-ui.card class="p-4">
                <h2 class="text-sm font-semibold">{{ __('Ship to') }}</h2>
                <div class="mt-2 space-y-0.5 text-[13px]">
                    <p class="font-medium text-ink">{{ $address['recipient_name'] ?? '—' }}</p>
                    @if (! empty($address['phone']))
                        <p class="text-ink-soft">{{ $address['phone'] }}</p>
                    @endif
                    <p class="text-ink-soft">{{ $address['line1'] ?? '' }}</p>
                    @if (! empty($address['line2']))
                        <p class="text-ink-soft">{{ $address['line2'] }}</p>
                    @endif
                    <p class="text-ink-soft">{{ trim(($address['postcode'] ?? '').' '.($address['city'] ?? '')) }}</p>
                    <p class="text-ink-soft">{{ $address['state'] ?? '' }}</p>
                </div>
            </x-ui.card>

            {{-- Totals --}}
            <x-ui.card class="p-4">
                <h2 class="text-sm font-semibold">{{ __('Totals') }}</h2>
                <dl class="mt-3 space-y-1.5 text-[13px]">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-ink-soft">{{ __('Items subtotal') }}</dt>
                        <dd class="font-mono tabular-nums">@money($subOrder->items_subtotal_sen)</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-ink-soft">{{ __('Shipping fee') }}</dt>
                        <dd class="font-mono tabular-nums">@money($subOrder->shipping_fee_sen)</dd>
                    </div>
                    @if ($subOrder->shop_discount_sen > 0)
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-ink-soft">{{ __('Shop discount') }}</dt>
                            <dd class="font-mono tabular-nums">-@money($subOrder->shop_discount_sen)</dd>
                        </div>
                    @endif
                    <div class="flex items-center justify-between gap-3 border-t border-line pt-1.5">
                        <dt class="font-semibold text-ink">{{ __('Total') }}</dt>
                        <dd class="font-mono font-semibold tabular-nums">@money($subOrder->total_sen)</dd>
                    </div>
                </dl>
                <p class="mt-3 border-t border-line pt-2 text-[12px] text-ink-soft">
                    {{ __('Platform commission rate: :rate%', ['rate' => $commissionRate]) }}
                    @if ($subOrder->commission_sen !== null)
                        · {{ __('Commission: :amount', ['amount' => \App\Support\Money::format($subOrder->commission_sen)]) }}
                    @endif
                </p>
            </x-ui.card>
        </div>
    </div>
</div>
