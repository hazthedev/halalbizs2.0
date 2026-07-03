@props(['product', 'wishlisted' => false, 'sponsored' => false])

@php
    $defaultVariant = $product->variants->firstWhere('is_default', true) ?? $product->variants->first();
    $minPrice = $product->variants->map->effectivePriceSen()->min() ?? 0;
    $maxDiscount = $product->variants->map->discountPercent()->filter()->max();
    $image = $product->getFirstMediaUrl('images', 'thumb');
    $singleVariant = $product->variants->count() === 1;
    $inStock = $product->variants->sum('stock') > 0;
@endphp

<div class="group relative flex flex-col overflow-hidden rounded-[var(--radius-card)] border border-line bg-surface shadow-soft hb-lift hover:border-line-strong">
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
        <h3 class="line-clamp-2 text-[13px] font-medium leading-snug text-ink">{{ $product->getTranslation('name', app()->getLocale()) }}</h3>

        <div class="flex items-center gap-1.5">
            <span class="text-sm font-bold text-ink tnum">@price($minPrice)</span>
            @if ($maxDiscount)
                <x-ui.badge variant="sale">-{{ $maxDiscount }}%</x-ui.badge>
            @endif
        </div>

        <div class="mt-auto flex items-center gap-1 text-xs text-ink-soft">
            @if ($product->rating_count > 0)
                <span aria-hidden="true">★</span><span class="tnum">{{ number_format((float) $product->rating_avg, 1) }}</span>
                <span aria-hidden="true">·</span>
            @endif
            <span>{{ $product->sold_count >= 1000 ? number_format($product->sold_count / 1000, 1).'k' : $product->sold_count }} {{ __('sold') }}</span>
            @if ($product->store?->state)
                <span aria-hidden="true">·</span><span class="truncate">{{ $product->store->state }}</span>
            @endif
        </div>

        @if ($singleVariant && $inStock && $defaultVariant)
            {{-- Label crossfades to "✓ Added" for 1.2s; both labels share one grid
                 cell so the button keeps the width of the wider one — no jump.
                 ponytail: flips optimistically on click (like the badge bump);
                 a failed add just reverts silently at 1.2s. --}}
            <button
                type="button"
                x-data="{ added: false, flash() { this.added = true; clearTimeout(this._t); this._t = setTimeout(() => this.added = false, 1200); } }"
                x-on:click="$store.cart.bump(); flash()"
                wire:click="addToCart({{ $defaultVariant->id }})"
                class="relative z-20 mt-1 inline-grid min-h-9 place-items-center rounded-[var(--radius-control)] border border-line-strong px-3 text-[13px] font-semibold text-ink hb-press hover:border-emerald hover:bg-emerald-tint hover:text-emerald"
            >
                <span class="col-start-1 row-start-1 transition-opacity duration-(--dur-micro)" x-bind:style="added && { opacity: 0 }">{{ __('Add to cart') }}</span>
                <span class="col-start-1 row-start-1 text-emerald opacity-0 transition-opacity duration-(--dur-micro)" x-bind:style="added && { opacity: 1 }" aria-hidden="true">✓ {{ __('Added') }}</span>
            </button>
        @endif
    </div>
</div>
