@php
    // Words are split server-side from the *translated* string (not the raw
    // English key) so a later JS stagger works against real markup and any
    // locale's own word order/count — never a text-splitting library.
    $heroWords = explode(' ', __('The souk never closes.'));

    // The marquee never shows an empty ticker: fall back to the same
    // representative names the categories section below uses when nothing
    // is seeded yet.
    $marqueeNames = $categories->isNotEmpty()
        ? $categories->map(fn ($category) => $category->getTranslation('name', app()->getLocale()))
        : collect([
            __('Groceries & Pantry'), __('Fashion & Apparel'), __('Beauty & Personal Care'), __('Home & Living'),
            __('Health & Wellness'), __('Baby & Kids'), __('Books & Stationery'), __('Electronics & Gadgets'),
        ]);

    // A short track is a VISIBLE track: the loop renders the list twice for a
    // seamless wrap, so with only 3 live categories both copies fit on one
    // 1440 screen and the reader sees "Fashion ... Fashion" and reads it as a
    // bug (live 2026-07-28). Pad the base list to at least 8 entries first,
    // so one wrap is always wider than the viewport.
    $minMarqueeItems = 8;
    while ($marqueeNames->count() < $minMarqueeItems && $marqueeNames->isNotEmpty()) {
        $marqueeNames = $marqueeNames->concat($marqueeNames->all());
    }
@endphp

