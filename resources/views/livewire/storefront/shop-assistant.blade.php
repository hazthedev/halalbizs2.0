<div
    x-data="{ open: @entangle('open') }"
    x-on:keydown.escape.window="open = false"
    x-on:open-concierge.window="open = true"
>
    {{-- Launcher: brass-ringed premium ornament; bottom-left to clear the toast
         stack. Hidden on mobile (sm:flex) so it never overlaps the PDP/checkout
         sticky bottom action bars; desktop/tablet have no bottom bar. --}}
    <button
        type="button"
        x-on:click="open = ! open"
        x-show="! open"
        class="fixed bottom-4 left-4 z-40 hidden items-center gap-2 rounded-full border border-brass/40 bg-ink py-2.5 pl-2.5 pr-4 text-paper shadow-pop transition-transform hover:-translate-y-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-brass sm:flex"
        aria-label="{{ __('Open shopping assistant') }}"
    >
        <span class="flex size-8 items-center justify-center rounded-full bg-brass/15 text-brass">
            <x-ui.star-mark :size="18" />
        </span>
        <span class="text-[13px] font-semibold">{{ __('Ask the concierge') }}</span>
    </button>

    {{-- Panel --}}
    <div
        x-show="open"
        x-cloak
        x-transition.origin.bottom.left
        class="fixed bottom-4 left-4 z-50 flex h-[min(34rem,80vh)] w-[min(24rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-[var(--radius-card)] border border-brass/25 bg-surface shadow-pop"
        role="dialog"
        aria-label="{{ __('Shopping concierge') }}"
    >
        {{-- Header --}}
        <div class="surface-girih flex items-center gap-2.5 border-b border-brass/25 bg-ink px-4 py-3 text-paper">
            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brass/15 text-brass">
                <x-ui.star-mark :size="18" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold">{{ __('Shopping concierge') }}</p>
                <p class="truncate text-[11px] text-paper/64">{{ __('Find products in English or Bahasa Melayu') }}</p>
            </div>
            @if ($history !== [])
                <button type="button" wire:click="clearChat" class="rounded-lg px-2 py-1 text-[11px] font-medium text-paper/64 hover:text-paper" aria-label="{{ __('Clear conversation') }}">
                    {{ __('Clear') }}
                </button>
            @endif
            <button type="button" x-on:click="open = false" class="flex size-8 items-center justify-center rounded-lg text-paper/64 hover:text-paper" aria-label="{{ __('Close') }}">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Transcript --}}
        <div class="flex-1 space-y-3 overflow-y-auto px-3 py-3" x-ref="log" x-init="$watch('$wire.history', () => $nextTick(() => $refs.log.scrollTop = $refs.log.scrollHeight))">
            @if ($history === [])
                <div class="rounded-[var(--radius-card)] border border-line bg-paper px-3.5 py-3 text-[13px] text-ink-soft">
                    <p class="font-medium text-ink">{{ __('Salam! How can I help you shop today?') }}</p>
                    <p class="mt-1">{{ __('Try: “a gift under RM50” or “tudung bawal terlaris”.') }}</p>
                </div>
            @endif

            @foreach ($history as $turn)
                @if ($turn['role'] === 'user')
                    <div class="flex justify-end">
                        <p class="max-w-[85%] rounded-[var(--radius-card)] rounded-br-sm bg-emerald px-3.5 py-2 text-[13px] text-white">{{ $turn['content'] }}</p>
                    </div>
                @else
                    <div class="space-y-2">
                        @if (trim($turn['content']) !== '')
                            <p class="max-w-[90%] rounded-[var(--radius-card)] rounded-bl-sm border border-line bg-paper px-3.5 py-2 text-[13px] text-ink">{{ $turn['content'] }}</p>
                        @endif

                        @php($cards = collect($turn['products'] ?? [])->map(fn ($id) => $products->get((int) $id))->filter())
                        @if ($cards->isNotEmpty())
                            <div class="space-y-1.5">
                                @foreach ($cards as $product)
                                    @php($minSen = $product->variants->isNotEmpty() ? $product->variants->map->effectivePriceSen()->min() : 0)
                                    @php($thumb = $product->getFirstMediaUrl('images', 'thumb'))
                                    <a href="{{ route('product.show', $product->slug) }}" wire:navigate
                                       class="flex items-center gap-2.5 rounded-[var(--radius-card)] border border-line bg-surface p-1.5 transition-colors hover:border-line-strong hover:bg-paper">
                                        <span class="size-12 shrink-0 overflow-hidden rounded-lg bg-paper">
                                            @if ($thumb)<img src="{{ $thumb }}" alt="" class="size-full object-cover" loading="lazy">@endif
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="line-clamp-1 text-[13px] font-medium text-ink">{{ $product->getTranslation('name', app()->getLocale()) }}</span>
                                            <span class="mt-0.5 flex items-center gap-1.5 text-xs text-ink-soft">
                                                <span class="font-bold text-ink tnum">@price($minSen)</span>
                                                @if ($product->rating_count > 0)
                                                    <span aria-hidden="true">·</span><span>★ {{ number_format((float) $product->rating_avg, 1) }}</span>
                                                @endif
                                            </span>
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            @endforeach

            {{-- Thinking indicator while a turn is in flight --}}
            <div wire:loading wire:target="send" class="flex items-center gap-1.5 px-1 text-ink-faint">
                <span class="size-1.5 animate-bounce rounded-full bg-brass [animation-delay:-0.2s]"></span>
                <span class="size-1.5 animate-bounce rounded-full bg-brass [animation-delay:-0.1s]"></span>
                <span class="size-1.5 animate-bounce rounded-full bg-brass"></span>
            </div>
        </div>

        {{-- Composer --}}
        <form wire:submit="send" class="flex items-end gap-2 border-t border-line bg-surface px-3 py-2.5">
            <textarea
                wire:model="draft"
                x-on:keydown.enter.prevent="$wire.send()"
                rows="1"
                maxlength="500"
                placeholder="{{ __('Ask about products…') }}"
                class="max-h-24 min-h-10 w-full resize-none rounded-[var(--radius-control)] border border-line bg-paper px-3 py-2 text-[13px] text-ink placeholder:text-ink-faint focus:border-emerald focus:outline-none focus:ring-1 focus:ring-emerald"
            ></textarea>
            <button
                type="submit"
                wire:loading.attr="disabled" wire:target="send"
                class="flex size-10 shrink-0 items-center justify-center rounded-[var(--radius-control)] bg-emerald text-white transition-colors hover:bg-emerald-deep disabled:opacity-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-1"
                aria-label="{{ __('Send') }}"
            >
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
            </button>
        </form>
    </div>
</div>
