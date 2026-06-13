<div class="pb-12 sm:pb-16">
    {{-- ===== Occasion hero (ThemeSettings + ThemeAsset 'hero') ===== --}}
    @if ($heroUrl)
        <section class="relative h-[280px] w-full overflow-hidden bg-ink" aria-label="{{ $occasion !== '' ? $occasion : __('Seasonal highlight') }}">
            <img src="{{ $heroUrl }}" alt="{{ $occasion !== '' ? $occasion : __('Seasonal highlight') }}" class="absolute inset-0 size-full object-cover">
            <div class="absolute inset-0 bg-ink/40"></div>
            @if ($occasion !== '')
                <div class="relative mx-auto flex h-full max-w-7xl items-end px-4 pb-8">
                    <h1 class="font-display text-3xl font-bold text-paper sm:text-4xl">{{ $occasion }}</h1>
                </div>
            @endif
        </section>
    @endif

    @foreach ($sections as $row)
        @php
            /** @var \App\Models\HomeSection $section */
            $section = $row['section'];
            $data = $row['data'];
            $title = $section->getTranslation('title', app()->getLocale());
        @endphp

        @switch($section->type)
            {{-- ===== Banner carousel (Swiper — swipe + arrows, no autoplay) ===== --}}
            @case('banner')
                <section class="mx-auto max-w-7xl px-4 pt-6 sm:pt-8" aria-label="{{ __('Promotions') }}" wire:key="section-{{ $section->id }}">
                    <div
                        wire:ignore
                        x-data
                        x-init="new window.Swiper($refs.container, {
                            modules: Object.values(window.SwiperModules),
                            slidesPerView: 1,
                            navigation: { prevEl: $refs.prev, nextEl: $refs.next },
                            pagination: { el: $refs.pagination, clickable: true },
                        })"
                        class="relative"
                    >
                        <div
                            class="swiper overflow-hidden rounded-[10px] border border-line"
                            x-ref="container"
                            style="--swiper-pagination-color: var(--color-paper); --swiper-pagination-bullet-inactive-color: var(--color-paper); --swiper-pagination-bullet-inactive-opacity: 0.5;"
                        >
                            <div class="swiper-wrapper">
                                @foreach ($data as $banner)
                                    @php
                                        $bannerTitle = $banner->getTranslation('title', app()->getLocale());
                                        $bannerVideo = $banner->getFirstMediaUrl('video');
                                    @endphp
                                    <div class="swiper-slide">
                                        @if ($banner->link_url)
                                            <a href="{{ $banner->link_url }}" @if (str_starts_with($banner->link_url, '/')) wire:navigate @endif>
                                        @endif
                                            @if ($bannerVideo)
                                                <video autoplay muted loop playsinline
                                                       src="{{ $bannerVideo }}"
                                                       poster="{{ $banner->getFirstMediaUrl('image', 'card') }}"
                                                       aria-label="{{ $bannerTitle }}"
                                                       class="aspect-[3/1] w-full bg-paper object-cover"></video>
                                            @else
                                                <img src="{{ $banner->getFirstMediaUrl('image', 'card') }}" alt="{{ $bannerTitle }}"
                                                     class="aspect-[3/1] w-full bg-paper object-cover" @if (! $loop->first) loading="lazy" @endif>
                                            @endif
                                        @if ($banner->link_url)
                                            </a>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            <div x-ref="pagination" class="swiper-pagination"></div>
                        </div>

                        @if ($data->count() > 1)
                            <button type="button" x-ref="prev"
                                    class="absolute left-3 top-1/2 z-10 flex size-11 -translate-y-1/2 items-center justify-center rounded-full border border-line bg-surface/90 text-ink transition-colors hover:bg-surface disabled:opacity-40"
                                    aria-label="{{ __('Previous banner') }}">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
                            </button>
                            <button type="button" x-ref="next"
                                    class="absolute right-3 top-1/2 z-10 flex size-11 -translate-y-1/2 items-center justify-center rounded-full border border-line bg-surface/90 text-ink transition-colors hover:bg-surface disabled:opacity-40"
                                    aria-label="{{ __('Next banner') }}">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                            </button>
                        @endif
                    </div>
                </section>
                @break

            {{-- ===== Category grid (2×4 mobile → 8 cols desktop) ===== --}}
            @case('category_grid')
                <section class="mx-auto max-w-7xl px-4 pt-12 sm:pt-16" wire:key="section-{{ $section->id }}">
                    @if ($title)
                        <h2 class="font-display text-2xl font-bold">{{ $title }}</h2>
                    @endif
                    <div class="mt-4 grid grid-cols-4 gap-3 sm:mt-6 sm:gap-4 lg:grid-cols-8">
                        @foreach ($data as $category)
                            @php $categoryName = $category->getTranslation('name', app()->getLocale()); @endphp
                            <a href="{{ route('category.show', $category->slug) }}" wire:navigate
                               wire:key="category-{{ $category->id }}"
                               class="group flex flex-col items-center gap-2 rounded-[10px] border border-line bg-surface p-3 transition-colors duration-150 hover:border-ink">
                                <span class="block aspect-square w-full overflow-hidden rounded-lg bg-paper">
                                    @if ($categoryImage = $category->getFirstMediaUrl('image', 'thumb'))
                                        <img src="{{ $categoryImage }}" alt="{{ $categoryName }}"
                                             class="size-full object-cover transition-transform duration-150 group-hover:scale-[1.02]" loading="lazy">
                                    @endif
                                </span>
                                <span class="line-clamp-2 text-center text-[13px] font-medium leading-snug text-ink">{{ $categoryName }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
                @break

            {{-- ===== Product carousel (horizontal scroll strip) ===== --}}
            @case('product_carousel')
                <section class="mx-auto max-w-7xl px-4 pt-12 sm:pt-16" wire:key="section-{{ $section->id }}">
                    <div class="flex items-baseline justify-between gap-4">
                        @if ($title)
                            <h2 class="font-display text-2xl font-bold">{{ $title }}</h2>
                        @endif
                        <a href="{{ route('search') }}" wire:navigate
                           class="inline-flex min-h-11 shrink-0 items-center gap-1 text-sm font-medium text-ink-soft transition-colors hover:text-ink">
                            {{ __('View all') }}
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                        </a>
                    </div>
                    <div class="-mx-4 mt-4 flex snap-x gap-3 overflow-x-auto px-4 pb-2 sm:mt-6">
                        @foreach ($data as $product)
                            <div class="w-40 shrink-0 snap-start sm:w-48" wire:key="carousel-{{ $section->id }}-{{ $product->id }}">
                                <x-product-card :product="$product" :wishlisted="in_array($product->id, $wishlistedIds)" :sponsored="(bool) ($product->sponsored ?? false)" />
                            </div>
                        @endforeach
                    </div>
                </section>
                @break

            {{-- ===== Product grid (2/3/4/6 cols) ===== --}}
            @case('product_grid')
                <section class="mx-auto max-w-7xl px-4 pt-12 sm:pt-16" wire:key="section-{{ $section->id }}">
                    <div class="flex items-baseline justify-between gap-4">
                        @if ($title)
                            <h2 class="font-display text-2xl font-bold">{{ $title }}</h2>
                        @endif
                        <a href="{{ route('search') }}" wire:navigate
                           class="inline-flex min-h-11 shrink-0 items-center gap-1 text-sm font-medium text-ink-soft transition-colors hover:text-ink">
                            {{ __('View all') }}
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                        </a>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-3 sm:mt-6 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-6">
                        @foreach ($data as $product)
                            <div wire:key="grid-{{ $section->id }}-{{ $product->id }}">
                                <x-product-card :product="$product" :wishlisted="in_array($product->id, $wishlistedIds)" :sponsored="(bool) ($product->sponsored ?? false)" />
                            </div>
                        @endforeach
                    </div>
                </section>
                @break

            {{-- ===== Recently viewed (hydrated from localStorage, hidden when empty) ===== --}}
            @case('recently_viewed')
                <div wire:key="section-{{ $section->id }}"
                     x-data
                     x-init="const ids = window.recentlyViewed?.all() ?? []; if (ids.length) { $wire.loadRecentlyViewed(ids) }">
                    @if ($data->isNotEmpty())
                        <section class="mx-auto max-w-7xl px-4 pt-12 sm:pt-16">
                            @if ($title)
                                <h2 class="font-display text-2xl font-bold">{{ $title }}</h2>
                            @endif
                            <div class="-mx-4 mt-4 flex snap-x gap-3 overflow-x-auto px-4 pb-2 sm:mt-6">
                                @foreach ($data as $product)
                                    <div class="w-40 shrink-0 snap-start sm:w-48" wire:key="recent-{{ $product->id }}">
                                        <x-product-card :product="$product" :wishlisted="in_array($product->id, $wishlistedIds)" />
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif
                </div>
                @break
        @endswitch
    @endforeach

    {{-- One-time welcome tour for first-time visitors (home only) --}}
    @include('livewire.storefront.partials.welcome-tour')
</div>
