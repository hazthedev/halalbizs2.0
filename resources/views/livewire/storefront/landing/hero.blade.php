{{-- ===== Hero — halal-first pitch, dual CTA (buyer + seller) =====
     Dark ink panel with the house girih watermark, echoing the header/footer
     ink frames so the page reads as HalalBizs from the first scroll — not a
     generic marketing template bolted onto the storefront. Pure CSS reveal
     (`.reveal`, no JS gate), so content is visible even with scripts off. --}}
<section data-land="hero" class="surface-girih relative overflow-hidden border-b border-brass/25 bg-ink text-paper">
    <div class="relative mx-auto max-w-7xl px-4 py-16 sm:py-24">
        <div class="reveal max-w-2xl">
            <p data-motion="eyebrow" class="inline-flex items-center gap-2 rounded-full border border-brass/40 bg-paper/5 px-3.5 py-1.5 text-[13px] font-semibold text-brass-tint">
                <x-ui.star-mark :size="16" class="text-brass" data-motion="ornament" />
                {{ __('Halal-first, Malaysia-made') }}
            </p>

            <h1 class="mt-5 font-display text-4xl font-bold leading-[1.08] tracking-tight sm:text-5xl lg:text-6xl">
                {{ __('The marketplace built around what “halal” actually means to you.') }}
            </h1>

            <p data-motion="subcopy" class="mt-5 max-w-xl text-base leading-relaxed text-paper/80 sm:text-lg">
                {{ __('Shop thousands of halal-certified products from verified Malaysian sellers — or open your own store and reach buyers who shop with intention.') }}
            </p>

            <div data-motion="cta-row" class="mt-8 flex flex-wrap items-center gap-3">
                <x-ui.button variant="primary" :href="route('home')">
                    {{ __('Shop Now') }}
                </x-ui.button>
                <x-ui.button variant="ink-outline" :href="route('seller.apply')">
                    {{ __('Start Selling') }}
                </x-ui.button>
            </div>

            <ul class="mt-7 flex flex-wrap items-center gap-x-6 gap-y-2 text-[13px] text-paper/64">
                <li class="inline-flex items-center gap-1.5">
                    <svg class="size-4 shrink-0 text-brass" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    {{ __('Halal-certified sellers') }}
                </li>
                <li class="inline-flex items-center gap-1.5">
                    <svg class="size-4 shrink-0 text-brass" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 4.556-3.196 8.372-7.494 9.319a1 1 0 0 1-.512 0C8.696 20.372 5.5 16.556 5.5 12V7.087c0-.859.564-1.622 1.397-1.789 1.462-.29 3.126-.822 4.51-1.61a2.75 2.75 0 0 1 2.186 0c1.384.788 3.048 1.32 4.51 1.61.833.167 1.397.93 1.397 1.789V12Z"/></svg>
                    {{ __('Buyer protection on every order') }}
                </li>
                <li class="inline-flex items-center gap-1.5">
                    <svg class="size-4 shrink-0 text-brass" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
                    {{ __('Local Malaysian sellers') }}
                </li>
            </ul>
        </div>
    </div>
</section>
