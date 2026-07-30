@props(['product', 'wishlisted' => false, 'sponsored' => false])

@php
    $defaultVariant = $product->variants->firstWhere('is_default', true) ?? $product->variants->first();
    $minPrice = $product->variants->map->effectivePriceSen()->min() ?? 0;
    $maxDiscount = $product->variants->map->discountPercent()->filter()->max();
    $image = $product->getFirstMediaUrl('images', 'thumb');
    $singleVariant = $product->variants->count() === 1;
    $inStock = $product->variants->sum('stock') > 0;
    $name = $product->getTranslation('name', app()->getLocale());
    // Certifier badge is OPT-IN on real data. The reference design shows a JAKIM
    // pill on every card, but per-SKU certificate binding is a phase-2 feature —
    // rendering a hardcoded badge would claim a check the app cannot perform.
    // Reads the attribute if it exists, shows nothing otherwise.
    $certifier = $product->certifier ?? null;
@endphp

{{-- Revamp card (2026-07-30): ported from the measured reference. Depth is a
     hairline plus a cream-vs-white surface shift, never a shadow — the whole
     reference page renders exactly one shadow and it is not on a card. --}}
<div class="group relative flex h-full flex-col overflow-hidden rounded-[var(--radius-card)] border border-line bg-surface transition-colors duration-(--dur-micro) hover:border-line-strong">
    <a href="{{ route('product.show', $product->slug) }}" wire:navigate class="absolute inset-0 z-10" aria-label="{{ $name }}"></a>

    {{-- Packshot sits on the cream bed, contained rather than cropped: these are
         product-on-white photographs, so cropping eats the packaging. --}}
    <div class="relative aspect-square overflow-hidden bg-cream">
        @if ($image)
            <img src="{{ $image }}" alt="{{ $name }}{{ $defaultVariant?->options_label ? ', '.$defaultVariant->options_label : '' }}"
                 x-data="{ ld: false }" x-init="ld = $el.complete" x-on:load="ld = true" x-bind:class="ld && 'loaded'"
                 class="img-motion size-full object-contain p-3" loading="lazy">
        @endif

        {{-- Paid placement disclosure — deliberately neutral, never green --}}
        @if ($sponsored)
            <span class="absolute left-2 top-2 z-20 inline-flex items-center rounded-[var(--radius-pill)] border border-line bg-surface/90 px-2 py-0.5 text-[length:var(--text-tiny)] font-medium uppercase tracking-[var(--tracking-brand)] text-ink-faint">
                {{ __('Sponsored') }}
            </span>
        @endif

        @auth
            <button
                type="button"
                wire:click="toggleWishlist({{ $product->id }})"
                class="absolute right-2 top-2 z-20 flex size-9 items-center justify-center rounded-[var(--radius-pill)] border border-line bg-surface/90 hb-press [--press:0.9] {{ $wishlisted ? 'text-danger' : 'text-ink-faint hover:text-ink' }}"
                aria-label="{{ $wishlisted ? __('Remove from wishlist') : __('Add to wishlist') }}"
            >
                <svg class="size-4" viewBox="0 0 24 24" fill="{{ $wishlisted ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
            </button>
        @endauth

        @unless ($inStock)
            <div class="absolute inset-x-0 bottom-0 z-20 bg-emerald-night/70 py-1 text-center text-[length:var(--text-tiny)] font-medium uppercase tracking-[var(--tracking-label)] text-on-dark">{{ __('Out of stock') }}</div>
        @endunless
    </div>

    <div class="flex flex-1 flex-col gap-1.5 p-4 pb-5">
        {{-- Certificate line, mono: machine-checked fact, so it is never sans. --}}
        @if ($certifier)
            <span class="inline-flex w-fit items-center gap-1 font-mono text-[length:var(--text-nano)] font-medium uppercase tracking-[var(--tracking-label-xl)] text-emerald">
                <svg class="size-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                {{ $certifier }}
            </span>
        @endif

        {{-- Seller line — the brand above the product, as the reference does. --}}
        @if ($product->store?->name)
            <span class="truncate text-[length:var(--text-tiny)] uppercase tracking-[var(--tracking-brand)] text-ink-faint">{{ $product->store->name }}</span>
        @endif

        {{-- Serif product name. min-h reserves 2 lines so 1-line titles do not
             shorten the card in a grid. --}}
        <h3 class="line-clamp-2 min-h-[2.65em] font-display text-[length:var(--text-name)] font-normal leading-[1.32] text-ink-head">{{ $name }}</h3>

        @if ($product->rating_count > 0)
            <span class="text-[length:var(--text-mini)] text-ink-moss">
                {{ number_format((float) $product->rating_avg, 1) }} &middot; {{ $product->rating_count }} {{ __('reviews') }}
            </span>
        @endif

        <div class="mt-auto flex items-center gap-2 pt-1">
            {{-- Price is evidence: mono, tabular. --}}
            <span class="font-mono text-[length:var(--text-price)] font-medium text-ink-head tnum">@price($minPrice)</span>
            @if ($maxDiscount)
                <span class="font-mono text-[length:var(--text-tiny)] font-medium uppercase tracking-[var(--tracking-label)] text-brass-deep">{{ __('Save') }} {{ $maxDiscount }}%</span>
            @endif

            @if ($singleVariant && $inStock && $defaultVariant)
                {{-- Add pill. Label crossfades to a tick for 1.2s; both share one
                     grid cell so the pill never resizes.
                     ponytail: flips optimistically on click; a failed add just
                     reverts silently at 1.2s. --}}
                <button
                    type="button"
                    x-data="{ added: false, flash() { this.added = true; clearTimeout(this._t); this._t = setTimeout(() => this.added = false, 1200); } }"
                    x-on:click="$store.cart.bump(); flash()"
                    wire:click="addToCart({{ $defaultVariant->id }})"
                    class="relative z-20 ml-auto inline-grid shrink-0 place-items-center rounded-[var(--radius-pill)] border border-line-strong px-3 py-1 text-[length:var(--text-xs)] text-emerald hb-press hover:border-emerald hover:bg-emerald-tint"
                    aria-label="{{ __('Add to cart') }}"
                >
                    <span class="col-start-1 row-start-1 transition-opacity duration-(--dur-micro)" x-bind:style="added && { opacity: 0 }">{{ __('Add') }}</span>
                    <span class="col-start-1 row-start-1 font-medium opacity-0 transition-opacity duration-(--dur-micro)" x-bind:style="added && { opacity: 1 }" aria-hidden="true">&check;</span>
                </button>
            @endif
        </div>
    </div>
</div>
