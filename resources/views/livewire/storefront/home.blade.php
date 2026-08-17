<div class="pb-12 sm:pb-16">
    {{-- ===== Occasion hero (ThemeSettings + ThemeAsset 'hero') ===== --}}
    @if ($heroUrl)
        <section class="relative h-[280px] w-full overflow-hidden bg-emerald-night" aria-label="{{ $occasion !== '' ? $occasion : __('Seasonal highlight') }}">
            <img src="{{ $heroUrl }}" alt="{{ $occasion !== '' ? $occasion : __('Seasonal highlight') }}" class="absolute inset-0 size-full object-cover">
            <div class="surface-zellij absolute inset-0 opacity-40"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-ink/85 via-ink/35 to-ink/10"></div>
            @if ($occasion !== '')
                <div class="relative mx-auto flex h-full max-w-7xl items-end px-4 pb-8">
                    <h1 class="reveal flex items-center gap-3 font-display text-3xl font-medium text-on-dark sm:text-4xl">
                        <x-ui.star-mark :size="28" class="text-brass" />
                        {{ $occasion }}
                    </h1>
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
            {{-- ===== Banner carousel (Swiper — quiet 6s crossfade; swipe + arrows kept) ===== --}}
            @case('banner')
                {{-- Full-bleed: no max-w, no side padding, no frame. The banner is the
                     image itself, edge to edge, so nothing competes with it. --}}
                <section class="w-full pt-6 sm:pt-8" aria-label="{{ __('Promotions') }}" wire:key="section-{{ $section->id }}">
                    <div
                        wire:ignore
                        x-data="{ active: 0 }"
                        x-init="const scope = $data; new window.Swiper($refs.container, {
                            modules: Object.values(window.SwiperModules),
                            slidesPerView: 1,
                            @if ($data->count() > 1)
                            loop: true,
                            effect: 'fade',
                            fadeEffect: { crossFade: true },
                            speed: 600,
                            {{-- reduced motion: no auto-advance at all --}}
                            autoplay: window.matchMedia('(prefers-reduced-motion: reduce)').matches
                                ? false
                                : { delay: 6000, pauseOnMouseEnter: true, disableOnInteraction: false },
                            @endif
                            navigation: { prevEl: $refs.prev, nextEl: $refs.next },
                            pagination: { el: $refs.pagination, clickable: true },
                            {{-- realIndex, not activeIndex: loop mode clones slides. --}}
                            on: { slideChange(sw) { scope.active = sw.realIndex } },
                        })"
                        {{-- autoplay also pauses while focus is inside (keyboard users) --}}
                        x-on:focusin="$refs.container.swiper?.autoplay?.stop()"
                        x-on:focusout="if ($refs.container.swiper?.params.autoplay?.enabled) $refs.container.swiper.autoplay.start()"
                        class="relative"
                    >
                        <div
                            class="swiper overflow-hidden"
                            x-ref="container"
                            style="--swiper-pagination-color: var(--color-paper); --swiper-pagination-bullet-inactive-color: var(--color-paper); --swiper-pagination-bullet-inactive-opacity: 0.5;"
                        >
                            <div class="swiper-wrapper">
                                @foreach ($data as $banner)
                                    @php
                                        $bannerTitle = $banner->getTranslation('title', app()->getLocale());
                                        $bannerSubtitle = trim((string) $banner->getTranslation('subtitle', app()->getLocale()));
                                        $bannerCta = trim((string) $banner->getTranslation('cta_label', app()->getLocale()));
                                        // Motion assets may also contain baked English copy. Until
                                        // localized video collections exist, Vietnamese uses its
                                        // dedicated still artwork rather than showing the wrong text.
                                        $bannerVideo = app()->getLocale() === 'vi' ? '' : $banner->getFirstMediaUrl('video');
                                        $bannerImage = $banner->imageUrl(app()->getLocale());
                                    @endphp
                                    <div class="swiper-slide">
                                        @if ($banner->link_url)
                                            <a href="{{ $banner->link_url }}" class="relative block" @if (str_starts_with($banner->link_url, '/')) wire:navigate @endif>
                                        @else
                                            <div class="relative">
                                        @endif
                                            @if ($bannerVideo)
                                                <video autoplay muted loop playsinline
                                                       src="{{ $bannerVideo }}"
                                                       poster="{{ $bannerImage }}"
                                                       aria-label="{{ $bannerTitle }}"
                                                       class="aspect-[3/1] w-full bg-paper object-cover"></video>
                                            @else
                                                {{-- The copy is composited INTO the artwork now, so alt is the
                                                     only route a screen reader has to it. --}}
                                                <img src="{{ $bannerImage }}" alt="{{ $bannerTitle }}{{ $bannerSubtitle !== '' ? '. '.$bannerSubtitle : '' }}"
                                                     class="aspect-[3/1] w-full bg-paper object-cover" @if (! $loop->first) loading="lazy" @endif>
                                            @endif

                                        @if ($banner->link_url)
                                            </a>
                                        @else
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            <div x-ref="pagination" class="swiper-pagination !bottom-3"></div>
                        </div>

                        {{-- MOBILE COPY, deliberately OUTSIDE the swiper.
                             effect:'fade' stacks every slide and crossfades their
                             opacity — so copy living inside a slide fades with it.
                             Measured mid-transition at 414px: two slides at 0.29 and
                             0.71 opacity with all five copy blocks piled at the same
                             y, which reads as the text not showing. Out here it is a
                             single block that simply swaps on slideChange. --}}
                        <div class="sm:hidden">
                            @foreach ($data as $i => $b)
                                @php
                                    $bTitle = $b->getTranslation('title', app()->getLocale());
                                    $bSub = trim((string) $b->getTranslation('subtitle', app()->getLocale()));
                                    $bCta = trim((string) $b->getTranslation('cta_label', app()->getLocale()));
                                @endphp
                                {{-- x-cloak only from the SECOND block on. Since [x-cloak] is now a
                                     real display:none rule, cloaking all five would mean "Alpine did
                                     not run" = "no banner text at all" — a shield, a blocked script or
                                     one JS error and the copy vanishes. The first block paints without
                                     JavaScript and Alpine takes over from there. --}}
                                <div x-show="active === {{ $i }}" @if ($i > 0) x-cloak @endif class="px-4 pt-3">
                                    @if ($bCta !== '' && $b->link_url)
                                        {{-- Only the action lives here now. The headline and its
                                             supporting line are composited into the artwork, so
                                             repeating them underneath would say everything twice. --}}
                                        <a href="{{ $b->link_url }}" @if (str_starts_with($b->link_url, '/')) wire:navigate @endif
                                           class="inline-flex min-h-11 items-center gap-1.5 rounded-[var(--radius-pill)] bg-emerald px-4 text-[length:var(--text-xs)] font-medium text-white">
                                            {{ $bCta }}
                                            <span aria-hidden="true">&rarr;</span>
                                        </a>
                                    @endif
                                </div>
                            @endforeach
                        </div>


                        @if ($data->count() > 1)
                            <button type="button" x-ref="prev"
                                    class="absolute left-3 top-1/2 z-10 hidden size-11 -translate-y-1/2 items-center justify-center rounded-full border border-line bg-surface/90 text-ink transition-colors hover:bg-surface disabled:opacity-40 sm:flex"
                                    aria-label="{{ __('Previous banner') }}">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
                            </button>
                            <button type="button" x-ref="next"
                                    class="absolute right-3 top-1/2 z-10 hidden size-11 -translate-y-1/2 items-center justify-center rounded-full border border-line bg-surface/90 text-ink transition-colors hover:bg-surface disabled:opacity-40 sm:flex"
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
                        <x-ui.section-heading :title="$title" class="motion-reveal"
                                              x-data="{ shown: false }" x-intersect.once="shown = true" x-bind:class="shown && 'revealed'" />
                    @endif
                    {{-- 3 across on a phone, not 4: at 390px four columns give 81px
                         tiles, and the catalogue has 5 top-level categories rather
                         than the 8 this grid was drawn for, so the fourth column
                         only ever bought a lonelier orphan row. --}}
                    <div class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-4 sm:mt-6 sm:gap-4 lg:grid-cols-8">
                        @foreach ($data as $category)
                            @php $categoryName = $category->getTranslation('name', app()->getLocale()); @endphp
                            <a href="{{ route('category.show', $category->slug) }}" wire:navigate
                               wire:key="category-{{ $category->id }}"
                               x-data="{ shown: false }" x-intersect.once="shown = true" x-bind:class="shown && 'revealed'"
                               style="animation-delay: {{ min($loop->index * 40, 320) }}ms"
                               class="group motion-reveal flex flex-col items-center gap-2 rounded-[var(--radius-card)] border border-line bg-surface p-3 shadow-soft hb-lift hover:border-brass/40">
                                <span class="block aspect-square w-full overflow-hidden rounded-[var(--radius-control)] bg-paper">
                                    @if ($categoryImage = $category->getFirstMediaUrl('image', 'thumb'))
                                        <img src="{{ $categoryImage }}" alt="{{ $categoryName }}"
                                             x-data="{ ld: false }" x-init="ld = $el.complete" x-on:load="ld = true" x-bind:class="ld && 'loaded'"
                                             class="img-motion [--img-zoom-dur:450ms] size-full object-contain group-hover:scale-[1.05]" loading="lazy">
                                    @else
                                        {{-- No media on the category: without this the tile
                                             rendered as an empty paper box, which reads as a
                                             broken image rather than a category. The brand
                                             mark is what empty-state already uses. --}}
                                        <span class="grid size-full place-items-center text-brass/45">
                                            <x-ui.star-mark :size="28" stroke-width="1.25" />
                                        </span>
                                    @endif
                                </span>
                                <span class="line-clamp-2 text-center text-[13px] font-medium leading-snug text-ink transition-colors duration-(--dur-micro) group-hover:text-emerald">{{ $categoryName }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
                @break

            {{-- ===== Product carousel (horizontal scroll strip) ===== --}}
            @case('product_carousel')
                <section class="mx-auto max-w-7xl px-4 pt-12 sm:pt-16" wire:key="section-{{ $section->id }}">
                    @if ($title)
                        <x-ui.section-heading :title="$title" :href="route('search')" :link-label="__('View all')" class="motion-reveal"
                                              x-data="{ shown: false }" x-intersect.once="shown = true" x-bind:class="shown && 'revealed'" />
                    @else
                        <div class="flex justify-end">
                            <a href="{{ route('search') }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 text-sm font-medium text-ink-soft transition-colors hover:text-ink">{{ __('View all') }}</a>
                        </div>
                    @endif
                    <div class="no-scrollbar -mx-4 mt-4 flex snap-x gap-3 overflow-x-auto px-4 pb-2 sm:mt-6">
                        @foreach ($data as $product)
                            <div class="motion-reveal w-44 shrink-0 snap-start sm:w-48" wire:key="carousel-{{ $section->id }}-{{ $product->id }}"
                                 x-data="{ shown: false }" x-intersect.once="shown = true" x-bind:class="shown && 'revealed'"
                                 style="animation-delay: {{ min($loop->index * 40, 320) }}ms">
                                <x-product-card :product="$product" :wishlisted="in_array($product->id, $wishlistedIds)" :sponsored="(bool) ($product->sponsored ?? false)" />
                            </div>
                        @endforeach
                    </div>
                </section>
                @break

            {{-- ===== Product grid (2/3/4/6 cols) ===== --}}
            @case('product_grid')
                <section class="mx-auto max-w-7xl px-4 pt-12 sm:pt-16" wire:key="section-{{ $section->id }}">
                    @if ($title)
                        <x-ui.section-heading :title="$title" :href="route('search')" :link-label="__('View all')" class="motion-reveal"
                                              x-data="{ shown: false }" x-intersect.once="shown = true" x-bind:class="shown && 'revealed'" />
                    @else
                        <div class="flex justify-end">
                            <a href="{{ route('search') }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 text-sm font-medium text-ink-soft transition-colors hover:text-ink">{{ __('View all') }}</a>
                        </div>
                    @endif
                    <div class="mt-4 grid grid-cols-2 gap-3 sm:mt-6 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-5">
                        @foreach ($data as $product)
                            <div class="motion-reveal" wire:key="grid-{{ $section->id }}-{{ $product->id }}"
                                 x-data="{ shown: false }" x-intersect.once="shown = true" x-bind:class="shown && 'revealed'"
                                 style="animation-delay: {{ min($loop->index * 40, 320) }}ms">
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
                                <x-ui.section-heading :title="$title" class="motion-reveal"
                                                      x-data="{ shown: false }" x-intersect.once="shown = true" x-bind:class="shown && 'revealed'" />
                            @endif
                            <div class="no-scrollbar -mx-4 mt-4 flex snap-x gap-3 overflow-x-auto px-4 pb-2 sm:mt-6">
                                @foreach ($data as $product)
                                    <div class="motion-reveal w-44 shrink-0 snap-start sm:w-48" wire:key="recent-{{ $product->id }}"
                                         x-data="{ shown: false }" x-intersect.once="shown = true" x-bind:class="shown && 'revealed'"
                                         style="animation-delay: {{ min($loop->index * 40, 320) }}ms">
                                        <x-product-card :product="$product" :wishlisted="in_array($product->id, $wishlistedIds)" />
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif
                </div>
                @break

            {{-- ===== Recommended for you (lazy, personalised; self-renders, hidden when empty) ===== --}}
            @case('recommended')
                <div class="mx-auto max-w-7xl px-4" wire:key="section-{{ $section->id }}">
                    <livewire:storefront.recommended-products context="home" :wire:key="'rec-home-'.$section->id" />
                </div>
                @break
        @endswitch
    @endforeach

    {{-- One-time welcome tour for first-time visitors (home only) --}}
    @include('livewire.storefront.partials.welcome-tour')
</div>
