{{-- ===== How it works — buyer journey, 3 steps =====
     Brass numerals (ornament), not emerald — these mark sequence, not action.
     `#how-path` is a fully-drawn decorative curve behind the row (a later
     task animates its stroke-dasharray on scroll); it renders complete and
     correct with no JS, and sits behind the content via z-index so it never
     crosses text. --}}
<section data-land="how" class="mx-auto max-w-7xl px-4 py-14 sm:py-20">
    <x-ui.section-heading
        as="h2"
        :title="__('How buying works')"
        :subtitle="__('From browsing to your doorstep, in three steps.')"
    />

    <div class="relative mt-8 sm:mt-10">
        <svg viewBox="0 0 1200 100" preserveAspectRatio="none" class="pointer-events-none absolute inset-x-0 top-3 hidden h-16 w-full sm:block" aria-hidden="true">
            <path id="how-path" class="how-path" d="M20,70 C 250,10 350,90 600,50 S 950,10 1180,60"
                  stroke="var(--color-brass)" stroke-width="2.5" fill="none" stroke-linecap="round" opacity="0.5" />
        </svg>

        <div class="relative z-10 grid gap-8 sm:grid-cols-3 sm:gap-6">
            <div data-motion="item">
                <span class="flex size-9 items-center justify-center rounded-full bg-brass font-display text-base font-bold text-white">1</span>
                <h3 class="mt-4 font-display text-lg font-semibold text-ink">{{ __('Browse the souk') }}</h3>
                <p class="mt-2 text-sm leading-relaxed text-ink-soft">
                    {{ __('Explore halal-certified products across fashion, food, beauty, home and more — all from one marketplace.') }}
                </p>
            </div>

            <div data-motion="item">
                <span class="flex size-9 items-center justify-center rounded-full bg-brass font-display text-base font-bold text-white">2</span>
                <h3 class="mt-4 font-display text-lg font-semibold text-ink">{{ __('Pay, protected') }}</h3>
                <p class="mt-2 text-sm leading-relaxed text-ink-soft">
                    {{ __('Pay online or choose cash on delivery — every order is protected from checkout to delivery.') }}
                </p>
            </div>

            <div data-motion="item">
                <span class="flex size-9 items-center justify-center rounded-full bg-brass font-display text-base font-bold text-white">3</span>
                <h3 class="mt-4 font-display text-lg font-semibold text-ink">{{ __('Delivered to your door') }}</h3>
                <p class="mt-2 text-sm leading-relaxed text-ink-soft">
                    {{ __('Follow your parcel in real time, then rate the seller so other buyers know what to expect.') }}
                </p>
            </div>
        </div>
    </div>
</section>
