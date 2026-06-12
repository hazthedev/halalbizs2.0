@php
    $name = $product->getTranslation('name', app()->getLocale());
    $images = $product->getMedia('images');
    $variantImage = $variant?->getFirstMediaUrl('image') ?: null;
    $mainImage = $variantImage ?: $images->first()?->getUrl();
    $canBuy = $variant !== null && $variant->stock > 0;
    $store = $product->store;
@endphp

<div class="pb-28 lg:pb-0" x-data x-init="window.recentlyViewed?.push({{ $product->id }})">
    @push('meta')
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush

    <div class="mx-auto max-w-7xl px-4 py-6 lg:py-10">
        <div class="grid items-start gap-8 lg:grid-cols-[55fr_45fr] lg:gap-12">

            {{-- ===== Gallery ===== --}}
            <div wire:key="gallery-{{ $variant?->id ?? 'base' }}" x-data="{ activeImage: @js($mainImage) }">
                <div class="aspect-square overflow-hidden rounded-[10px] border border-line bg-paper">
                    @if ($mainImage)
                        <img x-bind:src="activeImage" src="{{ $mainImage }}"
                             alt="{{ $name }}{{ $variant?->options_label ? ' — '.$variant->options_label : '' }}"
                             class="size-full object-cover">
                    @else
                        <div class="flex size-full items-center justify-center text-sm text-ink-faint">{{ __('No image yet') }}</div>
                    @endif
                </div>

                @if ($images->count() > 1)
                    <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                        @foreach ($images as $media)
                            <button type="button"
                                    x-on:click="activeImage = @js($media->getUrl())"
                                    x-bind:class="activeImage === @js($media->getUrl()) ? 'border-emerald' : 'border-line hover:border-line-strong'"
                                    class="size-16 shrink-0 overflow-hidden rounded-lg border bg-paper"
                                    aria-label="{{ __('View image :number of :name', ['number' => $loop->iteration, 'name' => $name]) }}">
                                <img src="{{ $media->getUrl() }}" alt="{{ $name }}" class="size-full object-cover" loading="lazy">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- ===== Buy box ===== --}}
            <div>
                <h1 class="text-[17px] font-semibold leading-snug text-ink">{{ $name }}</h1>

                <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[13px] text-ink-soft">
                    @if ($product->rating_count > 0)
                        <span aria-hidden="true" class="text-ink">★</span>
                        <span class="tnum font-semibold text-ink">{{ number_format((float) $product->rating_avg, 1) }}</span>
                        <span class="tnum">({{ number_format($product->rating_count) }})</span>
                        <span aria-hidden="true">·</span>
                    @endif
                    <span class="tnum">{{ $product->sold_count >= 1000 ? number_format($product->sold_count / 1000, 1).'k' : $product->sold_count }} {{ __('sold') }}</span>
                </div>

                {{-- Price block --}}
                <div class="mt-4 rounded-[10px] bg-paper px-4 py-3">
                    @if ($variant !== null)
                        <div class="flex flex-wrap items-baseline gap-x-2.5 gap-y-1">
                            <span class="text-2xl font-bold text-ink tnum">@price($variant->effectivePriceSen())</span>
                            @if ($variant->isOnSale())
                                <span class="text-sm text-ink-faint line-through tnum">@price($variant->price_sen)</span>
                                <x-ui.badge variant="sale">-{{ $variant->discountPercent() }}%</x-ui.badge>
                            @endif
                        </div>
                        @if ($variant->isOnSale() && $variant->sale_ends_at !== null && $variant->sale_ends_at->isFuture() && now()->diffInHours($variant->sale_ends_at) < 48)
                            @php $minutesLeft = (int) now()->diffInMinutes($variant->sale_ends_at); @endphp
                            <p class="mt-1 text-[13px] font-medium text-emerald">
                                {{ __('Sale ends in :time', ['time' => intdiv($minutesLeft, 60).'h '.($minutesLeft % 60).'m']) }}
                            </p>
                        @endif
                    @else
                        @php
                            $minSen = $product->minPriceSen();
                            $maxSen = $product->maxPriceSen();
                        @endphp
                        <span class="text-2xl font-bold text-ink tnum">@price($minSen)@if ($maxSen > $minSen) <span aria-hidden="true">–</span> @price($maxSen)@endif</span>
                    @endif
                </div>

                {{-- Variant picker (skipped entirely for single-variant products) --}}
                @if ($product->variants->count() > 1 && $product->options->isNotEmpty())
                    <div class="mt-5 space-y-4">
                        @foreach ($product->options as $option)
                            <fieldset wire:key="option-{{ $option->id }}" data-option-group>
                                <legend class="text-[13px] font-medium text-ink">{{ $option->name }}</legend>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach ($option->values as $value)
                                        @php
                                            $selected = ($selectedValues[$option->id] ?? null) === $value->id;
                                            $available = $availability[$option->id][$value->id] ?? false;
                                        @endphp
                                        <button type="button"
                                                wire:key="chip-{{ $value->id }}"
                                                wire:click="selectValue({{ $option->id }}, {{ $value->id }})"
                                                @disabled(! $available)
                                                aria-pressed="{{ $selected ? 'true' : 'false' }}"
                                                class="min-h-11 rounded-full border px-4 text-sm font-medium transition-colors duration-150 disabled:cursor-not-allowed disabled:opacity-40 {{ $selected ? 'border-emerald bg-emerald-tint text-emerald' : 'border-line-strong text-ink hover:border-ink' }}">
                                            {{ $value->value }}
                                        </button>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach
                    </div>
                @endif

                {{-- Quantity stepper --}}
                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <span class="text-[13px] font-medium text-ink">{{ __('Quantity') }}</span>
                    <div class="inline-flex items-center rounded-full border border-line-strong">
                        <button type="button" wire:click="decrementQty"
                                @disabled($variant === null || $qty <= 1)
                                class="flex size-11 items-center justify-center rounded-l-full text-ink-soft hover:text-ink disabled:cursor-not-allowed disabled:opacity-40"
                                aria-label="{{ __('Decrease quantity') }}">−</button>
                        <span class="min-w-8 text-center font-mono text-sm font-medium tnum">{{ $qty }}</span>
                        <button type="button" wire:click="incrementQty"
                                @disabled($variant === null || $qty >= $variant->stock)
                                class="flex size-11 items-center justify-center rounded-r-full text-ink-soft hover:text-ink disabled:cursor-not-allowed disabled:opacity-40"
                                aria-label="{{ __('Increase quantity') }}">+</button>
                    </div>
                    @if ($variant !== null && $variant->stock > 0 && $variant->stock < 10)
                        <span class="text-[13px] font-medium text-warn">{{ __('Only :count left', ['count' => $variant->stock]) }}</span>
                    @elseif ($variant !== null && $variant->stock < 1)
                        <x-ui.badge variant="out-of-stock">{{ __('Out of stock') }}</x-ui.badge>
                    @endif
                </div>

                {{-- Shipping row --}}
                <div class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px] text-ink-soft">
                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg>
                    <span>{{ __('Ships from :state', ['state' => $store?->state ?? 'Malaysia']) }}</span>
                    <span aria-hidden="true">·</span>
                    <span>{{ __('Shipping calculated at checkout') }}</span>
                </div>

                {{-- Badges row --}}
                @if ($codAvailable)
                    <div class="mt-3 flex flex-wrap gap-2">
                        <x-ui.badge variant="cod">{{ __('Cash on delivery') }}</x-ui.badge>
                    </div>
                @endif

                {{-- Actions --}}
                <div class="mt-6 hidden gap-3 lg:flex">
                    <button type="button"
                            data-testid="pdp-add-to-cart"
                            x-on:click="$store.cart.bump()"
                            wire:click="addToCart({{ $variant?->id ?? 0 }}, {{ $qty }})"
                            @disabled(! $canBuy)
                            class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg border border-ink px-4 text-sm font-semibold text-ink transition-colors duration-150 hover:bg-paper disabled:cursor-not-allowed disabled:opacity-50">
                        {{ __('Add to cart') }}
                    </button>
                    <button type="button"
                            wire:click="buyNow"
                            @disabled(! $canBuy)
                            class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg bg-emerald px-4 text-sm font-semibold text-white transition-colors duration-150 hover:bg-emerald-deep active:bg-emerald-night disabled:cursor-not-allowed disabled:opacity-50">
                        {{ __('Buy now') }}
                    </button>
                </div>
                {{-- Mobile actions live in the sticky buy bar below --}}

                {{-- Seller card --}}
                @if ($store !== null)
                    <section class="mt-8 rounded-[10px] border border-line bg-surface p-4" aria-label="{{ __('Seller') }}">
                        <div class="flex flex-wrap items-center gap-3">
                            @if ($store->getFirstMediaUrl('logo'))
                                <img src="{{ $store->getFirstMediaUrl('logo') }}" alt="{{ $store->name }}"
                                     class="size-12 shrink-0 rounded-full border border-line object-cover bg-paper">
                            @else
                                <div class="flex size-12 shrink-0 items-center justify-center rounded-full border border-line bg-paper font-display text-lg font-bold text-ink-soft" aria-hidden="true">
                                    {{ mb_substr($store->name, 0, 1) }}
                                </div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('store.show', $store->slug) }}" wire:navigate class="truncate text-sm font-semibold text-ink">{{ $store->name }}</a>
                                    @if ($store->isApproved())
                                        <x-ui.badge variant="verified">
                                            <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                            {{ __('Verified') }}
                                        </x-ui.badge>
                                    @endif
                                </p>
                                <p class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[13px] text-ink-soft">
                                    @if ($store->rating_count > 0)
                                        <span aria-hidden="true">★</span>
                                        <span class="tnum">{{ number_format((float) $store->rating_avg, 1) }} ({{ number_format($store->rating_count) }})</span>
                                        <span aria-hidden="true">·</span>
                                    @endif
                                    <span class="tnum">{{ number_format($storeProductsCount) }} {{ __('products') }}</span>
                                    @if ($store->state)
                                        <span aria-hidden="true">·</span>
                                        <span>{{ $store->state }}</span>
                                    @endif
                                </p>
                            </div>
                            <a href="{{ route('store.show', $store->slug) }}" wire:navigate
                               class="inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-ink-soft transition-colors duration-150 hover:text-ink">
                                {{ __('Visit store') }}
                            </a>
                        </div>
                    </section>
                @endif
            </div>
        </div>

        {{-- ===== Tabs ===== --}}
        <section class="mt-10" x-data="{ tab: 'description', lightbox: null }">
            <div class="flex gap-1 overflow-x-auto border-b border-line" role="tablist" aria-label="{{ __('Product information') }}">
                <button type="button" role="tab" x-on:click="tab = 'description'"
                        x-bind:aria-selected="tab === 'description' ? 'true' : 'false'"
                        x-bind:class="tab === 'description' ? 'border-ink text-ink' : 'border-transparent text-ink-soft hover:text-ink'"
                        class="-mb-px min-h-11 shrink-0 border-b-2 px-4 text-sm font-semibold transition-colors duration-150">
                    {{ __('Description') }}
                </button>
                <button type="button" role="tab" x-on:click="tab = 'specifications'"
                        x-bind:aria-selected="tab === 'specifications' ? 'true' : 'false'"
                        x-bind:class="tab === 'specifications' ? 'border-ink text-ink' : 'border-transparent text-ink-soft hover:text-ink'"
                        class="-mb-px min-h-11 shrink-0 border-b-2 px-4 text-sm font-semibold transition-colors duration-150">
                    {{ __('Specifications') }}
                </button>
                <button type="button" role="tab" x-on:click="tab = 'reviews'"
                        x-bind:aria-selected="tab === 'reviews' ? 'true' : 'false'"
                        x-bind:class="tab === 'reviews' ? 'border-ink text-ink' : 'border-transparent text-ink-soft hover:text-ink'"
                        class="-mb-px min-h-11 shrink-0 border-b-2 px-4 text-sm font-semibold transition-colors duration-150">
                    {{ __('Reviews') }}
                </button>
            </div>

            <div x-show="tab === 'description'" role="tabpanel" class="max-w-prose space-y-3 py-5 text-sm leading-relaxed text-ink [&_h2]:font-display [&_h2]:text-lg [&_h2]:font-bold [&_li]:ml-5 [&_ol]:list-decimal [&_ul]:list-disc">
                {!! $product->getTranslation('description', app()->getLocale()) !!}
            </div>

            <div x-show="tab === 'specifications'" x-cloak role="tabpanel" class="py-5">
                <table class="w-full max-w-md text-[13px]">
                    <tbody class="divide-y divide-line">
                        <tr>
                            <th scope="row" class="w-40 py-2.5 pr-4 text-left font-medium text-ink-soft">{{ __('Brand') }}</th>
                            <td class="py-2.5 text-ink">{{ $product->brand?->name ?? __('No brand') }}</td>
                        </tr>
                        <tr>
                            <th scope="row" class="w-40 py-2.5 pr-4 text-left font-medium text-ink-soft">{{ __('Condition') }}</th>
                            <td class="py-2.5 text-ink">{{ $product->condition->label() }}</td>
                        </tr>
                        <tr>
                            <th scope="row" class="w-40 py-2.5 pr-4 text-left font-medium text-ink-soft">{{ __('Weight') }}</th>
                            <td class="py-2.5 text-ink tnum">{{ number_format((int) $product->weight_grams) }} g</td>
                        </tr>
                        @if ($product->category !== null)
                            <tr>
                                <th scope="row" class="w-40 py-2.5 pr-4 text-left font-medium text-ink-soft">{{ __('Category') }}</th>
                                <td class="py-2.5 text-ink">{{ $product->category->getTranslation('name', app()->getLocale()) }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>

            @php($visibleReviewTotal = array_sum($reviewDistribution))
            <div x-show="tab === 'reviews'" x-cloak role="tabpanel" class="py-5">
                @if ($visibleReviewTotal === 0)
                    <div class="py-5 text-center">
                        <p class="font-display text-lg font-semibold">{{ __('No reviews yet') }}</p>
                        <p class="mt-1 text-sm text-ink-soft">{{ __('Reviews appear here after buyers complete their orders.') }}</p>
                    </div>
                @else
                    {{-- Summary: big average + per-star distribution bars --}}
                    <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:gap-10">
                        <div class="shrink-0 text-center sm:text-left">
                            <p class="font-display text-5xl font-bold text-ink tnum">{{ number_format((float) $product->rating_avg, 1) }}</p>
                            <p class="mt-1 flex justify-center gap-0.5 text-lg leading-none sm:justify-start" aria-hidden="true">
                                @foreach (range(1, 5) as $star)
                                    <span class="{{ $star <= (int) round((float) $product->rating_avg) ? 'text-warn' : 'text-line' }}">★</span>
                                @endforeach
                            </p>
                            <p class="sr-only">{{ __('Rated :avg out of 5', ['avg' => number_format((float) $product->rating_avg, 1)]) }}</p>
                            <p class="mt-1 text-[13px] text-ink-soft tnum">{{ trans_choice('{1}:count review|[2,*]:count reviews', $visibleReviewTotal, ['count' => number_format($visibleReviewTotal)]) }}</p>
                        </div>
                        <div class="w-full max-w-sm space-y-1.5">
                            @foreach ($reviewDistribution as $star => $count)
                                <div class="flex items-center gap-2 text-[13px]"
                                     aria-label="{{ __(':stars-star reviews: :count', ['stars' => $star, 'count' => $count]) }}">
                                    <span class="w-6 shrink-0 text-right text-ink-soft tnum" aria-hidden="true">{{ $star }}★</span>
                                    <span class="h-2.5 flex-1 overflow-hidden rounded-full border border-line bg-surface" aria-hidden="true">
                                        <span class="block h-full rounded-full bg-emerald-tint" style="width: {{ $visibleReviewTotal > 0 ? round($count / $visibleReviewTotal * 100) : 0 }}%"></span>
                                    </span>
                                    <span class="w-8 shrink-0 text-ink-soft tnum" aria-hidden="true">{{ number_format($count) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Filters --}}
                    @php($reviewFilters = ['all' => __('All'), 'photos' => __('With photos'), '5' => '5 ★', '4' => '4 ★', '3' => '3 ★', '2' => '2 ★', '1' => '1 ★'])
                    <div class="mt-6 flex flex-wrap gap-2" role="group" aria-label="{{ __('Filter reviews') }}">
                        @foreach ($reviewFilters as $key => $label)
                            <button type="button"
                                    wire:key="review-filter-{{ $key }}"
                                    wire:click="setReviewFilter('{{ $key }}')"
                                    aria-pressed="{{ $reviewFilter === $key ? 'true' : 'false' }}"
                                    class="min-h-11 rounded-full border px-4 text-[13px] font-medium transition-colors duration-150 focus-visible:ring-2 focus-visible:ring-emerald {{ $reviewFilter === $key ? 'border-emerald bg-emerald-tint text-emerald' : 'border-line-strong text-ink hover:border-ink' }}">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>

                    {{-- List --}}
                    <div class="mt-2 divide-y divide-line" wire:loading.class="opacity-60" wire:target="setReviewFilter, loadMoreReviews">
                        @forelse ($reviews as $review)
                            <article class="py-4" wire:key="review-{{ $review->id }}">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px]">
                                    <span class="font-medium text-ink">{{ $review->reviewerDisplayName() }}</span>
                                    <span class="text-ink-faint" aria-hidden="true">·</span>
                                    <span class="text-ink-soft">{{ $review->created_at->diffForHumans() }}</span>
                                </div>
                                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <span class="flex gap-0.5 text-sm leading-none" aria-hidden="true">
                                        @foreach (range(1, 5) as $star)
                                            <span class="{{ $star <= $review->rating ? 'text-warn' : 'text-line' }}">★</span>
                                        @endforeach
                                    </span>
                                    <span class="sr-only">{{ __('Rated :rating out of 5', ['rating' => $review->rating]) }}</span>
                                    @if ($review->orderItem?->variant_label)
                                        <span class="text-xs text-ink-faint">{{ $review->orderItem->variant_label }}</span>
                                    @endif
                                </div>
                                @if ($review->comment)
                                    <p class="mt-2 max-w-prose text-sm leading-relaxed text-ink">{{ $review->comment }}</p>
                                @endif
                                @php($reviewPhotos = $review->getMedia('photos'))
                                @if ($reviewPhotos->isNotEmpty())
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($reviewPhotos as $photo)
                                            <button type="button"
                                                    wire:key="review-photo-{{ $photo->id }}"
                                                    x-on:click="lightbox = @js($photo->getUrl())"
                                                    class="size-16 overflow-hidden rounded-lg border border-line bg-paper transition-colors duration-150 hover:border-ink focus-visible:ring-2 focus-visible:ring-emerald"
                                                    aria-label="{{ __('View review photo :number', ['number' => $loop->iteration]) }}">
                                                <img src="{{ $photo->getUrl() }}" alt="{{ __('Review photo') }}" class="size-full object-cover" loading="lazy">
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                                @if ($review->seller_reply)
                                    <div class="ml-4 mt-3 max-w-prose rounded-lg border-l-2 border-line-strong bg-paper px-3.5 py-2.5">
                                        <p class="text-xs font-semibold text-ink">{{ __('Seller response') }}</p>
                                        <p class="mt-1 text-[13px] leading-relaxed text-ink-soft">{{ $review->seller_reply }}</p>
                                    </div>
                                @endif
                            </article>
                        @empty
                            <p class="py-8 text-center text-sm text-ink-soft">{{ __('No reviews match this filter yet.') }}</p>
                        @endforelse
                    </div>

                    @if ($hasMoreReviews)
                        <div class="mt-2 text-center">
                            <x-ui.button variant="secondary" wire:click="loadMoreReviews" wire:loading.attr="disabled">
                                {{ __('Load more reviews') }}
                            </x-ui.button>
                        </div>
                    @endif
                @endif
            </div>

            {{-- Review photo lightbox (true overlay — the only place shadows are allowed) --}}
            <div x-cloak x-show="lightbox" x-transition.opacity.duration.150ms
                 x-on:keydown.escape.window="lightbox = null"
                 x-on:click="lightbox = null"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-ink/85 p-4"
                 role="dialog" aria-modal="true" aria-label="{{ __('Review photo') }}">
                <img x-bind:src="lightbox" alt="{{ __('Review photo, enlarged') }}" class="max-h-[85vh] max-w-full rounded-[10px] object-contain shadow-lg">
                <button type="button" x-on:click="lightbox = null"
                        class="absolute right-4 top-4 flex size-11 items-center justify-center rounded-full text-paper transition-colors duration-150 hover:bg-paper/10 focus-visible:ring-2 focus-visible:ring-emerald"
                        aria-label="{{ __('Close photo') }}">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </section>

        {{-- ===== Related products ===== --}}
        @if ($related->isNotEmpty())
            <section class="mt-10" aria-label="{{ __('Related products') }}">
                <h2 class="font-display text-xl font-bold">{{ __('Related products') }}</h2>
                <div class="mt-4 flex gap-3 overflow-x-auto pb-2">
                    @foreach ($related as $item)
                        <div class="w-44 shrink-0 sm:w-48" wire:key="related-{{ $item->id }}">
                            <x-product-card :product="$item" :wishlisted="in_array($item->id, $wishlistedIds, true)" />
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    {{-- ===== Mobile sticky buy bar (ink frame) ===== --}}
    <div class="fixed inset-x-0 bottom-0 z-30 bg-ink shadow-lg lg:hidden" style="border-top: 1px solid var(--color-emerald-night);">
        <div class="mx-auto flex max-w-7xl items-center gap-2 px-4 py-3">
            <button type="button" disabled title="{{ __('Coming soon') }}"
                    class="flex size-11 shrink-0 cursor-not-allowed items-center justify-center rounded-lg border border-paper/30 text-paper/40"
                    aria-label="{{ __('Chat with seller (coming soon)') }}">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/></svg>
            </button>
            <button type="button"
                    data-testid="pdp-add-to-cart"
                    x-on:click="$store.cart.bump()"
                    wire:click="addToCart({{ $variant?->id ?? 0 }}, {{ $qty }})"
                    @disabled(! $canBuy)
                    class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg border border-paper px-3 text-sm font-semibold text-paper transition-colors duration-150 hover:bg-paper/10 disabled:cursor-not-allowed disabled:opacity-50">
                {{ __('Add to cart') }}
            </button>
            <button type="button"
                    wire:click="buyNow"
                    @disabled(! $canBuy)
                    class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg bg-emerald px-3 text-sm font-semibold text-white transition-colors duration-150 hover:bg-emerald-deep active:bg-emerald-night disabled:cursor-not-allowed disabled:opacity-50">
                {{ __('Buy now') }}
            </button>
        </div>
    </div>
</div>
