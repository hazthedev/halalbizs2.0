{{-- ===== Seller CTA band — pitch to open a store, benefits only =====
     No fee/commission numbers — those live in the seller onboarding flow,
     not a marketing page. The CTA button itself stays emerald (it's an
     action), even though the band is brass-toned (premium/ornament). --}}
<section data-land="seller" class="border-y border-brass/20 bg-brass-tint/40 px-4 py-14 sm:py-20">
    <div class="mx-auto grid max-w-7xl gap-10 sm:grid-cols-2 sm:items-center sm:gap-12">
        <div>
            <p class="inline-flex items-center gap-2 rounded-full border border-brass/40 bg-surface px-3.5 py-1.5 text-[13px] font-semibold text-brass-deep">
                <x-ui.star-mark :size="16" class="text-brass" />
                {{ __('For sellers') }}
            </p>
            <h2 class="mt-4 font-display text-3xl font-bold leading-tight text-ink sm:text-4xl">
                {{ __('Open your store on HalalBizs') }}
            </h2>
            <p class="mt-4 max-w-lg text-base leading-relaxed text-ink-soft">
                {{ __('Join a marketplace built for halal-conscious shoppers who are already looking for what you sell.') }}
            </p>
            <div class="mt-7">
                <x-ui.button variant="primary" :href="route('seller.apply')">
                    {{ __('Start Selling') }}
                </x-ui.button>
            </div>
        </div>

        <ul class="space-y-4">
            <li class="flex items-start gap-3 rounded-[var(--radius-card)] border border-line bg-surface p-4 shadow-soft">
                <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-brass-tint text-brass">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                </span>
                <span class="text-sm leading-relaxed text-ink">{{ __('Get discovered by buyers already searching for halal-certified products.') }}</span>
            </li>
            <li class="flex items-start gap-3 rounded-[var(--radius-card)] border border-line bg-surface p-4 shadow-soft">
                <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-brass-tint text-brass">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25"/></svg>
                </span>
                <span class="text-sm leading-relaxed text-ink">{{ __('Run your shop from one seller dashboard — products, orders, payouts.') }}</span>
            </li>
            <li class="flex items-start gap-3 rounded-[var(--radius-card)] border border-line bg-surface p-4 shadow-soft">
                <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-brass-tint text-brass">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </span>
                <span class="text-sm leading-relaxed text-ink">{{ __('Sell with confidence — every store goes through halal-first review before launch.') }}</span>
            </li>
        </ul>
    </div>
</section>
