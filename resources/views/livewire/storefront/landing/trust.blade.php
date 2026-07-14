{{-- ===== Trust band — halal curation, buyer protection, local sellers =====
     Brass carries the ornament here (icon roundels), never the action — the
     cards themselves have no CTA, so nothing here needed emerald. --}}
<section data-land="trust" class="mx-auto max-w-7xl px-4 py-14 sm:py-20">
    <x-ui.section-heading
        as="h2"
        :title="__('Why shop HalalBizs')"
        :subtitle="__('Every store on the platform is reviewed before it goes live.')"
    />

    <div class="mt-8 grid gap-4 sm:mt-10 sm:grid-cols-3 sm:gap-6">
        <div data-motion="item" class="rounded-[var(--radius-card)] border border-line bg-surface p-6 shadow-soft hb-lift">
            <span class="flex size-11 items-center justify-center rounded-full bg-brass-tint text-brass">
                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
            </span>
            <h3 class="mt-4 font-display text-lg font-semibold text-ink">{{ __('Halal-first curation') }}</h3>
            <p class="mt-2 text-sm leading-relaxed text-ink-soft">
                {{ __('Listings are reviewed for halal status before they ever reach the shelf, so you never have to guess.') }}
            </p>
        </div>

        <div data-motion="item" class="rounded-[var(--radius-card)] border border-line bg-surface p-6 shadow-soft hb-lift">
            <span class="flex size-11 items-center justify-center rounded-full bg-brass-tint text-brass">
                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 4.556-3.196 8.372-7.494 9.319a1 1 0 0 1-.512 0C8.696 20.372 5.5 16.556 5.5 12V7.087c0-.859.564-1.622 1.397-1.789 1.462-.29 3.126-.822 4.51-1.61a2.75 2.75 0 0 1 2.186 0c1.384.788 3.048 1.32 4.51 1.61.833.167 1.397.93 1.397 1.789V12Z"/></svg>
            </span>
            <h3 class="mt-4 font-display text-lg font-semibold text-ink">{{ __('Buyer protection') }}</h3>
            <p class="mt-2 text-sm leading-relaxed text-ink-soft">
                {{ __('Secure checkout, order tracking and a support team you can actually reach if something goes wrong.') }}
            </p>
        </div>

        <div data-motion="item" class="rounded-[var(--radius-card)] border border-line bg-surface p-6 shadow-soft hb-lift">
            <span class="flex size-11 items-center justify-center rounded-full bg-brass-tint text-brass">
                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
            </span>
            <h3 class="mt-4 font-display text-lg font-semibold text-ink">{{ __('Local Malaysian sellers') }}</h3>
            <p class="mt-2 text-sm leading-relaxed text-ink-soft">
                {{ __('Every store is run by a Malaysian seller — support small business with every ringgit you spend.') }}
            </p>
        </div>
    </div>
</section>