{{-- ===== Hero — "the souk never closes" =====
     Deep emerald night sky, back to front: stars → skyline silhouette →
     bobbing brass lanterns → foreground vignette, then content, then the
     category marquee bookending the section. Every decorative layer is
     `absolute inset-0`, `aria-hidden` and `pointer-events-none`; only the
     inner lantern/marquee elements carry motion (never the section root or
     the `data-plx` wrappers themselves, so a later JS parallax pass can own
     the wrapper's transform without fighting CSS for it). Content sits in
     normal flow above everything and needs zero JS to read. --}}
<section data-land="hero" class="relative isolate flex min-h-[100svh] flex-col overflow-hidden bg-emerald-night text-paper">

    {{-- Layer 1 — star field, farthest back --}}
    <div aria-hidden="true" data-plx="0.15" class="souk-stars absolute inset-0 pointer-events-none"></div>

    {{-- Layer 2 — shophouse/arch skyline silhouette --}}
    <div aria-hidden="true" data-plx="0.35" class="absolute inset-0 pointer-events-none">
        <svg viewBox="0 0 1440 320" preserveAspectRatio="none" class="absolute inset-x-0 bottom-0 h-[42%] w-full" aria-hidden="true">
            <path fill="#052821" d="M0,320 L0,180 L60,180 L60,140 L120,140 L120,180 L180,180 L180,120
                Q210,80 240,120 L240,180 L300,180 L300,150 L360,150 L360,180 L420,180 L420,100
                Q450,60 480,100 L480,180 L540,180 L540,160 L600,160 L600,180 L660,180 L660,90
                Q690,50 720,90 L720,180 L780,180 L780,140 L840,140 L840,180 L900,180 L900,110
                Q930,70 960,110 L960,180 L1020,180 L1020,150 L1080,150 L1080,180 L1140,180 L1140,95
                Q1170,55 1200,95 L1200,180 L1260,180 L1260,160 L1320,160 L1320,180 L1380,180 L1380,140 L1440,140 L1440,320 Z" />
        </svg>
    </div>

    {{-- Layer 3 — bobbing brass lanterns --}}
    <div aria-hidden="true" data-plx="0.6" class="absolute inset-0 pointer-events-none">
        {{-- Positions are utility classes, not inline styles, so responsive
             variants can override them (inline style would always win). The
             mid-field lanterns overlap the headline/subcopy once the text
             column widens relative to the viewport (seen live at 375/768) —
             below lg the two big ones RELOCATE to flank the scroll cue at the
             hero's base instead of hiding (hiding left mobile with almost no
             visible motion, Haze 2026-07-24); only the smallest is lg-only. --}}
        {{-- Positions keep the lanterns OUT of the text column at every width
             (2026-07-28): the centred content is max-w-4xl, so at lg the
             lanterns live in the outer margins (under ~14% / over ~80%), and
             below lg they all drop into the empty band between the CTAs and
             the marquee. Previously one sat at left-[44%] and landed on the
             headline at desktop, and two crossed the eyebrow pill at 390. --}}
        @foreach ([
            ['pos' => 'top-[18%] left-[6%] max-lg:top-[72%] max-lg:left-[8%]', 'w' => 'w-7', 'delay' => '0s', 'dur' => '4.6s'],
            ['pos' => 'top-[12%] left-[88%] max-lg:top-[69%] max-lg:left-[79%]', 'w' => 'w-6', 'delay' => '.9s', 'dur' => '5.3s'],
            ['pos' => 'top-[47%] left-[12%] max-lg:top-[84%] max-lg:left-[26%]', 'w' => 'w-9', 'delay' => '1.6s', 'dur' => '4.9s'],
            ['pos' => 'top-[34%] left-[81%] max-lg:top-[85%] max-lg:left-[62%]', 'w' => 'w-6', 'delay' => '.4s', 'dur' => '5.7s'],
            ['pos' => 'top-[63%] left-[91%] hidden lg:inline-block', 'w' => 'w-5', 'delay' => '2.1s', 'dur' => '4.3s'],
        ] as $lantern)
            <span class="souk-lantern absolute {{ $lantern['w'] }} {{ $lantern['pos'] }}" style="animation-delay: {{ $lantern['delay'] }}; animation-duration: {{ $lantern['dur'] }};">
                <svg viewBox="0 0 40 64" class="w-full">
                    <path d="M20 2 L26 10 H14 Z" fill="var(--color-brass)" />
                    <rect x="10" y="10" width="20" height="6" rx="2" fill="var(--color-brass)" />
                    <path d="M8 18 Q20 12 32 18 L30 46 Q20 54 10 46 Z" fill="var(--color-brass)" />
                    <rect x="17" y="48" width="6" height="4" fill="var(--color-brass)" />
                    <path d="M18 52 L22 52 L20 60 Z" fill="var(--color-brass)" />
                    <circle cx="20" cy="32" r="4" fill="var(--color-brass-tint)" />
                </svg>
            </span>
        @endforeach
    </div>

    {{-- Foreground vignette — no data-plx, purely a static contrast aid --}}
    <div aria-hidden="true" class="souk-vignette absolute inset-0 pointer-events-none"></div>

    {{-- Content --}}
    <div class="relative mx-auto flex w-full max-w-4xl flex-1 flex-col items-center justify-center px-4 pt-24 pb-10 text-center sm:pt-28">
        <p data-motion="eyebrow" class="inline-flex items-center gap-2 rounded-full border border-brass/40 bg-paper/5 px-3.5 py-1.5 text-[13px] font-semibold uppercase tracking-[0.08em] text-brass-tint">
            <x-ui.star-mark :size="16" class="text-brass" />
            {{ __('Halal-first, always open') }}
        </p>

        {{-- .souk-shine sits on each WORD, not the h1: clip-to-text on the
             parent does not paint through the transformed inline-block spans
             (anime's transform residue puts them on their own layer — live
             bite 2026-07-25, headline rendered invisible). An element's own
             clipped background always paints its own glyphs. The per-word
             delay makes the glint travel across the line. --}}
        <h1 class="mt-6 font-display text-[clamp(3rem,8vw,6rem)] font-bold leading-[1.05] tracking-tight">
            @foreach ($heroWords as $word)
                <span class="souk-shine inline-block" data-motion="word" style="animation-delay: {{ 2.4 + $loop->index * 0.15 }}s">{{ $word }}</span>
            @endforeach
        </h1>

        <p data-motion="subcopy" class="mt-6 max-w-xl text-base leading-relaxed text-paper/80 sm:text-lg">
            {{ __('Thousands of halal-certified products from verified Malaysian sellers, day or night. Or open your own stall and reach buyers who shop with intention.') }}
        </p>

        <div data-motion="cta-row" class="mt-9 flex flex-wrap items-center justify-center gap-3">
            <x-ui.button variant="brass" :href="route('home')">
                {{ __('Shop Now') }}
            </x-ui.button>
            <x-ui.button variant="ink-outline" :href="route('seller.apply')">
                {{ __('Start Selling') }}
            </x-ui.button>
        </div>

        {{-- The "Scroll" cue that used to sit here is gone: a visitor looking
             at an unscrolled hero already knows what scrolling is, and the
             label was the only uppercase micro-tell left on the page. The
             marquee below is the affordance now. --}}
    </div>

    {{-- Category marquee — bookends the hero. Two identical tracks side by
         side inside one flex row; the row animates translateX(-50%) so the
         duplicate lands exactly where the original started (seamless loop).
         Reduced motion drops the animation entirely, leaving one static,
         clipped row (`overflow-hidden` on the viewport). --}}
    <div class="souk-marquee relative z-10 overflow-hidden border-t border-brass/20 bg-emerald-night/70 py-3">
        <div class="souk-marquee-track flex w-max items-center whitespace-nowrap text-sm font-semibold tracking-[0.02em] text-brass-tint">
            @foreach ([false, true] as $isDuplicate)
                <div class="flex items-center gap-8 px-4" @if ($isDuplicate) aria-hidden="true" @endif>
                    @foreach ($marqueeNames as $name)
                        <span class="inline-flex items-center gap-8">
                            <span>{{ $name }}</span>
                            <span aria-hidden="true" class="text-brass">✦</span>
                        </span>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
</section>
