<div
    x-data="{
        open: false,
        focusIndex: -1,
        openOverlay() { this.open = true; this.$nextTick(() => this.$refs.searchInput.focus()); },
        close() { this.open = false; this.focusIndex = -1; },
        go() {
            const term = $wire.query.trim();
            if (!term) return;
            window.recentSearches.push(term);
            this.close();
            Livewire.navigate('{{ url('/search') }}?q=' + encodeURIComponent(term));
        },
    }"
    x-on:open-search.window="openOverlay()"
    x-on:keydown.window.prevent.slash="if (!open && !['INPUT','TEXTAREA'].includes(document.activeElement.tagName)) openOverlay()"
    x-on:keydown.escape.window="close()"
>
    <div x-show="open" x-cloak
         x-transition:enter="transition-opacity duration-(--dur-standard) ease-standard" x-transition:enter-start="opacity-0"
         x-transition:leave="transition-opacity duration-200 ease-in-soft" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 bg-ink/50 p-4 pt-[10vh]" x-on:click.self="close()">
        {{-- Dialog slides 16px down from its edge + fades, faster exit --}}
        <div x-show="open"
             x-transition:enter="transition duration-(--dur-standard) ease-out-soft" x-transition:enter-start="-translate-y-4 opacity-0"
             x-transition:leave="transition duration-200 ease-in-soft" x-transition:leave-end="-translate-y-4 opacity-0"
             class="mx-auto w-full max-w-2xl overflow-hidden rounded-[var(--radius-card)] bg-surface shadow-pop" role="dialog" aria-label="{{ __('Search') }}">
            {{-- Input row --}}
            <div class="flex items-center gap-3 border-b border-line px-4">
                <svg class="size-5 shrink-0 text-ink-faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                <input
                    x-ref="searchInput"
                    type="search"
                    maxlength="100"
                    wire:model.live.debounce.300ms="query"
                    x-on:keydown.enter="go()"
                    placeholder="{{ __('Search products, stores, categories…') }}"
                    class="h-14 w-full border-0 bg-transparent text-base text-ink placeholder:text-ink-faint focus:outline-none focus:ring-0"
                >
                <button type="button" x-on:click="close()" class="text-[13px] font-medium text-ink-soft hover:text-ink">{{ __('Cancel') }}</button>
            </div>

            <div class="max-h-[60vh] overflow-y-auto p-2">
                @if (mb_strlen(trim($query)) < 2)
                    {{-- Empty state: recent + trending --}}
                    <div class="p-3" x-data="{ recents: [] }" x-init="recents = window.recentSearches.all()">
                        <template x-if="recents.length">
                            <div class="mb-4">
                                <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.04em] text-ink-faint">{{ __('Recent searches') }}</p>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="term in recents" :key="term">
                                        <button type="button" x-on:click="$wire.set('query', term)"
                                                class="rounded-full border border-line px-3 py-1.5 text-[13px] text-ink-soft hover:border-ink hover:text-ink" x-text="term"></button>
                                    </template>
                                </div>
                            </div>
                        </template>

                        @if ($trending->isNotEmpty())
                            <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.04em] text-ink-faint">{{ __('Trending') }}</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($trending as $term)
                                    <button type="button" wire:click="$set('query', '{{ $term }}')"
                                            class="rounded-full border border-line px-3 py-1.5 text-[13px] text-ink-soft hover:border-ink hover:text-ink">{{ $term }}</button>
                                @endforeach
                            </div>
                        @endif

                        @if (config('search.enabled', true))
                            <a href="{{ route('search.visual') }}" wire:navigate x-on:click="close()"
                               class="mt-4 inline-flex items-center gap-2 rounded-full border border-line px-3 py-1.5 text-[13px] font-semibold text-ink-soft hover:border-ink hover:text-ink">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 19.5h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Z"/></svg>
                                {{ __('Search by image') }}
                            </a>
                        @endif
                    </div>
                @else
                    {{-- Grouped results --}}
                    <div wire:loading.class="opacity-60">
                        @if ($products->isEmpty() && $stores->isEmpty() && $categories->isEmpty())
                            <p class="p-6 text-center text-sm text-ink-soft">{{ __('No results for ":term" — press Enter to search anyway.', ['term' => trim($query)]) }}</p>
                        @endif

                        @if ($products->isNotEmpty())
                            <p class="px-3 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-[0.04em] text-ink-faint">{{ __('Products') }}</p>
                            @foreach ($products as $product)
                                <a href="{{ route('product.show', $product->slug) }}" wire:navigate x-on:click="close()"
                                   class="flex items-center gap-3 rounded-[var(--radius-control)] px-3 py-2 hover:bg-paper">
                                    <img src="{{ $product->getFirstMediaUrl('images', 'thumb') }}" alt="" class="size-10 rounded-[var(--radius-control)] border border-line object-cover bg-paper">
                                    <span class="line-clamp-1 flex-1 text-sm">{{ $product->getTranslation('name', app()->getLocale()) }}</span>
                                    <span class="shrink-0 text-[13px] font-bold tnum">@price($product->variants->map->effectivePriceSen()->min() ?? 0)</span>
                                </a>
                            @endforeach
                        @endif

                        @if ($stores->isNotEmpty())
                            <p class="px-3 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-[0.04em] text-ink-faint">{{ __('Stores') }}</p>
                            @foreach ($stores as $store)
                                <a href="{{ $store->storefrontUrl() }}" wire:navigate x-on:click="close()"
                                   class="flex items-center gap-3 rounded-[var(--radius-control)] px-3 py-2 hover:bg-paper">
                                    <img src="{{ $store->getFirstMediaUrl('logo') }}" alt="" class="size-8 rounded-full border border-line object-cover bg-paper">
                                    <span class="flex-1 text-sm">{{ $store->name }}</span>
                                    @if ($store->rating_count > 0)
                                        <span class="text-xs text-ink-soft">★ {{ number_format((float) $store->rating_avg, 1) }}</span>
                                    @endif
                                </a>
                            @endforeach
                        @endif

                        @if ($categories->isNotEmpty())
                            <p class="px-3 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-[0.04em] text-ink-faint">{{ __('Categories') }}</p>
                            @foreach ($categories as $category)
                                <a href="{{ route('category.show', $category->slug) }}" wire:navigate x-on:click="close()"
                                   class="flex items-center gap-3 rounded-[var(--radius-control)] px-3 py-2 text-sm hover:bg-paper">
                                    {{ $category->getTranslation('name', app()->getLocale()) }}
                                </a>
                            @endforeach
                        @endif

                        <button type="button" x-on:click="go()" class="mt-1 flex w-full items-center gap-2 rounded-[var(--radius-control)] px-3 py-2.5 text-sm font-semibold text-emerald hover:bg-emerald-tint">
                            {{ __('See all results for ":term"', ['term' => trim($query)]) }} →
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
