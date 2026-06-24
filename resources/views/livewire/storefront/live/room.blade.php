<div wire:poll.15s class="mx-auto w-full max-w-7xl px-4 py-6">
    @php($featured = $session->featuredProduct)
    @php($isLive = $session->status === \App\Enums\LiveSessionStatus::Live)

    <div class="flex flex-wrap items-center gap-3">
        @if ($isLive)
            <span class="inline-flex items-center gap-1.5 rounded-full bg-danger px-2.5 py-1 text-[11px] font-bold uppercase tracking-[0.06em] text-white">
                <span class="size-1.5 animate-pulse rounded-full bg-white"></span>{{ __('Live') }}
            </span>
        @else
            <x-ui.badge variant="neutral">{{ $session->status->label() }}</x-ui.badge>
        @endif
        <h1 class="font-display text-xl font-bold">{{ $session->title }}</h1>
        <a href="{{ route('store.show', $session->store->slug) }}" wire:navigate class="text-[13px] font-semibold text-emerald hover:text-emerald-deep">{{ $session->store?->name }}</a>
    </div>

    <div class="mt-4 grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px] lg:items-start">
        {{-- Stage --}}
        <div>
            <div class="surface-girih relative flex aspect-video items-center justify-center overflow-hidden rounded-[var(--radius-card)] border border-brass/25 bg-ink">
                @if ($embedUrl)
                    <iframe src="{{ $embedUrl }}" class="absolute inset-0 size-full" frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen
                            title="{{ $session->title }}"></iframe>
                @else
                    <div class="text-center text-paper/70">
                        <x-ui.star-mark :size="44" class="mx-auto text-brass/60" />
                        <p class="mt-2 text-sm">{{ $isLive ? __('The stream is on — browse the picks alongside.') : __('Stream hasn’t started yet.') }}</p>
                    </div>
                @endif
            </div>

            {{-- Spotlight --}}
            @if ($featured)
                @php($variant = $featured->variants->firstWhere('is_default', true) ?? $featured->variants->first())
                @php($minSen = $featured->variants->isNotEmpty() ? $featured->variants->map->effectivePriceSen()->min() : 0)
                <div class="mt-4 flex flex-wrap items-center gap-4 rounded-[var(--radius-card)] border border-emerald/40 bg-emerald-tint/40 p-4">
                    <span class="size-20 shrink-0 overflow-hidden rounded-[var(--radius-card)] bg-paper">
                        @if ($featured->getFirstMediaUrl('images', 'thumb'))
                            <img src="{{ $featured->getFirstMediaUrl('images', 'thumb') }}" alt="" class="size-full object-cover">
                        @endif
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.06em] text-emerald">{{ __('Featured now') }}</p>
                        <a href="{{ route('product.show', $featured->slug) }}" wire:navigate class="line-clamp-1 text-sm font-semibold text-ink hover:text-emerald">{{ $featured->getTranslation('name', app()->getLocale()) }}</a>
                        <p class="mt-0.5 text-lg font-bold text-ink tnum">@price($minSen)</p>
                    </div>
                    @if ($variant && $featured->variants->count() === 1)
                        <button type="button" x-on:click="$store.cart.bump()" wire:click="addToCart({{ $variant->id }})"
                                class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-[var(--radius-control)] bg-emerald px-5 text-sm font-semibold text-white hover:bg-emerald-deep">
                            {{ __('Add to cart') }}
                        </button>
                    @else
                        <a href="{{ route('product.show', $featured->slug) }}" wire:navigate class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-[var(--radius-control)] border border-emerald bg-surface px-5 text-sm font-semibold text-emerald hover:bg-emerald hover:text-white">{{ __('Choose options') }}</a>
                    @endif
                </div>
            @endif
        </div>

        {{-- Sidebar: voucher + rail + sold feed --}}
        <div class="space-y-4">
            @if ($session->voucher_code)
                <div class="flex items-center justify-between gap-2 rounded-[var(--radius-card)] border border-dashed border-brass/50 bg-brass/10 px-4 py-3">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.06em] text-brass-deep">{{ __('Live voucher') }}</p>
                        <p class="font-mono text-sm font-bold text-ink">{{ $session->voucher_code }}</p>
                    </div>
                    <span class="shrink-0 text-[13px] text-ink-soft">{{ __('Apply at checkout') }}</span>
                </div>
            @endif

            {{-- Just sold feed --}}
            @if ($sold->isNotEmpty())
                <div class="rounded-[var(--radius-card)] border border-line bg-surface p-3 shadow-soft">
                    <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.06em] text-ink-faint">{{ __('Just sold') }}</p>
                    <ul class="space-y-1.5">
                        @foreach ($sold as $sale)
                            <li class="flex items-center gap-2 text-[13px] text-ink-soft">
                                <span class="size-1.5 shrink-0 rounded-full bg-emerald"></span>
                                <span class="min-w-0 truncate"><span class="font-medium text-ink">{{ $sale['buyer'] }}</span> {{ __('bought') }} {{ $sale['product'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Product rail --}}
            <div class="rounded-[var(--radius-card)] border border-line bg-surface p-3 shadow-soft">
                <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.06em] text-ink-faint">{{ __('Shop the stream') }}</p>
                <ul class="space-y-2">
                    @foreach ($session->products as $product)
                        @php($variant = $product->variants->firstWhere('is_default', true) ?? $product->variants->first())
                        @php($minSen = $product->variants->isNotEmpty() ? $product->variants->map->effectivePriceSen()->min() : 0)
                        <li class="flex items-center gap-2.5 rounded-[var(--radius-control)] border border-line p-2">
                            <a href="{{ route('product.show', $product->slug) }}" wire:navigate class="size-12 shrink-0 overflow-hidden rounded-lg bg-paper">
                                @if ($product->getFirstMediaUrl('images', 'thumb'))
                                    <img src="{{ $product->getFirstMediaUrl('images', 'thumb') }}" alt="" class="size-full object-cover">
                                @endif
                            </a>
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('product.show', $product->slug) }}" wire:navigate class="line-clamp-1 text-[13px] font-medium text-ink hover:text-emerald">{{ $product->getTranslation('name', app()->getLocale()) }}</a>
                                <p class="text-[13px] font-bold text-ink tnum">@price($minSen)</p>
                            </div>
                            @if ($variant && $product->variants->count() === 1)
                                <button type="button" x-on:click="$store.cart.bump()" wire:click="addToCart({{ $variant->id }})"
                                        class="inline-flex size-9 shrink-0 items-center justify-center rounded-[var(--radius-control)] bg-emerald text-white hover:bg-emerald-deep" aria-label="{{ __('Add to cart') }}">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                </button>
                            @else
                                <a href="{{ route('product.show', $product->slug) }}" wire:navigate class="inline-flex size-9 shrink-0 items-center justify-center rounded-[var(--radius-control)] border border-line-strong text-ink hover:border-emerald hover:text-emerald" aria-label="{{ __('View') }}">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
