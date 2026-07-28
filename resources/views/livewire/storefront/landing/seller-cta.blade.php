{{-- ===== Seller CTA band — pitch to open a stall, benefits only =====
     No fee/commission numbers: those live in the seller onboarding flow, not
     a marketing page.

     The right column WAS three mini "product card" shapes built from styled
     divs — a fake product UI, which is the most recognisable AI-build tell
     there is, and it was pretending to show data the page does not have.
     Replaced with an actual stall front drawn in the hero's own language:
     the souk arch (same curve family as the hero skyline), the zellij field,
     and one hanging lantern reusing the hero's lantern geometry. It claims
     to be nothing other than ornament.

     Kept: the `data-plx` layers (landing.js gives them depth on scroll) and
     `hidden sm:block`, so this can never cause horizontal overflow on a
     phone. --}}
<section data-land="seller" class="border-y border-brass/20 bg-brass-tint/40 px-4 py-20 sm:py-24 lg:py-28">
    <div class="mx-auto grid max-w-7xl gap-10 sm:grid-cols-2 sm:items-center sm:gap-14">
        <div data-motion="item">
            <p class="inline-flex items-center gap-2 rounded-full border border-brass/40 bg-surface px-3.5 py-1.5 text-[13px] font-semibold text-brass-deep">
                <x-ui.star-mark :size="16" class="text-brass" />
                {{ __('For sellers') }}
            </p>
            <h2 class="mt-4 font-display text-3xl font-bold leading-tight text-ink sm:text-4xl">
                {{ __('Open your stall in the souk') }}
            </h2>
            <p class="mt-4 max-w-lg text-base leading-relaxed text-ink-soft">
                {{ __('Join a marketplace built for halal-conscious shoppers who are already looking for what you sell. Set up your stall in minutes, run it from one dashboard.') }}
            </p>
            <ul class="mt-6 space-y-2.5 text-sm text-ink">
                @foreach ([
                    __('Get discovered by buyers already searching for halal-certified products.'),
                    __('Run one dashboard for products, orders and payouts.'),
                    __('Sell with confidence. Every stall goes through halal-first review before launch.'),
                ] as $benefit)
                    <li class="flex items-start gap-2.5">
                        <span aria-hidden="true" class="mt-1 size-1.5 shrink-0 rounded-full bg-brass"></span>
                        {{ $benefit }}
                    </li>
                @endforeach
            </ul>
            <div class="mt-7">
                <x-ui.button variant="primary" :href="route('seller.apply')">
                    {{ __('Start Selling') }}
                </x-ui.button>
            </div>
        </div>

        {{-- The stall front. Decorative only.
             Sized to actually hold the column: the first pass drew the arch
             inside a 320-unit box at max-w-sm and it rendered as a small dark
             shape adrift in half a screen (screenshot 2026-07-28). The arch
             now fills its viewBox edge to edge, the box is wider and taller,
             and the interior carries the star field so it reads as the same
             night as the hero rather than a flat silhouette. --}}
        <div class="relative hidden h-[26rem] w-full max-w-md sm:mx-auto sm:block" aria-hidden="true">
            <div data-plx="0.9" class="pointer-events-none absolute inset-0">
                <svg viewBox="0 0 400 400" preserveAspectRatio="xMidYMid meet" class="h-full w-full">
                    <defs>
                        {{-- Same rub-el-hizb motif as the `surface-girih`
                             utility, as an SVG pattern so it can be clipped to
                             the arch instead of tiling a whole element. --}}
                        <pattern id="stall-girih" width="46" height="46" patternUnits="userSpaceOnUse">
                            <g fill="none" stroke="#F4EFE6" stroke-opacity="0.12" stroke-width="1">
                                <rect x="14" y="14" width="18" height="18" />
                                <rect x="14" y="14" width="18" height="18" transform="rotate(45 23 23)" />
                            </g>
                        </pattern>
                        <clipPath id="stall-arch">
                            <path d="M20,372 L20,170 Q200,10 380,170 L380,372 Z" />
                        </clipPath>
                    </defs>

                    <path d="M20,372 L20,170 Q200,10 380,170 L380,372 Z" fill="var(--color-emerald-night)" />
                    <g clip-path="url(#stall-arch)">
                        <rect x="0" y="0" width="400" height="400" fill="url(#stall-girih)" />
                        {{-- A few stars, same idea as the hero's field. --}}
                        @foreach ([[70,230,1.6],[150,300,1],[250,200,1.3],[330,270,1],[110,140,1],[300,120,1.4],[200,340,1]] as [$cx, $cy, $r])
                            <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}" fill="#FAF7F0" opacity="0.55" />
                        @endforeach
                    </g>
                    <path d="M20,372 L20,170 Q200,10 380,170 L380,372" fill="none"
                          stroke="var(--color-brass)" stroke-width="3" stroke-linecap="round" opacity="0.8" />
                    {{-- Counter line, so the arch reads as a stall rather than a doorway. --}}
                    <line x1="4" y1="374" x2="396" y2="374" stroke="var(--color-brass)" stroke-width="4" stroke-linecap="round" />
                </svg>
            </div>

            {{-- Hanging lantern, same geometry as the hero's. --}}
            <div data-plx="1.15" class="pointer-events-none absolute inset-0">
                <span class="souk-lantern absolute left-1/2 top-[18%] w-10 -translate-x-1/2">
                    <svg viewBox="0 0 40 64" class="w-full">
                        <path d="M20 2 L26 10 H14 Z" fill="var(--color-brass)" />
                        <rect x="10" y="10" width="20" height="6" rx="2" fill="var(--color-brass)" />
                        <path d="M8 18 Q20 12 32 18 L30 46 Q20 54 10 46 Z" fill="var(--color-brass)" />
                        <rect x="17" y="48" width="6" height="4" fill="var(--color-brass)" />
                        <path d="M18 52 L22 52 L20 60 Z" fill="var(--color-brass)" />
                        <circle cx="20" cy="32" r="4" fill="var(--color-brass-tint)" />
                    </svg>
                </span>
            </div>
        </div>
    </div>
</section>
