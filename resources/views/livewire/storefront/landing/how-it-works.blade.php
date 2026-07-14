{{-- ===== How it works — buyer journey, 3 steps =====
     Brass numerals (ornament), not emerald — these mark sequence, not action. --}}
<section data-land="how" class="mx-auto max-w-7xl px-4 py-14 sm:py-20">
    <x-ui.section-heading
        as="h2"
        :title="__('How buying works')"
        :subtitle="__('From browsing to your doorstep, in three steps.')"
    />

    <div class="mt-8 grid gap-8 sm:mt-10 sm:grid-cols-3 sm:gap-6">
        <div data-motion="item">
            <span class="flex size-9 items-center justify-center rounded-full bg-brass font-display text-base font-bold text-white">1</span>
            <h3 class="mt-4 font-display text-lg font-semibold text-ink">{{ __('Browse & discover') }}</h3>
            <p class="mt-2 text-sm leading-relaxed text-ink-soft">
                {{ __('Explore halal-certified products across fashion, food, beauty, home and more — all from one marketplace.') }}
            </p>
        </div>

        <div data-motion="item">
            <span class="flex size-9 items-center justify-center rounded-full bg-brass font-display text-base font-bold text-white">2</span>
            <h3 class="mt-4 font-display text-lg font-semibold text-ink">{{ __('Checkout securely') }}</h3>
            <p class="mt-2 text-sm leading-relaxed text-ink-soft">
                {{ __('Pay online or choose cash on delivery — every order is protected from checkout to delivery.') }}
            </p>
        </div>

        <div data-motion="item">
            <span class="flex size-9 items-center justify-center rounded-full bg-brass font-display text-base font-bold text-white">3</span>
            <h3 class="mt-4 font-display text-lg font-semibold text-ink">{{ __('Track & receive') }}</h3>
            <p class="mt-2 text-sm leading-relaxed text-ink-soft">
                {{ __('Follow your parcel in real time, then rate the seller so other buyers know what to expect.') }}
            </p>
        </div>
    </div>
</section>
