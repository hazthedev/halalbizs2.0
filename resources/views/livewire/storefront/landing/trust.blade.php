{{-- Three-point trust rail on the cream tile. Brass 01/02/03 numerals, no
     icons, no cards, no borders — the reference deliberately leaves this
     section flat, which is why it reads as a statement rather than a feature
     grid. Each point names a MECHANISM, not an adjective. --}}
<section class="border-b border-line bg-cream">
    <div class="mx-auto grid max-w-[1400px] gap-10 px-4 py-14 md:grid-cols-3 lg:px-12">
        @foreach ([
            ['n' => '01', 'title' => __('Certificate before catalogue'),
             'body' => __('A listing cannot go live without a readable certificate, its issuing body and an expiry date we can monitor.')],
            ['n' => '02', 'title' => __('Bound to the item'),
             'body' => __('The certificate is attached to the SKU you are buying, not to the shop that sells it, so a broad shop badge cannot cover a narrow product.')],
            ['n' => '03', 'title' => __('Expiry in the open'),
             'body' => __('Every listing shows the certificate number and the date it runs out, on the product page and in your basket.')],
        ] as $point)
            <div class="flex gap-4">
                <span class="font-mono text-[length:var(--text-base)] font-medium text-brass" aria-hidden="true">{{ $point['n'] }}</span>
                <div>
                    <h2 class="font-display text-[length:var(--text-h4)] text-ink-head">{{ $point['title'] }}</h2>
                    <p class="mt-2 max-w-[42ch] text-[length:var(--text-sm)] leading-relaxed text-ink-soft">{{ $point['body'] }}</p>
                </div>
            </div>
        @endforeach
    </div>
</section>
