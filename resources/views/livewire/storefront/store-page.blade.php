@php
    $logo = $store->getFirstMediaUrl('logo');
    // The seller settings form has always accepted a banner and promised it is
    // "shown on your store page" — nothing ever rendered it. The `banner` media
    // collection and its 1200px `card` conversion existed the whole time
    // (Store::registerMediaConversions), so this is the missing reader, not a
    // new feature. Fall back to the original when the conversion has not been
    // generated yet: the queue can lag an upload, and an empty src would give
    // the seller the same "my banner did nothing" experience twice.
    $banner = $store->getFirstMediaUrl('banner', 'card') ?: $store->getFirstMediaUrl('banner');
@endphp

<div>
    {{-- ===== Header ===== --}}
    <section class="border-b border-line bg-surface">
        @if ($banner)
            {{-- Fixed aspect box rather than a free-height img: sellers upload
                 anything from a square photo to a 4:1 strip, and letting the
                 image dictate height would shove the whole shop below the fold
                 on the tall ones. object-cover crops to the band instead. --}}
            <div class="aspect-[4/1] w-full overflow-hidden bg-paper sm:aspect-[5/1]">
                <img src="{{ $banner }}" alt="" loading="lazy" decoding="async"
                     class="size-full object-cover">
            </div>
        @endif

        <div class="mx-auto max-w-7xl px-4 py-6">
            <div class="flex flex-wrap items-center gap-4">
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $store->name }}"
                         class="size-20 shrink-0 rounded-full border border-line bg-paper object-cover shadow-soft">
                @else
                    <div class="flex size-20 shrink-0 items-center justify-center rounded-full border border-line bg-brass-tint font-display text-2xl font-medium text-brass-deep shadow-soft" aria-hidden="true">
                        {{ mb_substr($store->name, 0, 1) }}
                    </div>
                @endif

                <div class="min-w-0">
                    <h1 class="flex flex-wrap items-center gap-2 font-display text-2xl font-medium text-ink">
                        {{ $store->name }}
                        <x-ui.badge variant="verified">
                            <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            {{ __('Verified') }}
                        </x-ui.badge>
                    </h1>
                    <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px] text-ink-soft">
                        @if ($store->rating_count > 0)
                            <span><span aria-hidden="true">★</span> <span class="tnum">{{ number_format((float) $store->rating_avg, 1) }} ({{ number_format($store->rating_count) }})</span></span>
                            <span aria-hidden="true">·</span>
                        @endif
                        @if ($store->service_rating_count > 0)
                            <span>{{ __('Seller service') }} <span aria-hidden="true">★</span><span class="tnum">{{ number_format((float) $store->service_rating_avg, 1) }} ({{ number_format($store->service_rating_count) }})</span></span>
                            <span aria-hidden="true">·</span>
                        @endif
                        <span>{{ __('Joined :date', ['date' => $store->created_at->translatedFormat('M Y')]) }}</span>
                        @if ($store->state)
                            <span aria-hidden="true">·</span>
                            <span>{{ $store->state }}</span>
                        @endif
                        <span aria-hidden="true">·</span>
                        <span class="tnum">{{ number_format($total) }} {{ __('products') }}</span>
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- Holiday-mode notice --}}
    @if ($store->holiday_mode)
        <div class="border-b border-line bg-warn-tint">
            <p class="mx-auto max-w-7xl px-4 py-3 text-[13px] font-medium text-warn">
                {{ __('This shop is on holiday — orders are paused.') }}
            </p>
        </div>
    @endif

    {{-- ===== Tabs ===== --}}
    <div class="mx-auto max-w-7xl px-4 py-6" x-data="{ tab: 'products' }">
        <div class="flex gap-1 border-b border-line" role="tablist" aria-label="{{ __('Store sections') }}">
            <button type="button" role="tab" x-on:click="tab = 'products'"
                    x-bind:aria-selected="tab === 'products' ? 'true' : 'false'"
                    x-bind:class="tab === 'products' ? 'border-ink text-ink' : 'border-transparent text-ink-soft hover:text-ink'"
                    class="-mb-px min-h-11 border-b-2 px-4 text-sm font-medium transition-colors duration-150">
                {{ __('Products') }}
            </button>
            <button type="button" role="tab" x-on:click="tab = 'about'"
                    x-bind:aria-selected="tab === 'about' ? 'true' : 'false'"
                    x-bind:class="tab === 'about' ? 'border-ink text-ink' : 'border-transparent text-ink-soft hover:text-ink'"
                    class="-mb-px min-h-11 border-b-2 px-4 text-sm font-medium transition-colors duration-150">
                {{ __('About') }}
            </button>
        </div>

        {{-- Products tab --}}
        <div x-show="tab === 'products'" role="tabpanel" class="pt-5">
            @if ($total > 0)
                <div class="mb-4 flex items-center justify-between gap-3">
                    <p class="text-[13px] text-ink-soft tnum">{{ __(':count products', ['count' => number_format($total)]) }}</p>
                    <select wire:model.live="sort" aria-label="{{ __('Sort products') }}"
                            class="h-11 cursor-pointer rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-[13px] font-medium text-ink">
                        <option value="latest">{{ __('Latest') }}</option>
                        <option value="top">{{ __('Top sales') }}</option>
                        <option value="price_asc">{{ __('Price: low to high') }}</option>
                        <option value="price_desc">{{ __('Price: high to low') }}</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-6"
                     wire:loading.class="opacity-60" wire:target="sort, loadMore">
                    @foreach ($products as $item)
                        <div wire:key="store-product-{{ $item->id }}">
                            <x-product-card :product="$item" :wishlisted="in_array($item->id, $wishlistedIds, true)" />
                        </div>
                    @endforeach
                </div>

                @if ($products->count() < $total)
                    <div class="mt-6 text-center">
                        <button type="button" wire:click="loadMore"
                                class="inline-flex min-h-11 items-center justify-center rounded-[var(--radius-control)] border border-ink px-6 text-sm font-medium text-ink transition-colors duration-150 hover:bg-paper">
                            <span wire:loading.remove wire:target="loadMore">{{ __('Load more') }}</span>
                            <span wire:loading wire:target="loadMore">{{ __('Loading…') }}</span>
                        </button>
                    </div>
                @endif
            @else
                <x-ui.empty-state :title="__('No products yet')" :message="__('This shop has not listed anything — check back soon.')" />
            @endif
        </div>

        {{-- About tab --}}
        <div x-show="tab === 'about'" x-cloak role="tabpanel" class="pt-5">
            @if (filled($store->description))
                <p class="max-w-prose whitespace-pre-line text-sm leading-relaxed text-ink">{{ $store->description }}</p>
            @else
                <p class="text-sm text-ink-soft">{{ __('This shop has not written a description yet.') }}</p>
            @endif
        </div>
    </div>
</div>
