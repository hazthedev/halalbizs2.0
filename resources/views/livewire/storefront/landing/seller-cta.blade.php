{{-- ===== Seller CTA band — pitch to open a stall, benefits only =====
     No fee/commission numbers — those live in the seller onboarding flow,
     not a marketing page. Right column is a purely decorative stack of 3
     mini "product card" shapes (CSS, not real data) — each in its own
     `data-plx` layer for a later depth pass, hidden below `sm` so nothing
     here can ever cause horizontal overflow on small screens. --}}
<section data-land="seller" class="border-y border-brass/20 bg-brass-tint/40 px-4 py-14 sm:py-20">
    <div class="mx-auto grid max-w-7xl gap-10 sm:grid-cols-2 sm:items-center sm:gap-12">
        <div data-motion="item">
            <p class="inline-flex items-center gap-2 rounded-full border border-brass/40 bg-surface px-3.5 py-1.5 text-[13px] font-semibold text-brass-deep">
                <x-ui.star-mark :size="16" class="text-brass" />
                {{ __('For sellers') }}
            </p>
            <h2 class="mt-4 font-display text-3xl font-bold leading-tight text-ink sm:text-4xl">
                {{ __('Open your stall in the souk') }}
            </h2>
            <p class="mt-4 max-w-lg text-base leading-relaxed text-ink-soft">
                {{ __('Join a marketplace built for halal-conscious shoppers who are already looking for what you sell. Set up your stall in minutes, run it from one dashboard.') }}
            </p>
            <ul class="mt-6 space-y-2.5 text-sm text-ink">
                <li class="flex items-start gap-2.5">
                    <span aria-hidden="true" class="mt-1 size-1.5 shrink-0 rounded-full bg-brass"></span>
                    {{ __('Get discovered by buyers already searching for halal-certified products.') }}
                </li>
                <li class="flex items-start gap-2.5">
                    <span aria-hidden="true" class="mt-1 size-1.5 shrink-0 rounded-full bg-brass"></span>
                    {{ __('Run your shop from one seller dashboard — products, orders, payouts.') }}
                </li>
                <li class="flex items-start gap-2.5">
                    <span aria-hidden="true" class="mt-1 size-1.5 shrink-0 rounded-full bg-brass"></span>
                    {{ __('Sell with confidence — every stall goes through halal-first review before launch.') }}
                </li>
            </ul>
            <div class="mt-7">
                <x-ui.button variant="primary" :href="route('seller.apply')">
                    {{ __('Start Selling') }}
                </x-ui.button>
            </div>
        </div>

        <div class="relative hidden h-72 w-full max-w-sm sm:mx-auto sm:block" aria-hidden="true">
            <div data-plx="0.85" class="pointer-events-none absolute inset-0">
                <div class="absolute left-2 top-10 w-40 -rotate-6 rounded-[var(--radius-card)] border border-line bg-surface p-3 shadow-card">
                    <div class="aspect-square w-full rounded-lg bg-emerald-tint"></div>
                    <div class="mt-2 h-2 w-3/4 rounded bg-line"></div>
                    <div class="mt-1.5 h-2 w-1/2 rounded bg-line"></div>
                </div>
            </div>
            <div data-plx="1" class="pointer-events-none absolute inset-0">
                <div class="absolute left-1/2 top-4 w-44 -translate-x-1/2 rotate-2 rounded-[var(--radius-card)] border border-brass/30 bg-surface p-3 shadow-pop">
                    <div class="aspect-square w-full rounded-lg bg-brass-tint"></div>
                    <div class="mt-2 h-2 w-2/3 rounded bg-line"></div>
                    <div class="mt-1.5 h-2 w-1/3 rounded bg-line"></div>
                </div>
            </div>
            <div data-plx="1.15" class="pointer-events-none absolute inset-0">
                <div class="absolute right-2 top-16 w-36 rotate-[10deg] rounded-[var(--radius-card)] border border-line bg-surface p-3 shadow-card">
                    <div class="aspect-square w-full rounded-lg bg-emerald-tint"></div>
                    <div class="mt-2 h-2 w-3/5 rounded bg-line"></div>
                    <div class="mt-1.5 h-2 w-2/5 rounded bg-line"></div>
                </div>
            </div>
        </div>
    </div>
</section>
