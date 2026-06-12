<div class="mx-auto w-full max-w-2xl px-4 py-12 lg:py-16">

    {{-- The emerald moment — a completed-order trust mark (design §2). --}}
    <div class="text-center">
        <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-emerald-tint">
            <svg class="size-8 text-emerald" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
        </div>
        <h1 class="mt-4 font-display text-[28px] font-bold">{{ __('Order placed') }}</h1>
        <p class="mt-1 text-sm text-ink-soft">{{ __('Order number') }} <span class="font-mono text-ink">{{ $order->order_no }}</span></p>
    </div>

    @if ($awaitingPayment)
        <div class="mt-6 rounded-[10px] border border-warn/40 bg-warn-tint p-4">
            <p class="text-sm font-medium text-warn">{{ __('Payment pending — complete it before the window closes.') }}</p>
            <x-ui.button :href="route('payments.ipay88.pay', $order)" class="mt-3">{{ __('Complete your payment') }}</x-ui.button>
        </div>
    @endif

    {{-- Per-sub-order summary --}}
    <x-ui.card class="mt-6">
        <ul class="divide-y divide-line">
            @foreach ($order->subOrders as $subOrder)
                <li wire:key="success-sub-{{ $subOrder->id }}" class="flex items-center justify-between gap-3 px-4 py-3.5">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">{{ $subOrder->store->name }}</p>
                        <p class="mt-0.5 font-mono text-xs text-ink-soft">{{ $subOrder->sub_order_no }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <x-ui.badge :variant="$this->statusVariant($subOrder->status)">{{ $subOrder->status->label() }}</x-ui.badge>
                        <span class="text-sm font-bold tnum">@money($subOrder->total_sen)</span>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="space-y-1 border-t border-line px-4 py-3.5">
            @if ($order->discount_total_sen > 0)
                <div class="flex items-center justify-between text-sm">
                    <span class="text-ink-soft">{{ __('Voucher discount') }}</span>
                    <span class="font-bold text-emerald tnum">-@money($order->discount_total_sen)</span>
                </div>
            @endif
            <div class="flex items-baseline justify-between">
                <span class="text-sm font-semibold">{{ __('Total') }}</span>
                <span class="text-lg font-bold tnum">@money($order->grand_total_sen)</span>
            </div>
        </div>
    </x-ui.card>

    {{-- Payment method line --}}
    <p class="mt-4 text-center text-sm text-ink-soft">
        @if ($order->payment_method === \App\Enums\PaymentMethod::Cod)
            {{ __('Pay :amount in cash when your order arrives.', ['amount' => \App\Support\Money::format($order->grand_total_sen)]) }}
        @else
            {{ $order->payment_method->label() }}
        @endif
    </p>

    <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center">
        <x-ui.button :href="route('account.orders')">{{ __('View my orders') }}</x-ui.button>
        <x-ui.button variant="ghost" :href="route('home')">{{ __('Continue shopping') }}</x-ui.button>
    </div>
</div>
