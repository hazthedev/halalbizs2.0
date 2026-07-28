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

        {{-- The stall front. Decorative only. --}}
        <div class="relative hidden h-80 w-full max-w-sm sm:mx-auto sm:block" aria-hidden="true">
            <div data-plx="0.9" class="pointer-events-none absolute inset-0">
                <svg viewBox="0 0 320 320" class="h-full w-full overflow-visible">
                    <defs>
                        {{-- Same rub-el-hizb motif as the `surface-girih`
                             utility, as an SVG pattern so it can be clipped
                             to the arch instead of tiling a whole element. --}}
                        <pattern id="stall-girih" width="46" height="46" patternUnits="userSpaceOnUse">
                            <g fill="none" stroke="#F4EFE6" stroke-opacity="0.10" stroke-width="1">
                                <rect x="14" y="14" width="18" height="18" />
                                <rect x="14" y="14" width="18" height="18" transform="rotate(45 23 23)" />
                            </g>
                        </pattern>
                        <clipPath id="stall-arch">
                            <path d="M46,306 L46,148 Q160,26 274,148 L274,306 Z" />
                        </clipPath>
                    </defs>

                    <path d="M46,306 L46,148 Q160,26 274,148 L274,306 Z" fill="var(--color-emerald-night)" />
                    <g clip-path="url(#stall-arch)">
                        <rect x="0" y="0" width="320" height="320" fill="url(#stall-girih)" />
                    </g>
                    <path d="M46,306 L46,148 Q160,26 274,148 L274,306" fill="none"
                          stroke="var(--color-brass)" stroke-width="2.5" stroke-linecap="round" opacity="0.75" />
                    {{-- Counter line, so the arch reads as a stall rather than a doorway. --}}
                    <line x1="30" y1="306" x2="290" y2="306" stroke="var(--color-brass)" stroke-width="3" stroke-linecap="round" />
                </svg>
            </div>

            {{-- Hanging lantern, same geometry as the hero's. --}}
            <div data-plx="1.15" class="pointer-events-none absolute inset-0">
                <span class="souk-lantern absolute left-1/2 top-[26%] w-8 -translate-x-1/2">
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
