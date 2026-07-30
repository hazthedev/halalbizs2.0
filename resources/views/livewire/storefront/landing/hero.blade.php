{{-- Hero — 2-col asymmetric, ported from the reference.
     Left: brass rule + mono eyebrow, serif headline, one paragraph, two CTAs,
     three counted figures. Right: one photograph carrying the page's only
     shadow, with a certificate card overlapping its lower-left corner. That
     overlap is what makes the claim read as checked rather than asserted. --}}
<section class="border-b border-line bg-paper">
    <div class="mx-auto grid max-w-[1400px] items-center gap-12 px-4 py-16 lg:grid-cols-[1fr_1.05fr] lg:gap-16 lg:px-12 lg:py-24">

        <div>
            <p class="flex items-center gap-3 font-mono text-[length:var(--text-tiny)] uppercase tracking-[var(--tracking-label-xl)] text-ink-faint">
                <span aria-hidden="true" class="h-px w-8 bg-brass"></span>
                {{ __('Malaysia · Halal marketplace') }}
            </p>

            {{-- The reference headline, kept verbatim (Haze's call 2026-07-30).
                 It states the RULE of the marketplace instead of a benefit, and
                 that is the whole idea of the design. The register + expiry watch
                 that make it literally true are phase 2. --}}
            <h1 class="mt-6 max-w-[15ch] font-display text-[clamp(2.25rem,4.6vw,2.875rem)] font-light leading-[1.08] tracking-[var(--tracking-hero)] text-ink-head">
                {{ __('Nothing is listed here until the certificate checks out.') }}
            </h1>

            <p class="mt-6 max-w-[52ch] text-[length:var(--text-base)] leading-relaxed text-ink-soft">
                {{ __('HalalBizs is the certificate-first marketplace for halal food, cosmetics, supplements and modest living. Every brand is audited, and every product carries the certificate it was listed under.') }}
            </p>

            <div class="mt-8 flex flex-wrap items-center gap-3">
                <a href="{{ route('search') }}" wire:navigate
                   class="rounded-[var(--radius-pill)] bg-emerald px-6 py-3 text-[length:var(--text-base)] font-medium text-white transition-colors duration-(--dur-micro) hover:bg-emerald-deep">
                    {{ __('Browse verified catalogue') }}
                </a>
                <a href="{{ route('seller.apply') }}" wire:navigate
                   class="rounded-[var(--radius-pill)] border border-line-strong px-6 py-3 text-[length:var(--text-base)] text-ink transition-colors duration-(--dur-micro) hover:border-ink-head hover:text-ink-head">
                    {{ __('Sell on HalalBizs') }}
                </a>
            </div>

            {{-- Counted from the database, never claimed. --}}
            <dl class="mt-10 flex flex-wrap gap-x-14 gap-y-6 border-t border-line pt-8">
                @foreach ([
                    ['value' => $stats['products'], 'label' => __('Certified listings')],
                    ['value' => $stats['stores'], 'label' => __('Audited sellers')],
                    ['value' => $stats['bodies'], 'label' => __('Recognised bodies')],
                ] as $figure)
                    <div>
                        <dt class="font-mono text-[28px] font-medium leading-none text-ink-head tnum">{{ number_format($figure['value']) }}</dt>
                        <dd class="mt-2 text-[length:var(--text-tiny)] uppercase tracking-[var(--tracking-brand)] text-ink-faint">{{ $figure['label'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <div class="relative">
            {{-- The page's ONLY shadow: green-tinted, large negative spread, so it
                 lifts rather than drops. fetchpriority=high because this is LCP. --}}
            <img src="{{ asset('images/landing/hero-lineup.webp') }}"
                 alt="{{ __('Halal-certified Malaysian grocery products arranged on a stone ledge') }}"
                 width="1536" height="1024" fetchpriority="high" decoding="async"
                 class="w-full rounded-[24px] object-cover shadow-[var(--shadow-lift)]">

            <div class="absolute -bottom-6 left-4 max-w-[19rem] rounded-[var(--radius-panel)] border border-line bg-surface p-4 sm:left-8">
                <p class="flex items-center gap-1.5 font-mono text-[length:var(--text-nano)] uppercase tracking-[var(--tracking-label-xl)] text-emerald">
                    <svg class="size-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                    {{ __('Certificate live') }}
                </p>
                <p class="mt-2 text-[length:var(--text-md)] text-ink-head">{{ __('JAKIM certified · MS 1500:2019') }}</p>
                <p class="mt-1 font-mono text-[length:var(--text-tiny)] text-ink-faint">{{ __('Bound to the SKU, not to the shop') }}</p>
            </div>
        </div>
    </div>
</section>
