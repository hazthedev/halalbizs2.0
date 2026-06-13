<div class="mx-auto w-full max-w-7xl px-4 py-8 lg:py-12">
    <h1 class="font-display text-[28px] font-bold">{{ __('Checkout') }}</h1>

    <div class="mt-6 grid gap-4 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-start lg:gap-6">

        {{-- ===== Left column ===== --}}
        <div class="space-y-4">

            {{-- (1) Delivery address --}}
            <x-ui.card class="p-4">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold">{{ __('Delivery address') }}</h2>
                    @if ($addresses->isNotEmpty())
                        <button type="button" wire:click="$toggle('changingAddress')"
                                wire:loading.attr="disabled" wire:target="changingAddress"
                                class="-my-1 flex min-h-11 items-center rounded-lg px-2 text-sm font-semibold text-emerald hover:text-emerald-deep">
                            {{ $changingAddress ? __('Close') : __('Change') }}
                        </button>
                    @endif
                </div>

                @if ($address !== null)
                    <p class="mt-1 text-sm font-medium">
                        {{ $address->recipient_name }}
                        <span class="font-normal text-ink-soft">· {{ $address->phone }}</span>
                    </p>
                    <p class="mt-0.5 text-sm text-ink-soft">
                        {{ $address->line1 }}@if ($address->line2), {{ $address->line2 }}@endif,
                        {{ $address->postcode }} {{ $address->city }}, {{ $address->state }}
                    </p>
                @else
                    <p class="mt-1 text-sm text-ink-soft">{{ __('You have no delivery address yet — add one to place your order.') }}</p>
                    <div class="mt-3">
                        <x-ui.button variant="secondary" :href="route('account.addresses')">{{ __('Add new address') }}</x-ui.button>
                    </div>
                @endif

                @if ($addressError)
                    <p class="mt-1.5 text-[13px] text-danger">
                        {{ $addressError }}
                        <a href="{{ route('account.addresses') }}" wire:navigate class="font-semibold underline">{{ __('Add new address') }}</a>
                    </p>
                @endif

                @if ($changingAddress && $addresses->isNotEmpty())
                    <fieldset class="mt-3 border-t border-line pt-3">
                        <legend class="sr-only">{{ __('Choose a delivery address') }}</legend>
                        <ul class="space-y-2">
                            @foreach ($addresses as $option)
                                <li wire:key="checkout-address-{{ $option->id }}">
                                    <label class="flex min-h-11 cursor-pointer items-start gap-3 rounded-lg border p-3 {{ $option->id === $addressId ? 'border-emerald bg-emerald-tint' : 'border-line-strong hover:border-ink' }}">
                                        <input type="radio" name="checkout-address" value="{{ $option->id }}"
                                               wire:click="selectAddress({{ $option->id }})" @checked($option->id === $addressId)
                                               class="mt-0.5 size-4 shrink-0 cursor-pointer accent-emerald">
                                        <span class="min-w-0 text-sm">
                                            <span class="font-medium">{{ $option->recipient_name }}</span>
                                            <span class="text-ink-soft">· {{ $option->phone }}</span>
                                            @if ($option->is_default)
                                                <x-ui.badge variant="neutral" class="ml-1">{{ __('Default') }}</x-ui.badge>
                                            @endif
                                            <span class="mt-0.5 block text-[13px] text-ink-soft">
                                                {{ $option->line1 }}@if ($option->line2), {{ $option->line2 }}@endif,
                                                {{ $option->postcode }} {{ $option->city }}, {{ $option->state }}
                                            </span>
                                        </span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ route('account.addresses') }}" wire:navigate
                           class="mt-1 inline-flex min-h-11 items-center text-sm font-semibold text-emerald hover:text-emerald-deep">
                            {{ __('Add new address') }}
                        </a>
                    </fieldset>
                @endif
            </x-ui.card>

            {{-- (2) Per-seller groups (read-only item rows) --}}
            @foreach ($groups as $group)
                <x-ui.card wire:key="checkout-store-{{ $group->store->id }}">
                    <div class="flex min-h-11 items-center gap-2 border-b border-line px-4">
                        <span class="truncate text-sm font-semibold">{{ $group->store->name }}</span>
                        @if ($group->store->state)
                            <span class="shrink-0 text-xs text-ink-soft">{{ $group->store->state }}</span>
                        @endif
                    </div>

                    <ul class="divide-y divide-line">
                        @foreach ($group->lines as $line)
                            <li wire:key="checkout-line-{{ $line->variant->id }}" class="flex gap-3 px-4 py-3.5">
                                <img src="{{ $line->variant->getFirstMediaUrl('image', 'thumb') ?: $line->variant->product->getFirstMediaUrl('images', 'thumb') }}"
                                     alt="{{ $line->variant->product->getTranslation('name', app()->getLocale()) }} {{ $line->variant->options_label }}"
                                     class="size-16 shrink-0 rounded-[10px] border border-line bg-paper object-cover">
                                <div class="min-w-0 flex-1">
                                    <p class="line-clamp-2 text-sm font-medium">{{ $line->variant->product->getTranslation('name', app()->getLocale()) }}</p>
                                    @if ($line->variant->options_label)
                                        <p class="mt-0.5 truncate text-xs text-ink-soft">{{ $line->variant->options_label }}</p>
                                    @endif
                                    <p class="mt-0.5 text-[13px] text-ink-soft tnum">@price($line->variant->effectivePriceSen()) <span class="font-mono">× {{ $line->qty }}</span></p>
                                </div>
                                <span class="shrink-0 self-center text-sm font-bold tnum">@price($line->variant->effectivePriceSen() * $line->qty)</span>
                            </li>
                        @endforeach
                    </ul>

                    <div class="space-y-3 border-t border-line px-4 py-3">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-ink-soft">{{ __('Shipping') }}</span>
                            @if ($group->shippingFeeSen === null)
                                <span class="text-[13px] text-ink-faint">{{ __('Add an address to see shipping') }}</span>
                            @elseif ($group->shippingFeeSen === 0)
                                <span class="font-semibold text-emerald">{{ __('FREE') }}</span>
                            @else
                                <span class="font-bold tnum">@price($group->shippingFeeSen)</span>
                            @endif
                        </div>

                        <x-ui.input
                            name="seller-note-{{ $group->store->id }}"
                            wire:model="sellerNotes.{{ $group->store->id }}"
                            placeholder="{{ __('Note to seller (optional)') }}"
                            aria-label="{{ __('Note to seller') }}"
                            maxlength="500"
                        />
                    </div>
                </x-ui.card>
            @endforeach

            {{-- (3) Vouchers (docs/09 §B): picker drawer + manual code fallback.
                 One platform voucher + one shop voucher per order (Shopee model). --}}
            <x-ui.card class="p-4">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold">{{ __('Vouchers') }}</h2>
                    <button type="button" wire:click="$toggle('voucherPanelOpen')"
                            wire:loading.attr="disabled" wire:target="voucherPanelOpen"
                            class="-my-1 flex min-h-11 items-center rounded-lg px-2 text-sm font-semibold text-emerald hover:text-emerald-deep">
                        {{ $voucherPanelOpen ? __('Close') : __('Select voucher') }}
                    </button>
                </div>

                {{-- Applied chips --}}
                @if ($platformDiscount !== null || $shopDiscount !== null)
                    <ul class="mt-2 space-y-2">
                        @if ($platformDiscount !== null)
                            <li class="flex items-center justify-between gap-3">
                                <span class="inline-flex min-w-0 items-center gap-1 rounded-full bg-emerald-tint py-0.5 pl-3 pr-0.5 text-[13px] font-semibold text-emerald">
                                    <span class="truncate font-mono">{{ $platformDiscount->voucher->code }}</span>
                                    <span class="hidden font-normal sm:inline">· {{ __('Platform') }}</span>
                                    <button type="button" wire:click="removeVoucher('platform')"
                                            wire:loading.attr="disabled" wire:target="removeVoucher"
                                            class="flex size-11 shrink-0 items-center justify-center rounded-full hover:bg-emerald/10"
                                            aria-label="{{ __('Remove platform voucher') }}">
                                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                    </button>
                                </span>
                                @if ($platformDiscount->voucher->type === \App\Enums\VoucherType::FreeShipping)
                                    <span class="shrink-0 text-sm font-bold text-emerald">{{ __('Free shipping') }}</span>
                                @else
                                    <span class="shrink-0 text-sm font-bold text-emerald tnum">-@money($platformDiscountSen)</span>
                                @endif
                            </li>
                        @endif

                        @if ($shopDiscount !== null)
                            <li class="flex items-center justify-between gap-3">
                                <span class="inline-flex min-w-0 items-center gap-1 rounded-full bg-emerald-tint py-0.5 pl-3 pr-0.5 text-[13px] font-semibold text-emerald">
                                    <span class="truncate font-mono">{{ $shopDiscount->voucher->code }}</span>
                                    <span class="hidden truncate font-normal sm:inline">· {{ $shopDiscount->voucher->store?->name }}</span>
                                    <button type="button" wire:click="removeVoucher('shop')"
                                            wire:loading.attr="disabled" wire:target="removeVoucher"
                                            class="flex size-11 shrink-0 items-center justify-center rounded-full hover:bg-emerald/10"
                                            aria-label="{{ __('Remove shop voucher') }}">
                                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                    </button>
                                </span>
                                @if ($shopDiscount->voucher->type === \App\Enums\VoucherType::FreeShipping)
                                    <span class="shrink-0 text-sm font-bold text-emerald">{{ __('Free shipping') }}</span>
                                @else
                                    <span class="shrink-0 text-sm font-bold text-emerald tnum">-@money($shopDiscountSen)</span>
                                @endif
                            </li>
                        @endif
                    </ul>
                @endif

                {{-- Picker panel: available vouchers per scope, best-savings hint --}}
                @if ($voucherPanelOpen)
                    <div class="mt-3 space-y-4 border-t border-line pt-3">
                        <div>
                            <h3 class="text-[13px] font-medium text-ink-soft">{{ __('Platform vouchers') }}</h3>
                            @if ($platformVoucherOptions->isEmpty())
                                <p class="mt-1 text-[13px] text-ink-faint">{{ __('No platform vouchers available right now.') }}</p>
                            @else
                                <ul class="mt-2 space-y-2">
                                    @foreach ($platformVoucherOptions as $option)
                                        <li wire:key="voucher-option-{{ $option->voucher->id }}">
                                            @include('livewire.storefront.partials.voucher-option', [
                                                'option' => $option,
                                                'applied' => $appliedPlatformCode === $option->voucher->code,
                                                'best' => $option->voucher->id === $bestPlatformVoucherId,
                                            ])
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        <div>
                            <h3 class="text-[13px] font-medium text-ink-soft">{{ __('Shop vouchers') }}</h3>
                            <p class="mt-0.5 text-[12px] text-ink-faint">{{ __('One shop voucher per order — it applies to that shop’s items only.') }}</p>
                            @if ($shopVoucherOptions->isEmpty())
                                <p class="mt-1 text-[13px] text-ink-faint">{{ __('No shop vouchers available for the shops in your order.') }}</p>
                            @else
                                <ul class="mt-2 space-y-2">
                                    @foreach ($shopVoucherOptions as $option)
                                        <li wire:key="voucher-option-{{ $option->voucher->id }}">
                                            @include('livewire.storefront.partials.voucher-option', [
                                                'option' => $option,
                                                'applied' => $appliedShopCode === $option->voucher->code,
                                                'best' => false,
                                            ])
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Manual code entry stays as the fallback --}}
                <form wire:submit="applyVoucher" class="mt-3 flex gap-2">
                    <input type="text" wire:model="voucherCode" placeholder="{{ __('Voucher code') }}"
                           aria-label="{{ __('Voucher code') }}"
                           class="block min-h-11 w-full rounded-lg border bg-surface px-3.5 py-2.5 font-mono text-sm text-ink placeholder:font-sans placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $voucherError ? 'border-danger' : 'border-line-strong' }}">
                    <x-ui.button type="submit" variant="secondary" class="shrink-0" wire:loading.attr="disabled" wire:target="applyVoucher">{{ __('Apply') }}</x-ui.button>
                </form>

                @if ($voucherError)
                    <p class="mt-1.5 text-[13px] text-danger">{{ $voucherError }}</p>
                @endif
            </x-ui.card>
        </div>

        {{-- ===== Right: sticky summary ===== --}}
        <x-ui.card class="sticky bottom-0 z-10 p-4 lg:bottom-auto lg:top-24">
            <h2 class="text-sm font-semibold">{{ __('Order summary') }}</h2>

            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-ink-soft">{{ __('Items subtotal') }}</dt>
                    <dd class="font-bold tnum">@money($subtotalSen)</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-ink-soft">{{ __('Shipping total') }}</dt>
                    <dd class="font-bold tnum">@if ($address === null) — @else @money($shippingTotalSen) @endif</dd>
                </div>
                @if ($platformDiscountSen > 0)
                    <div class="flex items-center justify-between">
                        <dt class="text-ink-soft">{{ __('Platform voucher') }}</dt>
                        <dd class="font-bold text-emerald tnum">-@money($platformDiscountSen)</dd>
                    </div>
                @endif
                @if ($shopDiscountSen > 0)
                    <div class="flex items-center justify-between">
                        <dt class="text-ink-soft">{{ __('Shop voucher') }}</dt>
                        <dd class="font-bold text-emerald tnum">-@money($shopDiscountSen)</dd>
                    </div>
                @endif
            </dl>

            <div class="mt-3 border-t border-line pt-3">
                <div class="flex items-baseline justify-between">
                    <span class="text-sm font-semibold">{{ __('Grand total') }}</span>
                    <span class="text-2xl font-bold tnum">@money($grandTotalSen)</span>
                </div>
                @if ($displayCurrency !== 'MYR')
                    <p class="mt-0.5 text-right text-[13px] text-ink-soft tnum">@price($grandTotalSen)</p>
                    <p class="text-right text-[13px] text-ink-faint">{{ __("You'll be charged in MYR.") }}</p>
                @endif
            </div>

            {{-- Payment method --}}
            <fieldset class="mt-4">
                <legend class="text-[13px] font-medium">{{ __('Payment method') }}</legend>
                <div class="mt-2 space-y-2">
                    <label class="flex min-h-11 cursor-pointer items-start gap-3 rounded-lg border p-3 {{ $paymentMethod === 'ipay88' ? 'border-emerald bg-emerald-tint' : 'border-line-strong hover:border-ink' }}">
                        <input type="radio" name="payment-method" value="ipay88" wire:model.live="paymentMethod"
                               @checked($paymentMethod === 'ipay88')
                               class="mt-0.5 size-4 shrink-0 cursor-pointer accent-emerald">
                        <span class="min-w-0">
                            <span class="block text-sm font-medium">{{ __('Online payment — FPX, cards, e-wallets') }}</span>
                            {{-- Gateway logos land with the M5 iPay88 bridge — text marks until then. --}}
                            <span class="mt-0.5 block text-[11px] uppercase tracking-[0.04em] text-ink-soft">FPX · Visa · Mastercard · TnG · GrabPay · Boost · ShopeePay</span>
                        </span>
                    </label>

                    @if ($codUnavailableReason === null)
                        <label class="flex min-h-11 cursor-pointer items-start gap-3 rounded-lg border p-3 {{ $paymentMethod === 'cod' ? 'border-emerald bg-emerald-tint' : 'border-line-strong hover:border-ink' }}">
                            <input type="radio" name="payment-method" value="cod" wire:model.live="paymentMethod"
                                   @checked($paymentMethod === 'cod')
                                   class="mt-0.5 size-4 shrink-0 cursor-pointer accent-emerald">
                            <span class="block text-sm font-medium">{{ __('Cash on delivery') }}</span>
                        </label>
                    @else
                        <div class="rounded-lg border border-line p-3">
                            <span class="block text-sm font-medium text-ink-faint">{{ __('Cash on delivery') }}</span>
                            <span class="mt-0.5 block text-[13px] text-ink-soft">{{ $codUnavailableReason }}</span>
                        </div>
                    @endif
                </div>
            </fieldset>

            {{-- Recalculating guard (design §7, Bagisto race lesson): ANY
                 in-flight request from this component disables the CTA; the
                 server re-validates everything inside the transaction anyway. --}}
            <x-ui.button type="button" wire:click="placeOrder"
                         wire:loading.attr="disabled" wire:loading.class="opacity-50"
                         class="mt-4 w-full">
                {{ __('Place order') }}
            </x-ui.button>
            <p wire:loading class="mt-2 text-center text-[13px] text-ink-soft" aria-live="polite">{{ __('Updating totals…') }}</p>
        </x-ui.card>
    </div>
</div>
