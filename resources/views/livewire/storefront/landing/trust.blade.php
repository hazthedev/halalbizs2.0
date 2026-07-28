{{-- ===== Trust — asymmetric editorial split =====
     Was three identical icon-roundel cards, which is the stock marketing row
     and made this the first of four consecutive 3-across sections. Now a
     5/7 split: the claim carries the left column, the proofs are a divided
     list on the right. No card boxes — a hairline between rows groups them
     just as well and costs no elevation this hierarchy needs.
     Brass stays ornament (the marks); nothing here is actionable, so
     nothing here is emerald. --}}
<section data-land="trust" class="mx-auto max-w-7xl px-4 py-20 sm:py-24 lg:py-28">
    <div class="grid gap-10 lg:grid-cols-12 lg:gap-16">

        <div class="lg:col-span-5">
            <h2 class="font-display text-3xl font-bold leading-[1.15] tracking-tight text-ink sm:text-4xl">
                {{ __('Every store is reviewed before it opens.') }}
            </h2>
            <p class="mt-5 max-w-md text-base leading-relaxed text-ink-soft">
                {{ __('Halal-first is the entry requirement here, not a filter you switch on afterwards.') }}
            </p>
        </div>

        <ul class="divide-y divide-line lg:col-span-7">
            @foreach ([
                [
                    'title' => __('Halal-first curation'),
                    'body' => __('Listings are reviewed for halal status before they ever reach the shelf, so you never have to guess.'),
                ],
                [
                    'title' => __('Your money, held safely'),
                    'body' => __('We hold your payment until delivery. The seller is only paid once your order actually arrives.'),
                ],
                [
                    'title' => __('Local Malaysian sellers'),
                    'body' => __('Every store is run by a Malaysian seller, so each ringgit you spend supports a small business here.'),
                ],
            ] as $proof)
                <li data-motion="item" class="flex gap-5 py-6 first:pt-0 last:pb-0 sm:gap-6 sm:py-7">
                    <x-ui.star-mark :size="20" class="mt-1 shrink-0 text-brass" />
                    <div>
                        <h3 class="font-display text-lg font-semibold text-ink">{{ $proof['title'] }}</h3>
                        <p class="mt-1.5 max-w-xl text-sm leading-relaxed text-ink-soft">{{ $proof['body'] }}</p>
                    </div>
                </li>
            @endforeach
        </ul>

    </div>
</section>
