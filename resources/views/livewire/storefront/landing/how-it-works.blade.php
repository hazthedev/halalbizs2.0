{{-- ===== How buying works — vertical step rail =====
     Was a 3-column row, which made it the third consecutive 3-across
     section and also read worst on mobile (three stacked columns with no
     sense of sequence). A vertical rail says "these happen in order" in a
     way a row never does, and it collapses to mobile unchanged.

     `#how-path` keeps its id and `.how-path` class: landing.js scrubs its
     stroke-dasharray to scroll progress, so the line now draws DOWNWARD
     through the steps as you read them. The path renders fully drawn with
     no JS, and reduced-motion leaves it that way.

     Brass numerals mark sequence, not action, so they stay brass. --}}
<section data-land="how" class="mx-auto max-w-3xl px-4 py-20 sm:py-24 lg:py-28">
    <x-ui.section-heading
        as="h2"
        :title="__('How buying works')"
        :subtitle="__('From browsing to your doorstep, in three steps.')"
    />

    <ol class="relative mt-10 sm:mt-12">
        {{-- The rail. Sits behind the numerals (z-0 vs z-10) and stops short
             of the last numeral so the line never dangles past step 3. --}}
        <svg viewBox="0 0 2 100" preserveAspectRatio="none" aria-hidden="true"
             class="pointer-events-none absolute left-[17px] top-4 -z-0 h-[calc(100%-5rem)] w-0.5 sm:left-[19px]">
            <path id="how-path" class="how-path" d="M1,0 L1,100"
                  stroke="var(--color-brass)" stroke-width="2" fill="none" stroke-linecap="round" opacity="0.45" />
        </svg>

        @foreach ([
            [
                'title' => __('Browse the souk'),
                'body' => __('Explore halal-certified products across fashion, food, beauty, home and more, all from one marketplace.'),
            ],
            [
                'title' => __('Pay, protected'),
                'body' => __('Pay online or choose cash on delivery. Every order is protected from checkout through to delivery.'),
            ],
            [
                'title' => __('Delivered to your door'),
                'body' => __('Follow your parcel in real time, then rate the seller so other buyers know what to expect.'),
            ],
        ] as $step)
            <li data-motion="item" class="relative flex gap-5 pb-10 last:pb-0 sm:gap-6 sm:pb-12">
                <span class="relative z-10 flex size-9 shrink-0 items-center justify-center rounded-full bg-brass font-display text-base font-bold text-white shadow-soft sm:size-10">
                    {{ $loop->iteration }}
                </span>
                <div class="pt-1">
                    <h3 class="font-display text-lg font-semibold text-ink sm:text-xl">{{ $step['title'] }}</h3>
                    <p class="mt-2 max-w-xl text-sm leading-relaxed text-ink-soft sm:text-base">{{ $step['body'] }}</p>
                </div>
            </li>
        @endforeach
    </ol>
</section>
