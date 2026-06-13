<div class="mx-auto max-w-7xl px-4 py-6" x-data="{ filtersOpen: false }" x-on:keydown.escape.window="filtersOpen = false">

    {{-- Breadcrumbs (category entry only) --}}
    @if (! $isSearch && count($breadcrumbs) > 0)
        <nav aria-label="{{ __('Breadcrumb') }}" class="mb-4">
            <ol class="flex flex-wrap items-center gap-1.5 text-[13px] text-ink-soft">
                <li>
                    <a href="{{ route('home') }}" wire:navigate class="hover:text-ink">{{ __('Home') }}</a>
                </li>
                @foreach ($breadcrumbs as $crumb)
                    <li aria-hidden="true" class="text-ink-faint">/</li>
                    <li>
                        @if ($loop->last)
                            <span aria-current="page" class="font-medium text-ink">{{ $crumb->getTranslation('name', app()->getLocale()) }}</span>
                        @else
                            <a href="{{ route('category.show', $crumb->slug) }}" wire:navigate class="hover:text-ink">{{ $crumb->getTranslation('name', app()->getLocale()) }}</a>
                        @endif
                    </li>
                @endforeach
            </ol>
        </nav>
    @endif

    {{-- Title row: heading + count left, sort right --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <x-ui.section-heading as="h1" :title="$isSearch ? (trim($q) === '' ? __('Search') : __('Search: :term', ['term' => trim($q)])) : $rootCategory->getTranslation('name', app()->getLocale())" />
            <p class="mt-1 text-[13px] text-ink-soft tnum">{{ trans_choice(':count product|:count products', $total, ['count' => $total]) }}</p>
        </div>

        <div class="ml-auto">
            <label for="listing-sort" class="sr-only">{{ __('Sort by') }}</label>
            <select
                id="listing-sort"
                wire:change="$set('sort', $event.target.value)"
                class="min-h-11 cursor-pointer rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-sm text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald"
            >
                @if ($isSearch && trim($q) !== '')
                    <option value="relevance" @selected($effectiveSort === 'relevance')>{{ __('Relevance') }}</option>
                @endif
                <option value="latest" @selected($effectiveSort === 'latest')>{{ __('Latest') }}</option>
                <option value="top" @selected($effectiveSort === 'top')>{{ __('Top sales') }}</option>
                <option value="price_asc" @selected($effectiveSort === 'price_asc')>{{ __('Price: low to high') }}</option>
                <option value="price_desc" @selected($effectiveSort === 'price_desc')>{{ __('Price: high to low') }}</option>
            </select>
        </div>
    </div>

    <div class="mt-6 flex items-start gap-8">
        {{-- Desktop sidebar filters (sticky) --}}
        <aside class="hidden w-60 shrink-0 lg:block" aria-label="{{ __('Filters') }}">
            <div class="sticky top-20 max-h-[calc(100vh-6rem)] space-y-6 overflow-y-auto pb-4 pr-1">
                @include('livewire.storefront.listing.filters', ['idPrefix' => 'desktop'])
            </div>
        </aside>

        <div class="min-w-0 flex-1">
            {{-- Mobile filters trigger --}}
            <button
                type="button"
                x-on:click="filtersOpen = true"
                class="mb-4 flex min-h-11 w-full items-center justify-center gap-2 rounded-[var(--radius-control)] border border-line-strong bg-surface px-4 text-sm font-semibold text-ink lg:hidden"
            >
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z"/></svg>
                {{ __('Filters') }}@if (count($chips) > 0)<span class="tnum">({{ count($chips) }})</span>@endif
            </button>

            {{-- Applied filter chips --}}
            @if (count($chips) > 0)
                <div class="mb-4 flex flex-wrap items-center gap-2">
                    @foreach ($chips as $filter => $label)
                        <button
                            type="button"
                            wire:click="removeFilter('{{ $filter }}')"
                            class="inline-flex min-h-11 items-center gap-1.5 rounded-full border border-line-strong bg-surface px-4 text-[13px] font-medium text-ink hover:border-ink"
                            aria-label="{{ __('Remove filter: :label', ['label' => $label]) }}"
                        >
                            {{ $label }}
                            <svg class="size-3.5 text-ink-soft" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                        </button>
                    @endforeach
                    <button type="button" wire:click="clearFilters" class="min-h-11 px-2 text-[13px] font-medium text-ink-soft hover:text-ink">
                        {{ __('Clear all') }}
                    </button>
                </div>
            @endif

            {{-- Results region --}}
            <div wire:loading.class="pointer-events-none opacity-60" class="transition-opacity duration-150">
                @if ($products->isEmpty())
                    {{-- Empty state (design §6): one display line + one sentence + single emerald action --}}
                    @php
                        $emptyTitle = $isSearch && trim($q) !== '' ? __('No results for ":term".', ['term' => trim($q)]) : __('Nothing here yet.');
                        $emptyMessage = count($chips) > 0
                            ? __('No products match these filters — loosen them and try again.')
                            : ($isSearch ? __('Check the spelling or try a more general term.') : __('Products will appear here as soon as sellers list them.'));
                    @endphp
                    <x-ui.empty-state :title="$emptyTitle" :message="$emptyMessage">
                        @if (count($chips) > 0)
                            <x-ui.button variant="primary" wire:click="clearFilters">{{ __('Clear filters') }}</x-ui.button>
                        @else
                            <x-ui.button variant="primary" :href="route('home')">{{ __('Back to home') }}</x-ui.button>
                        @endif
                    </x-ui.empty-state>
                @else
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-6">
                        @foreach ($products as $product)
                            <div wire:key="product-{{ $product->id }}">
                                <x-product-card
                                    :product="$product"
                                    :wishlisted="in_array($product->id, $wishlistedIds, true)"
                                    :sponsored="(bool) ($product->sponsored ?? false)"
                                />
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Skeleton cards for the incoming page (no layout shift on existing cards) --}}
                <div wire:loading.grid wire:target="loadMore" class="mt-3 hidden grid-cols-2 gap-3 sm:mt-4 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-6">
                    @for ($i = 0; $i < 6; $i++)
                        <div class="overflow-hidden rounded-[var(--radius-card)] border border-line bg-surface">
                            <x-ui.skeleton class="aspect-square w-full rounded-none" />
                            <div class="space-y-2 p-3">
                                <x-ui.skeleton class="h-4 w-full" />
                                <x-ui.skeleton class="h-4 w-2/3" />
                                <x-ui.skeleton class="h-3 w-1/2" />
                            </div>
                        </div>
                    @endfor
                </div>
            </div>

            {{-- Load more: button fallback, auto-triggered by IntersectionObserver --}}
            @if ($hasMore)
                <div
                    wire:key="load-more-sentinel"
                    x-data
                    x-init="
                        const io = new IntersectionObserver((entries) => {
                            if (entries[0].isIntersecting) {
                                $wire.loadMore().then(() => { io.unobserve($el); io.observe($el); });
                            }
                        }, { rootMargin: '400px 0px' });
                        io.observe($el);
                    "
                    class="mt-8 flex justify-center"
                >
                    <x-ui.button variant="secondary" wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore">
                        {{ __('Load more') }}
                    </x-ui.button>
                </div>
            @endif
        </div>
    </div>

    {{-- Mobile filters bottom sheet (overlay — shadow permitted) --}}
    <div x-show="filtersOpen" x-cloak class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="{{ __('Filters') }}">
        <div x-show="filtersOpen" x-transition.opacity.duration.150ms class="absolute inset-0 bg-ink/50" x-on:click="filtersOpen = false"></div>
        <div
            x-show="filtersOpen"
            x-transition:enter="transition duration-150 ease-out"
            x-transition:enter-start="translate-y-6 opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            x-transition:leave="transition duration-100 ease-in"
            x-transition:leave-end="translate-y-6 opacity-0"
            class="absolute inset-x-0 bottom-0 flex max-h-[85vh] flex-col rounded-t-[var(--radius-card)] bg-surface shadow-pop"
        >
            <div class="flex items-center justify-between border-b border-line px-4 py-3">
                <p class="font-display text-lg font-bold">{{ __('Filters') }}</p>
                <button type="button" x-on:click="filtersOpen = false" class="flex size-11 items-center justify-center rounded-[var(--radius-control)] text-ink-soft hover:text-ink" aria-label="{{ __('Close filters') }}">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="min-h-0 flex-1 space-y-6 overflow-y-auto p-4">
                @include('livewire.storefront.listing.filters', ['idPrefix' => 'mobile'])
            </div>
            <div class="border-t border-line p-4">
                <x-ui.button variant="primary" class="w-full" x-on:click="filtersOpen = false">
                    {{ trans_choice('Show :count result|Show :count results', $total, ['count' => $total]) }}
                </x-ui.button>
            </div>
        </div>
    </div>
</div>
