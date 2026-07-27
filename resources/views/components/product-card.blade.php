@props(['product', 'wishlisted' => false, 'sponsored' => false])

@php
    $defaultVariant = $product->variants->firstWhere('is_default', true) ?? $product->variants->first();
    $minPrice = $product->variants->map->effectivePriceSen()->min() ?? 0;
    $maxDiscount = $product->variants->map->discountPercent()->filter()->max();
    $image = $product->getFirstMediaUrl('images', 'thumb');
    $singleVariant = $product->variants->count() === 1;
    $inStock = $product->variants->sum('stock') > 0;
@endphp

<div class="group relative flex h-full flex-col overflow-hidden rounded-[var(--radius-card)] border border-line bg-surface shadow-soft hb-lift hover:border-line-strong">
    <a href="{{ route('product.show', $product->slug) }}" wire:navigate class="absolute inset-0 z-10" aria-label="{{ $product->getTranslation('name', app()->getLocale()) }}"></a>

    <div class="relative aspect-square overflow-hidden bg-paper">
        @if ($image)
            <img src="{{ $image }}" alt="{{ $product->getTranslation('name', app()->getLocale()) }}{{ $defaultVariant?->options_label ? ' — '.$defaultVariant->options_label : '' }}"
                 x-data="{ ld: false }" x-init="ld = $el.complete" x-on:load="ld = true" x-bind:class="ld && 'loaded'"
                 class="img-motion size-full object-cover group-hover:scale-[1.04]" loading="lazy">
        @endif

        {{-- Paid placement disclosure — deliberately neutral, never emerald --}}
        @if ($sponsored)
            <span class="absolute left-2 top-2 z-20 inline-flex items-center rounded-full border border-line bg-surface/90 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[0.04em] text-ink-soft">
                {{ __('Sponsored') }}
            </span>
        @endif

        @auth
            <button
                type="button"
                wire:click="toggleWishlist({{ $product->id }})"
                class="absolute right-2 top-2 z-20 flex size-9 items-center justify-center rounded-full border border-line bg-surface/90 hb-press [--press:0.9] {{ $wishlisted ? 'text-danger' : 'text-ink-faint hover:text-ink' }}"
                aria-label="{{ $wishlisted ? __('Remove from wishlist') : __('Add to wishlist') }}"
            >
                <svg class="size-4" viewBox="0 0 24 24" fill="{{ $wishlisted ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
            </button>
        @endauth

        @unless ($inStock)
            <div class="absolute inset-x-0 bottom-0 z-20 bg-ink/70 py-1 text-center text-[11px] font-semibold uppercase tracking-[0.04em] text-paper">{{ __('Out of stock') }}</div>
        @endunless
    </div>

    <div class="flex flex-1 flex-col gap-1.5 p-3">
        {{-- min-h reserves 2 lines (2 × leading-snug 1.375em) so 1-line titles don't shorten the card --}}
        <h3 class="line-clamp-2 min-h-[2.75em] text-[13px] font-medium leading-snug text-ink">{{ $product->getTranslation('name', app()->getLocale()) }}</h3>

        <div class="flex items-center gap-1.5">
            <span class="text-sm font-bold text-ink tnum">@price($minPrice)</span>
            @if ($maxDiscount)
                <x-ui.badge variant="sale">-{{ $maxDiscount }}%</x-ui.badge>
            @endif
        </div>

        {{-- Bottom row, Shopee-style: rating · sold · state left, round cart icon right.
             min-h-8 = the icon's height, so icon-less cards keep the same content height --}}
        <div class="mt-auto flex min-h-8 items-center gap-1 whitespace-nowrap text-xs text-ink-soft">
            @if ($product->rating_count > 0)
                <span aria-hidden="true">★</span><span class="tnum">{{ number_format((float) $product->rating_avg, 1) }}</span>
                <span aria-hidden="true">·</span>
            @endif
            <span>{{ $product->sold_count >= 1000 ? number_format($product->sold_count / 1000, 1).'k' : $product->sold_count }} {{ __('sold') }}</span>
            @if ($product->store?->state)
                <span aria-hidden="true">·</span><span class="min-w-0 truncate">{{ $product->store->state }}</span>
            @endif

            @if ($singleVariant && $inStock && $defaultVariant)
                {{-- Cart icon crossfades to ✓ for 1.2s; both share one grid cell.
                     ponytail: flips optimistically on click; a failed add just reverts silently at 1.2s. --}}
                <button
                    type="button"
                    x-data="{ added: false, flash() { this.added = true; clearTimeout(this._t); this._t = setTimeout(() => this.added = false, 1200); } }"
                    x-on:click="$store.cart.bump(); flash()"
                    wire:click="addToCart({{ $defaultVariant->id }})"
                    class="relative z-20 ml-auto inline-grid size-8 shrink-0 place-items-center rounded-full border border-line-strong text-ink hb-press hover:border-emerald hover:bg-emerald-tint hover:text-emerald"
                    aria-label="{{ __('Add to cart') }}"
                >
                    <svg class="col-start-1 row-start-1 size-4 transition-opacity duration-(--dur-micro)" x-bind:style="added && { opacity: 0 }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/></svg>
                    <span class="col-start-1 row-start-1 text-sm font-semibold text-emerald opacity-0 transition-opacity duration-(--dur-micro)" x-bind:style="added && { opacity: 1 }" aria-hidden="true">✓</span>
                </button>
            @endif
        </div>
    </div>
</div>
