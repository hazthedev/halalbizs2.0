@php
    $banner = $store->getFirstMediaUrl('banner');
    $logo = $store->getFirstMediaUrl('logo');
@endphp

<div>
    {{-- ===== Header ===== --}}
    <section class="border-b border-line bg-surface">
        <div class="surface-girih relative h-40 overflow-hidden bg-ink sm:h-56">
            @if ($banner)
                <img src="{{ $banner }}" alt="{{ $store->name }}" class="size-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-t from-ink/60 to-ink/10" aria-hidden="true"></div>
            @endif
        </div>

        <div class="mx-auto max-w-7xl px-4 pb-5">
            {{-- Logo straddles the banner edge; name + meta sit below on the
                 light surface so they stay legible over any banner. --}}
            @if ($logo)
                <img src="{{ $logo }}" alt="{{ $store->name }}"
                     class="-mt-12 size-24 rounded-full border-[3px] border-surface bg-paper object-cover shadow-card">
            @else
                <div class="-mt-12 flex size-24 items-center justify-center rounded-full border-[3px] border-surface bg-brass-tint font-display text-3xl font-bold text-brass-deep shadow-card" aria-hidden="true">
                    {{ mb_substr($store->name, 0, 1) }}
                </div>
            @endif

            <div class="mt-3 min-w-0">
                <h1 class="flex flex-wrap items-center gap-2 font-display text-2xl font-bold text-ink">
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
                    class="-mb-px min-h-11 border-b-2 px-4 text-sm font-semibold transition-colors duration-150">
                {{ __('Products') }}
            </button>
            <button type="button" role="tab" x-on:click="tab = 'about'"
                    x-bind:aria-selected="tab === 'about' ? 'true' : 'false'"
                    x-bind:class="tab === 'about' ? 'border-ink text-ink' : 'border-transparent text-ink-soft hover:text-ink'"
                    class="-mb-px min-h-11 border-b-2 px-4 text-sm font-semibold transition-colors duration-150">
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
                                class="inline-flex min-h-11 items-center justify-center rounded-[var(--radius-control)] border border-ink px-6 text-sm font-semibold text-ink transition-colors duration-150 hover:bg-paper">
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
