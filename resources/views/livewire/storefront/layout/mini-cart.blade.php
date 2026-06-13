<div
    x-data="{ open: false }"
    x-on:open-mini-cart.window="open = true"
    x-on:keydown.escape.window="open = false"
>
    {{-- Backdrop --}}
    <div x-show="open" x-cloak x-transition.opacity.duration.150ms
         class="fixed inset-0 z-40 bg-ink/40" x-on:click="open = false"></div>

    {{-- Slide-over --}}
    <aside x-show="open" x-cloak
           x-transition:enter="transition-transform duration-300 ease-out" x-transition:enter-start="translate-x-full"
           x-transition:leave="transition-transform duration-200 ease-in" x-transition:leave-end="translate-x-full"
           class="fixed inset-y-0 right-0 z-50 flex w-full max-w-sm flex-col bg-surface shadow-xl"
           role="dialog" aria-label="{{ __('Cart') }}">

        <div class="flex items-center justify-between border-b border-line px-4 py-3.5">
            <h2 class="font-display text-lg font-bold">{{ __('Cart') }}</h2>
            <button type="button" x-on:click="open = false" class="flex size-9 items-center justify-center rounded-lg text-ink-soft hover:text-ink" aria-label="{{ __('Close') }}">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-4 py-3">
            @forelse ($groups as $storeId => $lines)
                <div class="mb-4">
                    <a href="{{ $lines->first()->variant->product->store->storefrontUrl() }}" wire:navigate
                       class="mb-2 block text-[13px] font-semibold text-ink">
                        {{ $lines->first()->variant->product->store->name }}
                    </a>
                    <ul class="space-y-3">
                        @foreach ($lines as $line)
                            <li class="flex gap-3" wire:key="mini-line-{{ $line->variant->id }}">
                                <img src="{{ $line->variant->getFirstMediaUrl('image', 'thumb') ?: $line->variant->product->getFirstMediaUrl('images', 'thumb') }}"
                                     alt="" class="size-16 shrink-0 rounded-lg border border-line object-cover bg-paper">
                                <div class="min-w-0 flex-1">
                                    <p class="line-clamp-1 text-[13px] font-medium">{{ $line->variant->product->getTranslation('name', app()->getLocale()) }}</p>
                                    @if ($line->variant->options_label)
                                        <p class="text-xs text-ink-soft">{{ $line->variant->options_label }}</p>
                                    @endif
                                    <div class="mt-1 flex items-center justify-between">
                                        <div class="flex items-center rounded-full border border-line-strong">
                                            <button type="button" wire:click="updateQty({{ $line->variant->id }}, {{ $line->qty - 1 }})" class="flex size-7 items-center justify-center text-ink-soft hover:text-ink" aria-label="{{ __('Decrease') }}">−</button>
                                            <span class="min-w-6 text-center font-mono text-[13px]">{{ $line->qty }}</span>
                                            <button type="button" wire:click="updateQty({{ $line->variant->id }}, {{ $line->qty + 1 }})" class="flex size-7 items-center justify-center text-ink-soft hover:text-ink" aria-label="{{ __('Increase') }}" @disabled($line->qty >= $line->variant->stock)>+</button>
                                        </div>
                                        <span class="text-[13px] font-bold tnum">@price($line->variant->effectivePriceSen() * $line->qty)</span>
                                    </div>
                                </div>
                                <button type="button" wire:click="removeLine({{ $line->variant->id }})" class="self-start text-ink-faint hover:text-danger" aria-label="{{ __('Remove') }}">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <div class="flex h-full flex-col items-center justify-center text-center">
                    <p class="font-display text-lg font-semibold">{{ __('Your cart is empty') }}</p>
                    <p class="mt-1 text-sm text-ink-soft">{{ __('Things you add will appear here.') }}</p>
                </div>
            @endforelse
        </div>

        @if ($groups->isNotEmpty())
            <div class="border-t border-line p-4">
                <div class="mb-3 flex items-center justify-between text-sm">
                    <span class="text-ink-soft">{{ __('Items total') }}</span>
                    <span class="font-bold tnum">@price($subtotalSen)</span>
                </div>
                <x-ui.button :href="route('cart')" class="w-full">{{ __('View cart & checkout') }}</x-ui.button>
            </div>
        @endif
    </aside>
</div>
