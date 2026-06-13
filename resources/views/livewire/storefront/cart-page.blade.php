<div class="mx-auto w-full max-w-7xl px-4 py-8 lg:py-12">
    <h1 class="font-display text-[28px] font-bold">{{ __('Cart') }}</h1>

    @if ($groups->isEmpty())
        {{-- Empty state (design §6: one display line + one sentence + one emerald action) --}}
        <div class="mx-auto max-w-md py-16 text-center">
            <p class="font-display text-[22px] font-semibold">{{ __('Your cart is empty') }}</p>
            <p class="mt-2 text-sm text-ink-soft">{{ __('Things you add will appear here, grouped by seller.') }}</p>
            <div class="mt-6 flex justify-center">
                <x-ui.button :href="route('home')">{{ __('Start shopping') }}</x-ui.button>
            </div>
        </div>
    @else
        <div class="mt-6 grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px] lg:items-start lg:gap-6">

            {{-- ===== Cart lines, grouped by seller ===== --}}
            <div class="space-y-4">
                @auth
                    <x-ui.card class="flex items-center px-4">
                        <label class="flex min-h-11 cursor-pointer items-center gap-3">
                            <input type="checkbox" wire:click="toggleAllSelected" @checked($allSelected)
                                   class="size-5 shrink-0 cursor-pointer rounded accent-emerald">
                            <span class="text-sm font-medium">{{ __('Select all') }}</span>
                        </label>
                    </x-ui.card>
                @endauth

                @foreach ($groups as $group)
                    <x-ui.card wire:key="cart-store-{{ $group->store->id }}">
                        {{-- Store header row --}}
                        <div class="flex min-h-11 items-center gap-2 border-b border-line px-4">
                            @auth
                                <label class="-ml-3 flex size-11 shrink-0 cursor-pointer items-center justify-center">
                                    <input type="checkbox" wire:click="toggleStoreSelected({{ $group->store->id }})" @checked($group->allSelected)
                                           class="size-5 cursor-pointer rounded accent-emerald"
                                           aria-label="{{ __('Select all items from this seller') }}">
                                </label>
                            @endauth
                            <a href="{{ $group->store->storefrontUrl() }}" wire:navigate
                               class="flex min-h-11 items-center truncate text-sm font-semibold hover:underline">
                                {{ $group->store->name }}
                            </a>
                            @if ($group->store->state)
                                <span class="shrink-0 text-xs text-ink-soft">{{ $group->store->state }}</span>
                            @endif
                        </div>

                        {{-- Item rows --}}
                        <ul class="divide-y divide-line">
                            @foreach ($group->lines as $line)
                                <li wire:key="cart-line-{{ $line->variant->id }}" class="flex gap-2 px-4 py-4">
                                    @auth
                                        <div class="-ml-3 flex w-11 shrink-0 items-center justify-center self-center">
                                            @unless ($line->excluded)
                                                <label class="flex size-11 cursor-pointer items-center justify-center">
                                                    <input type="checkbox" wire:click="toggleSelected({{ $line->variant->id }})" @checked($line->selected)
                                                           class="size-5 cursor-pointer rounded accent-emerald"
                                                           aria-label="{{ __('Select this item') }}">
                                                </label>
                                            @endunless
                                        </div>
                                    @endauth

                                    <a href="{{ route('product.show', $line->variant->product->slug) }}" wire:navigate class="shrink-0 self-start">
                                        <img src="{{ $line->variant->getFirstMediaUrl('image', 'thumb') ?: $line->variant->product->getFirstMediaUrl('images', 'thumb') }}"
                                             alt="{{ $line->variant->product->getTranslation('name', app()->getLocale()) }} {{ $line->variant->options_label }}"
                                             class="size-20 rounded-[10px] border border-line bg-paper object-cover {{ $line->excluded ? 'opacity-40' : '' }}">
                                    </a>

                                    <div class="ml-1 min-w-0 flex-1">
                                        <a href="{{ route('product.show', $line->variant->product->slug) }}" wire:navigate
                                           class="line-clamp-2 text-sm font-medium hover:underline {{ $line->excluded ? 'text-ink-faint' : '' }}">
                                            {{ $line->variant->product->getTranslation('name', app()->getLocale()) }}
                                        </a>

                                        @if ($line->variant->options_label)
                                            <p class="mt-0.5 truncate text-xs text-ink-soft">{{ $line->variant->options_label }}</p>
                                        @endif

                                        <p class="mt-0.5 text-[13px] text-ink-soft tnum">@price($line->variant->effectivePriceSen())</p>

                                        @if ($line->unavailable)
                                            <x-ui.badge variant="danger" class="mt-1.5">{{ __('No longer available') }}</x-ui.badge>
                                        @elseif ($line->outOfStock)
                                            <x-ui.badge variant="out-of-stock" class="mt-1.5">{{ __('Out of stock') }}</x-ui.badge>
                                        @elseif ($line->adjusted)
                                            <x-ui.badge variant="warn" class="mt-1.5">{{ __('Only :count left — quantity adjusted', ['count' => $line->variant->stock]) }}</x-ui.badge>
                                        @endif

                                        @unless ($line->excluded)
                                            <div class="mt-2 flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
                                                {{-- Qty stepper: outlined pill, − / mono value / + --}}
                                                <div class="flex items-center rounded-full border border-line-strong">
                                                    <button type="button"
                                                            wire:click="updateQty({{ $line->variant->id }}, {{ $line->qty - 1 }})"
                                                            @disabled($line->qty <= 1)
                                                            class="flex size-11 items-center justify-center rounded-full text-ink-soft hover:text-ink disabled:cursor-not-allowed disabled:opacity-50"
                                                            aria-label="{{ __('Decrease quantity') }}">−</button>
                                                    <span class="min-w-8 text-center font-mono text-sm">{{ $line->qty }}</span>
                                                    <button type="button"
                                                            wire:click="updateQty({{ $line->variant->id }}, {{ $line->qty + 1 }})"
                                                            @disabled($line->qty >= $line->variant->stock)
                                                            class="flex size-11 items-center justify-center rounded-full text-ink-soft hover:text-ink disabled:cursor-not-allowed disabled:opacity-50"
                                                            aria-label="{{ __('Increase quantity') }}">+</button>
                                                </div>

                                                <span class="text-sm font-bold tnum">@price($line->lineTotalSen)</span>
                                            </div>
                                        @endunless
                                    </div>

                                    <button type="button" wire:click="removeLine({{ $line->variant->id }})"
                                            wire:loading.attr="disabled" wire:target="removeLine"
                                            class="-mr-2 flex size-11 shrink-0 items-center justify-center self-start rounded-lg text-ink-faint hover:text-danger disabled:cursor-not-allowed disabled:opacity-50"
                                            aria-label="{{ __('Remove') }}">
                                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                                    </button>
                                </li>
                            @endforeach
                        </ul>

                        {{-- Per-seller subtotal (selected lines) --}}
                        <div class="flex items-center justify-between border-t border-line px-4 py-3">
                            <span class="text-[13px] text-ink-soft">{{ __('Subtotal') }}</span>
                            <span class="text-sm font-bold tnum">@price($group->subtotalSen)</span>
                        </div>
                    </x-ui.card>
                @endforeach
            </div>

            {{-- ===== Summary (sticky: right column desktop, bottom mobile) ===== --}}
            <x-ui.card class="sticky bottom-0 z-10 p-4 transition-opacity duration-150 lg:bottom-auto lg:top-24"
                       wire:loading.class="opacity-60"
                       wire:target="updateQty, removeLine, toggleSelected, toggleStoreSelected, toggleAllSelected">
                <h2 class="sr-only">{{ __('Order summary') }}</h2>

                <div class="flex items-center justify-between text-sm">
                    <span class="text-ink-soft">{{ __('Items total') }}</span>
                    <span class="font-bold tnum">@price($itemsTotalSen)</span>
                </div>
                <p class="mt-1 text-[13px] text-ink-soft">{{ __('Shipping calculated at checkout') }}</p>

                <div class="mt-3 flex items-baseline justify-between border-t border-line pt-3">
                    <span class="text-sm font-semibold">{{ __('Total') }}</span>
                    <span class="text-xl font-bold tnum">@price($itemsTotalSen)</span>
                </div>

                <div class="mt-4">
                    @guest
                        <x-ui.button :href="route('login')" class="w-full">{{ __('Checkout') }}</x-ui.button>
                        <p class="mt-2 text-center text-[13px] text-ink-soft">{{ __('Log in to check out') }}</p>
                        <p class="mt-1 text-center text-[13px] text-ink-faint">{{ __('Your cart is saved in this browser — log in to keep it everywhere.') }}</p>
                    @else
                        @if (! Route::has('checkout'))
                            <x-ui.button disabled title="{{ __('Coming soon') }}" class="w-full">{{ __('Checkout') }}</x-ui.button>
                        @elseif ($selectedCount === 0)
                            <x-ui.button disabled class="w-full">{{ __('Checkout') }}</x-ui.button>
                        @else
                            <x-ui.button :href="url('/checkout')" class="w-full">{{ __('Checkout') }}</x-ui.button>
                        @endif
                    @endguest
                </div>
            </x-ui.card>
        </div>
    @endif
</div>
