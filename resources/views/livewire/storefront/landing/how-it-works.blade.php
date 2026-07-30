{{-- The dark band. This is the ONLY inverted section on the page and the only
     place white text lives, which is exactly how the reference uses it. Cards
     inside sit on the lighter dark-band surface with their own edge colour, so
     the contrast comes from surface shift rather than from a shadow.

     ⚠ Steps 01-03 describe what the app does TODAY. Step 04 describes the
     expiry watch, which is phase 2 and is labelled as coming rather than
     claimed as live -- the page must not promise a check that does not run. --}}
<section class="bg-emerald-night">
    <div class="mx-auto grid max-w-[1400px] gap-12 px-4 py-20 lg:grid-cols-[0.9fr_1.1fr] lg:px-12">

        <div>
            <p class="font-mono text-[length:var(--text-tiny)] uppercase tracking-[var(--tracking-label-xl)] text-brass">{{ __('How verification works') }}</p>
            <h2 class="mt-5 max-w-[18ch] font-display text-[clamp(1.75rem,3vw,2rem)] font-light leading-[1.15] tracking-[var(--tracking-head)] text-white">
                {{ __('Four checks between a seller and your basket.') }}
            </h2>
            <p class="mt-5 max-w-[46ch] text-[length:var(--text-base)] leading-relaxed text-on-dark-soft">
                {{ __('Sellers upload the certificate, not a claim. It is tied to the specific products it names, and it travels with the listing all the way to your order.') }}
            </p>
            <a href="{{ route('seller.apply') }}" wire:navigate
               class="mt-8 inline-block rounded-[var(--radius-pill)] border border-on-dark px-6 py-3 text-[length:var(--text-base)] text-on-dark transition-colors duration-(--dur-micro) hover:bg-on-dark hover:text-emerald-night">
                {{ __('Sell on HalalBizs') }}
            </a>
        </div>

        <ol class="space-y-3">
            @foreach ([
                ['n' => '01', 'title' => __('Document intake'),
                 'body' => __('The certificate, its issuing body and its expiry date are captured when the product is listed.'), 'soon' => false],
                ['n' => '02', 'title' => __('Body recognised'),
                 'body' => __('We record which authority issued it — JAKIM, MUIS, BPJPH or ESMA — and show that on the listing.'), 'soon' => false],
                ['n' => '03', 'title' => __('Scope binding'),
                 'body' => __('The certificate is attached to the SKU it names. A shop-wide badge never covers a product the annex leaves out.'), 'soon' => false],
                ['n' => '04', 'title' => __('Expiry watch'),
                 'body' => __('Automatic de-listing the moment a certificate lapses, with a renewal reminder to the seller before it does.'), 'soon' => true],
            ] as $step)
                <li class="flex gap-4 rounded-[var(--radius-panel)] border border-emerald-edge bg-emerald-card p-5">
                    <span class="font-mono text-[length:var(--text-base)] font-medium text-brass" aria-hidden="true">{{ $step['n'] }}</span>
                    <div>
                        <h3 class="flex flex-wrap items-center gap-2 text-[length:var(--text-md)] font-medium text-white">
                            {{ $step['title'] }}
                            @if ($step['soon'])
                                <span class="rounded-[var(--radius-pill)] border border-brass/50 px-2 py-0.5 font-mono text-[length:var(--text-nano)] uppercase tracking-[var(--tracking-label)] text-brass">{{ __('In build') }}</span>
                            @endif
                        </h3>
                        <p class="mt-1.5 max-w-[52ch] text-[length:var(--text-sm)] leading-relaxed text-on-dark-soft">{{ $step['body'] }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</section>
