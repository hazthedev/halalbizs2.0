@php
    $name = $product->getTranslation('name', app()->getLocale());
    $images = $product->getMedia('images');
    $video = $product->getFirstMedia('videos');
    $variantImage = $variant?->getFirstMediaUrl('image') ?: null;
    $mainImage = $variantImage ?: $images->first()?->getAvailableUrl(['card']);
    $canBuy = $purchasingEnabled && $variant !== null && $variant->stock > 0;
    $store = $product->store;
@endphp

<div class="pb-28 lg:pb-0"
     x-data="{ justAdded: false }"
     x-init="window.recentlyViewed?.push({{ $product->id }})"
     {{-- The corner toast is easy to miss; confirm on the control itself so a
          shopper is never left pressing Add to cart twice and buying two jars. --}}
     @cart-updated.window="justAdded = true; clearTimeout($el._added); $el._added = setTimeout(() => justAdded = false, 2400)">
    @push('meta')
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush

    <div class="mx-auto max-w-7xl px-4 py-6 lg:py-10">
        <div class="grid items-start gap-8 lg:grid-cols-[55fr_45fr] lg:gap-12">

            {{-- ===== Gallery ===== --}}
            <div wire:key="gallery-{{ $variant?->id ?? 'base' }}" x-data="{ activeImage: @js($mainImage), showVideo: false }">
                <div class="aspect-square overflow-hidden rounded-[var(--radius-card)] border border-line bg-paper">
                    @if ($video)
                        <video x-show="showVideo" x-cloak controls preload="none"
                               src="{{ $video->getUrl() }}"
                               poster="{{ $images->first()?->getAvailableUrl(['card']) }}"
                               aria-label="{{ __('Video of :name', ['name' => $name]) }}"
                               class="size-full object-cover"></video>
                    @endif
                    @if ($mainImage)
                        <img @if ($video) x-show="! showVideo" @endif x-bind:src="activeImage" src="{{ $mainImage }}"
                             alt="{{ $name }}{{ $variant?->options_label ? ' — '.$variant->options_label : '' }}"
                             class="size-full object-contain">
                    @elseif (! $video)
                        <div class="flex size-full items-center justify-center text-sm text-ink-faint">{{ __('No image yet') }}</div>
                    @endif
                </div>

                @if ($images->count() > 1 || $video)
                    <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                        @if ($video)
                            {{-- Play thumb: selecting swaps the main area to the video --}}
                            <button type="button"
                                    x-on:click="showVideo = true"
                                    x-bind:class="showVideo ? 'border-emerald' : 'border-line hover:border-line-strong'"
                                    class="relative size-16 shrink-0 overflow-hidden rounded-[var(--radius-control)] border bg-paper"
                                    aria-label="{{ __('Play video of :name', ['name' => $name]) }}">
                                @if ($images->isNotEmpty())
                                    <img src="{{ $images->first()->getAvailableUrl(['thumb']) }}" alt="" aria-hidden="true" class="size-full object-contain" loading="lazy">
                                @endif
                                <span class="absolute inset-0 flex items-center justify-center bg-emerald-night/40">
                                    <svg class="size-6 text-on-dark" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.14v13.72c0 .79.87 1.27 1.54.84l10.06-6.86a1 1 0 0 0 0-1.68L9.54 4.3A1 1 0 0 0 8 5.14Z"/></svg>
                                </span>
                            </button>
                        @endif
                        @foreach ($images as $media)
                            <button type="button"
                                    x-on:click="activeImage = @js($media->getAvailableUrl(['card'])); showVideo = false"
                                    x-bind:class="! showVideo && activeImage === @js($media->getAvailableUrl(['card'])) ? 'border-emerald' : 'border-line hover:border-line-strong'"
                                    class="size-16 shrink-0 overflow-hidden rounded-[var(--radius-control)] border bg-paper"
                                    aria-label="{{ __('View image :number of :name', ['number' => $loop->iteration, 'name' => $name]) }}">
                                <img src="{{ $media->getAvailableUrl(['thumb']) }}" alt="{{ $name }}" class="size-full object-contain" loading="lazy">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- ===== Buy box ===== --}}
            <div>
                <h1 class="font-display text-[length:var(--text-h1)] font-normal leading-[1.12] tracking-[var(--tracking-head)] text-ink-head">{{ $name }}</h1>

                <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[13px] text-ink-soft">
                    @if ($product->rating_count > 0)
                        <span aria-hidden="true" class="text-ink">★</span>
                        <span class="tnum font-medium text-ink">{{ number_format((float) $product->rating_avg, 1) }}</span>
                        <span class="tnum">({{ number_format($product->rating_count) }})</span>
                        <span aria-hidden="true">·</span>
                    @endif
                    <span class="tnum">{{ $product->sold_count >= 1000 ? number_format($product->sold_count / 1000, 1).'k' : $product->sold_count }} {{ __('sold') }}</span>
                </div>


                {{-- Halal certificate — the reference puts this above the price,
                     because on a certificate-first marketplace it outranks it.
                     Mint tint + paired border are the tokens reserved for
                     certificate surfaces. Renders only when the SKU really
                     carries one; there is no placeholder state. --}}
                {{-- The verdict comes from Product::halalVerdict(), which is the
                     same predicate the register uses. Never re-derive it here:
                     a badge that read `valid_to` alone called 134 of 166
                     products verified that the register refused (2026-08-07). --}}
                @php
                    $cert = $product->halalCertificate;
                    $verdict = $product->halalVerdict();
                    $certBody = $cert?->issuing_body;
                    $certTone = match ($verdict) {
                        'verified' => ['border-emerald-tint-edge bg-emerald-tint', 'text-emerald', 'm4.5 12.75 6 6 9-13.5'],
                        'lapsed' => ['border-danger/30 bg-danger-tint', 'text-danger', 'M6 18 18 6M6 6l12 12'],
                        default => ['border-line bg-paper', 'text-ink-soft', 'M12 9v3.75m0 3.75h.008M10.34 3.94 2.6 17.4a1.6 1.6 0 0 0 1.4 2.4h15.98a1.6 1.6 0 0 0 1.4-2.4L13.66 3.94a1.6 1.6 0 0 0-2.8 0Z'],
                    };
                @endphp
                @if ($cert !== null)
                    <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-[var(--radius-panel)] border px-4 py-3 {{ $certTone[0] }}">
                        <svg class="size-4 shrink-0 {{ $certTone[1] }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $certTone[2] }}"/>
                        </svg>
                        <div class="min-w-0">
                            <p class="font-mono text-[length:var(--text-nano)] uppercase tracking-[var(--tracking-label-xl)] {{ $certTone[1] }}">
                                {{ match ($verdict) {
                                    'verified' => __('Halal certificate verified'),
                                    'lapsed' => __('Certificate lapsed'),
                                    default => __('Certificate not yet in force'),
                                } }}
                            </p>
                            <p class="mt-1 text-[length:var(--text-md)] text-ink-head">
                                @if ($certBody){{ $certBody }} <span aria-hidden="true" class="text-ink-faint">·</span> @endif
                                <span class="font-mono">{{ $cert->number }}</span>
                                <span aria-hidden="true" class="text-ink-faint">·</span>
                                {{ match ($verdict) {
                                    'lapsed' => __('expired :date', ['date' => $cert->valid_to->format('j M Y')]),
                                    'pending' => __('valid from :date', ['date' => $cert->valid_from->format('j M Y')]),
                                    default => __('valid to :date', ['date' => $cert->valid_to->format('j M Y')]),
                                } }}
                            </p>
                        </div>
                        @if ($cert)
                            <a href="{{ route('certificate.register', ['no' => $cert->number]) }}" wire:navigate
                               class="ml-auto shrink-0 rounded-[var(--radius-pill)] border border-line bg-surface px-4 py-2 text-[length:var(--text-xs)] text-ink transition-colors duration-(--dur-micro) hover:border-ink-head hover:text-ink-head">
                                {{ __('Read the record') }}
                            </a>
                        @endif
                    </div>
                @endif

                {{-- Price block --}}
                <div class="mt-4 rounded-[var(--radius-card)] bg-paper px-4 py-3">
                    @if ($variant !== null)
                        <div class="flex flex-wrap items-baseline gap-x-2.5 gap-y-1">
                            <span class="font-mono text-[28px] font-medium text-ink-head tnum">@price($variant->effectivePriceSen())</span>
                            @if ($variant->isOnSale())
                                <span class="font-mono text-[length:var(--text-md)] text-ink-faint line-through tnum">@price($variant->price_sen)</span>
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
                        <span class="font-mono text-[28px] font-medium text-ink-head tnum">@price($minSen)@if ($maxSen > $minSen) <span aria-hidden="true">–</span> @price($maxSen)@endif</span>
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
                                                class="inline-flex min-h-11 min-w-14 items-center justify-center rounded-[var(--radius-control)] border px-4 text-sm font-medium hb-press disabled:cursor-not-allowed disabled:opacity-40 {{ $selected ? 'border-emerald bg-emerald-tint text-emerald' : 'border-line-strong bg-surface text-ink hover:border-ink' }}">
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
                    {{-- Listing-only mode has nothing to add a quantity TO, so the
                         stepper goes; the stock badge and notify-me below stay, because
                         stock level is catalogue information and notify-me is not a sale. --}}
                    @if ($purchasingEnabled)
                    <span class="text-[13px] font-medium text-ink">{{ __('Quantity') }}</span>
                    <div class="inline-flex items-center rounded-full border border-line-strong">
                        <button type="button" wire:click="decrementQty"
                                @disabled($variant === null || $qty <= 1)
                                class="flex size-11 items-center justify-center rounded-l-full text-ink-soft hb-press [--press:0.9] hover:text-ink disabled:cursor-not-allowed disabled:opacity-40"
                                aria-label="{{ __('Decrease quantity') }}">−</button>
                        <span class="min-w-8 text-center font-mono text-sm font-medium tnum">{{ $qty }}</span>
                        <button type="button" wire:click="incrementQty"
                                @disabled($variant === null || $qty >= $variant->stock)
                                class="flex size-11 items-center justify-center rounded-r-full text-ink-soft hb-press [--press:0.9] hover:text-ink disabled:cursor-not-allowed disabled:opacity-40"
                                aria-label="{{ __('Increase quantity') }}">+</button>
                    </div>
                    @endif
                    @if ($variant !== null && $variant->stock > 0 && $variant->stock < 10)
                        <span class="text-[13px] font-medium text-warn">{{ __('Only :count left', ['count' => $variant->stock]) }}</span>
                    @elseif ($variant !== null && $variant->stock < 1)
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.badge variant="out-of-stock">{{ __('Out of stock') }}</x-ui.badge>
                            @if (in_array($variant->id, $subscribedVariantIds, true))
                                <span class="text-[13px] font-medium text-emerald">{{ __("We'll email you when it's back.") }}</span>
                            @else
                                <button type="button" wire:click="notifyWhenAvailable({{ $variant->id }})"
                                        wire:loading.attr="disabled" wire:target="notifyWhenAvailable"
                                        class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] border border-ink px-3 text-[13px] font-medium text-ink hover:bg-paper focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                    {{ __('Notify me when available') }}
                                </button>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Shipping row --}}
                <div class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px] text-ink-soft">
                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg>
                    <span>{{ __('Ships from :state', ['state' => $store?->state ?? 'Malaysia']) }}</span>
                    @if ($purchasingEnabled)
                        <span aria-hidden="true">·</span>
                        <span>{{ __('Shipping calculated at checkout') }}</span>
                    @endif
                </div>

                {{-- Ingredients & scope. The reference's wording explains WHY the
                     badge can be trusted for this item: the certificate's annex
                     names the SKU, so a shop-wide badge cannot cover it by
                     implication. The scope line is the certificate's own, not
                     marketing copy. --}}
                @if ($product->halalCertificate?->scope_note)
                    <div class="mt-6 border-t border-line pt-5">
                        <p class="font-display text-[length:var(--text-h4)] text-ink-head">{{ __('Ingredients & scope') }}</p>
                        <p class="mt-3 max-w-[62ch] text-[length:var(--text-base)] leading-relaxed text-ink-soft">
                            {{ __('Certified scope: :scope.', ['scope' => $product->halalCertificate->scope_note]) }}
                            {{ __('The certificate names this SKU in its annex, so the badge is bound to the item you are buying, not to the shop that sells it.') }}
                        </p>
                    </div>
                @endif

                {{-- Traceability — the reference's key/value table. Only rows the
                     data can actually answer are rendered: an empty row on a
                     compliance surface is worse than no row. --}}
                @if ($product->halalCertificate || $product->halal_batch_code)
                    @php $trace = $product->halalCertificate; @endphp
                    {{-- The heading sits outside the <dl> and each row is a direct
                         div child of it: a <p> is not allowed inside <dl>, and
                         dt/dd must be children of the dl or of one div under it.
                         They were two divs deep, which failed axe dlitem on every
                         row and leaves assistive tech without the term/definition
                         pairing this table is entirely made of. --}}
                    <div class="mt-6 border-t border-line pt-5">
                        <p class="font-display text-[length:var(--text-h4)] text-ink-head">{{ __('Traceability') }}</p>
                        <dl class="mt-4 space-y-3">
                            @foreach (array_filter([
                                __('Facility') => $trace?->facility,
                                __('Batch') => $product->halal_batch_code
                                    ? $product->halal_batch_code.($product->halal_packed_on ? ' · '.__('packed :date', ['date' => $product->halal_packed_on->format('j M Y')]) : '')
                                    : null,
                                __('Certificate') => $trace ? $trace->issuing_body.' · '.$trace->number : $product->halal_cert_number,
                                {{-- Reads the same $verdict as the badge above, so this row can
                                     never say "884 days remaining" about a certificate the
                                     badge is calling not-yet-in-force. --}}
                                __('Expiry') => $cert === null ? null : match ($verdict) {
                                    'lapsed' => $cert->valid_to->format('j M Y').' · '.__('lapsed'),
                                    'pending' => $cert->valid_to->format('j M Y').' · '.__('not in force until :date', ['date' => $cert->valid_from->format('j M Y')]),
                                    default => $cert->valid_to->format('j M Y').' · '.trans_choice('{1} :count day remaining|[2,*] :count days remaining', $cert->daysRemaining(), ['count' => $cert->daysRemaining()]),
                                },
                            ]) as $label => $value)
                                <div class="flex flex-wrap gap-x-6 gap-y-1">
                                    <dt class="w-28 shrink-0 font-mono text-[length:var(--text-nano)] uppercase tracking-[var(--tracking-label-xl)] text-ink-faint">{{ $label }}</dt>
                                    <dd class="min-w-0 flex-1 text-[length:var(--text-base)] text-ink">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endif

                {{-- Sold by --}}
                @if ($store)
                    <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-5">
                        <div>
                            <p class="font-display text-[length:var(--text-h4)] text-ink-head">{{ __('Sold by') }}</p>
                            <p class="mt-2 text-[length:var(--text-base)] text-ink-soft">
                                {{ $store->name }}
                                @if ($store->approved_at)
                                    <span aria-hidden="true" class="text-ink-faint">·</span> {{ __('audited seller since :year', ['year' => $store->approved_at->format('Y')]) }}
                                @endif
                                @if ($store->state)
                                    <span aria-hidden="true" class="text-ink-faint">·</span> {{ __('ships from :state', ['state' => $store->state]) }}
                                @endif
                            </p>
                        </div>
                        <a href="{{ $store->storefrontUrl() }}" wire:navigate class="shrink-0 text-[length:var(--text-base)] text-emerald underline-offset-4 hover:underline">{{ __('Storefront') }}</a>
                    </div>
                @endif

                {{-- Badges row --}}
                @if ($codAvailable)
                    <div class="mt-3 flex flex-wrap gap-2">
                        <x-ui.badge variant="cod">{{ __('Cash on delivery') }}</x-ui.badge>
                    </div>
                @endif

                {{-- Actions --}}
                @if ($purchasingEnabled)
                <div class="mt-6 hidden gap-3 lg:flex">
                    <button type="button"
                            data-testid="pdp-add-to-cart"
                            x-on:click="$store.cart.bump()"
                            wire:click="addToCart({{ $variant?->id ?? 0 }}, {{ $qty }})"
                            wire:loading.attr="disabled" wire:target="addToCart, buyNow"
                            @disabled(! $canBuy)
                            class="inline-flex min-h-11 flex-1 items-center justify-center rounded-[var(--radius-control)] border border-ink px-4 text-sm font-medium text-ink transition-[color,background-color,transform] duration-150 ease-out-soft hover:bg-paper active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50">
                        <span x-show="!justAdded">{{ __('Add to cart') }}</span>
                        <span x-show="justAdded" x-cloak class="inline-flex items-center gap-1.5">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            {{ __('Added') }}
                        </span>
                    </button>
                    <button type="button"
                            wire:click="buyNow"
                            wire:loading.attr="disabled" wire:target="addToCart, buyNow"
                            @disabled(! $canBuy)
                            class="inline-flex min-h-11 flex-1 items-center justify-center rounded-[var(--radius-control)] bg-emerald px-4 text-sm font-medium text-white transition-[color,background-color,transform] duration-150 ease-out-soft hover:bg-emerald-deep active:scale-[0.98] active:bg-emerald-night disabled:cursor-not-allowed disabled:opacity-50">
                        {{ __('Buy now') }}
                    </button>
                </div>
                @else
                    <div class="mt-6 rounded-[var(--radius-card)] border border-brass/40 bg-brass/10 p-4">
                        <p class="font-display text-[length:var(--text-h4)] text-ink-head">{{ __('Listing only') }}</p>
                        <p class="mt-1 text-[13px] text-ink-soft">{{ __('Purchasing is currently unavailable. Browse the product details or contact the seller for more information.') }}</p>
                    </div>
                @endif

                {{-- Outbound marketplace links. One block outside the two buy
                     regions, so it renders once and serves both breakpoints; the
                     mobile sticky bar is too cramped for it and duplicating it
                     there would repeat the pdp-add-to-cart testid collision.
                     The component decides its own visibility. --}}
                <x-marketplace-links :product="$product" />
                {{-- Mobile actions live in the sticky buy bar below --}}

                {{-- Group-buy / share-to-unlock (M2.6) --}}
                @if ($purchasingEnabled && config('groupbuy.enabled', true))
                    <livewire:storefront.group-buy.panel :product="$product" :wire:key="'gb-'.$product->id" />
                @endif

                {{-- Subscribe & save (M2.8) --}}
                @if ($purchasingEnabled && config('subscriptions.enabled', true))
                    <livewire:storefront.subscribe.panel :product="$product" :wire:key="'sub-'.$product->id" />
                @endif

                {{-- Halal & product details (M2.7) --}}
                <x-product-metafields :product="$product" />

                {{-- Seller card --}}
                @if ($store !== null)
                    <section class="mt-8 rounded-[var(--radius-card)] border border-line bg-surface p-4 shadow-soft" aria-label="{{ __('Seller') }}">
                        <div class="flex flex-wrap items-center gap-3">
                            {{-- Avatar and identity share one flex item so they stay on the
                                 same line and only Chat / Visit store wrap below. basis-full
                                 on the PAIR, not on the identity alone — putting it on the
                                 identity pushed the text under the avatar. --}}
                            <div class="flex min-w-0 flex-1 basis-full items-center gap-3 sm:basis-auto">
                            @if ($store->getFirstMediaUrl('logo'))
                                <img src="{{ $store->getFirstMediaUrl('logo') }}" alt="{{ $store->name }}"
                                     class="size-12 shrink-0 rounded-full border border-line object-contain bg-paper">
                            @else
                                <div class="flex size-12 shrink-0 items-center justify-center rounded-full border border-line bg-paper font-display text-lg font-medium text-ink-soft" aria-hidden="true">
                                    {{ mb_substr($store->name, 0, 1) }}
                                </div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="flex flex-wrap items-center gap-2">
                                    <a href="{{ $store->storefrontUrl() }}" wire:navigate class="truncate text-sm font-medium text-ink">{{ $store->name }}</a>
                                    @if ($store->isApproved())
                                        <x-ui.badge variant="verified">
                                            <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                            {{ __('Verified') }}
                                        </x-ui.badge>
                                    @endif
                                </p>
                                <p class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[13px] text-ink-soft">
                                    {{-- Each separator travels INSIDE the span of the value
                                         it precedes. As loose flex items they could land at
                                         the end of a wrapped line, which is how "12 products ·"
                                         ended a line with Selangor alone on the next. --}}
                                    @if ($store->rating_count > 0)
                                        <span class="flex items-center gap-1.5"><span aria-hidden="true">★</span><span class="tnum">{{ number_format((float) $store->rating_avg, 1) }} ({{ number_format($store->rating_count) }})</span></span>
                                    @endif
                                    @if ($store->service_rating_count > 0)
                                        <span class="flex items-center gap-1.5">@if ($store->rating_count > 0)<span aria-hidden="true">·</span>@endif<span>{{ __('Seller service') }} <span aria-hidden="true">★</span><span class="tnum">{{ number_format((float) $store->service_rating_avg, 1) }} ({{ number_format($store->service_rating_count) }})</span></span></span>
                                    @endif
                                    <span class="flex items-center gap-1.5">@if ($store->rating_count > 0 || $store->service_rating_count > 0)<span aria-hidden="true">·</span>@endif<span class="tnum">{{ number_format($storeProductsCount) }} {{ __('products') }}</span></span>
                                    @if ($store->state)
                                        <span class="flex items-center gap-1.5"><span aria-hidden="true">·</span><span>{{ $store->state }}</span></span>
                                    @endif
                                </p>
                            </div>
                            </div>
                            <a href="{{ auth()->check() ? route('account.messages', ['store' => $store->id, 'product' => $product->id]) : route('login') }}"
                               wire:navigate
                               data-testid="pdp-chat"
                               class="inline-flex min-h-11 items-center gap-1.5 rounded-[var(--radius-control)] px-3 text-sm font-medium text-ink-soft transition-colors duration-150 hover:text-ink">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/></svg>
                                {{ __('Chat') }}
                            </a>
                            <a href="{{ $store->storefrontUrl() }}" wire:navigate
                               class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-3 text-sm font-medium text-ink-soft transition-colors duration-150 hover:text-ink">
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
                        class="-mb-px min-h-11 shrink-0 border-b-2 px-4 text-sm font-medium transition-colors duration-150">
                    {{ __('Description') }}
                </button>
                <button type="button" role="tab" x-on:click="tab = 'specifications'"
                        x-bind:aria-selected="tab === 'specifications' ? 'true' : 'false'"
                        x-bind:class="tab === 'specifications' ? 'border-ink text-ink' : 'border-transparent text-ink-soft hover:text-ink'"
                        class="-mb-px min-h-11 shrink-0 border-b-2 px-4 text-sm font-medium transition-colors duration-150">
                    {{ __('Specifications') }}
                </button>
                <button type="button" role="tab" x-on:click="tab = 'reviews'"
                        x-bind:aria-selected="tab === 'reviews' ? 'true' : 'false'"
                        x-bind:class="tab === 'reviews' ? 'border-ink text-ink' : 'border-transparent text-ink-soft hover:text-ink'"
                        class="-mb-px min-h-11 shrink-0 border-b-2 px-4 text-sm font-medium transition-colors duration-150">
                    {{ __('Reviews') }}
                </button>
                <button type="button" role="tab" x-on:click="tab = 'questions'"
                        x-bind:aria-selected="tab === 'questions' ? 'true' : 'false'"
                        x-bind:class="tab === 'questions' ? 'border-ink text-ink' : 'border-transparent text-ink-soft hover:text-ink'"
                        class="-mb-px min-h-11 shrink-0 border-b-2 px-4 text-sm font-medium transition-colors duration-150">
                    {{ __('Q&A') }}
                </button>
            </div>

            <div x-show="tab === 'description'" role="tabpanel" class="max-w-prose space-y-3 py-5 text-sm leading-relaxed text-ink [&_h2]:font-display [&_h2]:text-lg [&_h2]:font-medium [&_li]:ml-5 [&_ol]:list-decimal [&_ul]:list-disc">
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

            <div x-show="tab === 'reviews'" x-cloak role="tabpanel" class="py-5">
                {{-- Lazy island: review queries run only when this scrolls/toggles into view --}}
                <livewire:storefront.product-reviews :product="$product" />
            </div>

            <div x-show="tab === 'questions'" x-cloak role="tabpanel" class="py-5">
                {{-- Lazy island: Q&A queries run only when this tab opens --}}
                <livewire:storefront.product-questions :product="$product" />
            </div>

            {{-- Review photo lightbox (true overlay — the only place shadows are allowed) --}}
            <div x-cloak x-show="lightbox" x-transition.opacity.duration.150ms
                 x-on:keydown.escape.window="lightbox = null"
                 x-on:click="lightbox = null"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-emerald-night/85 p-4"
                 role="dialog" aria-modal="true" aria-label="{{ __('Review photo') }}">
                <img x-bind:src="lightbox" alt="{{ __('Review photo, enlarged') }}" class="max-h-[85vh] max-w-full rounded-[var(--radius-card)] object-contain shadow-pop">
                <button type="button" x-on:click="lightbox = null"
                        class="absolute right-4 top-4 flex size-11 items-center justify-center rounded-full text-on-dark transition-colors duration-150 hover:bg-paper/10 focus-visible:ring-2 focus-visible:ring-emerald"
                        aria-label="{{ __('Close photo') }}">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </section>

        {{-- ===== Related products (lazy island — loads as it scrolls into view) ===== --}}
        <livewire:storefront.related-products :product="$product" />

        {{-- ===== Personalised recommendations (distinct from same-category related) ===== --}}
        <livewire:storefront.recommended-products context="pdp" :exclude-product-id="$product->id" />
    </div>

    {{-- ===== Mobile sticky buy bar (ink frame) ===== --}}
    <div data-hb-bottom-bar class="fixed inset-x-0 bottom-0 z-30 border-t border-emerald-edge bg-emerald-night shadow-pop lg:hidden">
        <div class="mx-auto flex max-w-7xl items-center gap-2 px-4 py-3">
            @if ($store !== null)
                <a href="{{ auth()->check() ? route('account.messages', ['store' => $store->id, 'product' => $product->id]) : route('login') }}"
                   wire:navigate
                   data-testid="pdp-chat-mobile"
                   class="flex size-11 shrink-0 items-center justify-center rounded-[var(--radius-control)] border border-paper/90 text-on-dark transition-colors duration-150 hover:bg-paper/10"
                   aria-label="{{ __('Chat with seller') }}">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/></svg>
                </a>
            @endif
            @if ($purchasingEnabled)
            <button type="button"
                    data-testid="pdp-add-to-cart"
                    x-on:click="$store.cart.bump()"
                    wire:click="addToCart({{ $variant?->id ?? 0 }}, {{ $qty }})"
                    wire:loading.attr="disabled" wire:target="addToCart, buyNow"
                    @disabled(! $canBuy)
                    class="inline-flex min-h-11 flex-1 items-center justify-center rounded-[var(--radius-control)] border border-paper px-3 text-sm font-medium text-on-dark transition-[color,background-color,transform] duration-150 ease-out-soft hover:bg-paper/10 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50">
                <span x-show="!justAdded">{{ __('Add to cart') }}</span>
                <span x-show="justAdded" x-cloak class="inline-flex items-center gap-1.5">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                    {{ __('Added') }}
                </span>
            </button>
            <button type="button"
                    wire:click="buyNow"
                    wire:loading.attr="disabled" wire:target="addToCart, buyNow"
                    @disabled(! $canBuy)
                    class="inline-flex min-h-11 flex-1 items-center justify-center rounded-[var(--radius-control)] bg-emerald px-3 text-sm font-medium text-white transition-[color,background-color,transform] duration-150 ease-out-soft hover:bg-emerald-deep active:scale-[0.98] active:bg-emerald-night disabled:cursor-not-allowed disabled:opacity-50">
                {{ __('Buy now') }}
            </button>
            @else
                @if ($store !== null)
                    <a href="{{ $store->storefrontUrl() }}" wire:navigate
                       class="inline-flex min-h-11 flex-1 items-center justify-center rounded-[var(--radius-control)] border border-paper px-3 text-sm font-medium text-on-dark hover:bg-paper/10">
                        {{ __('Visit store') }}
                    </a>
                @endif
                <span class="inline-flex min-h-11 flex-1 items-center justify-center rounded-[var(--radius-control)] bg-paper/10 px-3 text-center text-sm font-medium text-on-dark">
                    {{ __('Listing only') }}
                </span>
            @endif
        </div>
    </div>
</div>
