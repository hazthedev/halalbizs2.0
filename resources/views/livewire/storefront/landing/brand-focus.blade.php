{{-- Brand in focus — a split card, photo left, content right. Proves a real
     seller exists behind the system, which is the job this section does in the
     reference. The store, its counts and its listings are all read live; if no
     approved seller has listings yet the section simply does not render. --}}
@if ($featuredStore)
    <section class="bg-paper">
        <div class="mx-auto max-w-[1400px] px-4 py-16 lg:px-12">
            <div class="grid overflow-hidden rounded-[var(--radius-card)] border border-line bg-surface md:grid-cols-2">
                <img src="{{ asset('images/landing/shelf-band.webp') }}"
                     alt="{{ __('A spread of halal-certified products from a Malaysian seller') }}"
                     width="1536" height="595" loading="lazy" decoding="async"
                     class="h-full w-full object-cover">

                <div class="p-8 lg:p-12">
                    <p class="font-mono text-[length:var(--text-tiny)] uppercase tracking-[var(--tracking-label-xl)] text-ink-faint">{{ __('Brand in focus') }}</p>

                    <h2 class="mt-4 font-display text-[length:var(--text-h2)] text-ink-head">
                        {{ $featuredStore->name }}@if ($featuredStore->state)<span class="text-ink-faint">, {{ $featuredStore->state }}</span>@endif
                    </h2>

                    <p class="mt-4 max-w-[46ch] text-[length:var(--text-base)] leading-relaxed text-ink-soft">
                        {{ $featuredStore->description ?: __('An audited seller on HalalBizs, listing under its own certificate.') }}
                    </p>

                    <dl class="mt-8 flex flex-wrap gap-x-12 gap-y-5">
                        @foreach ([
                            ['value' => number_format($featuredStore->listing_count), 'label' => __('Certified listings')],
                            ['value' => $featuredStore->rating_count > 0 ? number_format((float) $featuredStore->rating_avg, 1) : '—', 'label' => __('Seller rating')],
                            ['value' => optional($featuredStore->approved_at)->format('Y') ?? '—', 'label' => __('On HalalBizs since')],
                        ] as $figure)
                            <div>
                                <dt class="font-mono text-[length:var(--text-h3)] font-medium leading-none text-ink-head tnum">{{ $figure['value'] }}</dt>
                                <dd class="mt-2 text-[length:var(--text-tiny)] uppercase tracking-[var(--tracking-brand)] text-ink-faint">{{ $figure['label'] }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    <a href="{{ route('store.show', $featuredStore->slug) }}" wire:navigate
                       class="mt-8 inline-block rounded-[var(--radius-pill)] bg-emerald px-6 py-3 text-[length:var(--text-base)] font-medium text-white transition-colors duration-(--dur-micro) hover:bg-emerald-deep">
                        {{ __('Visit the storefront') }}
                    </a>
                </div>
            </div>
        </div>
    </section>
@endif
