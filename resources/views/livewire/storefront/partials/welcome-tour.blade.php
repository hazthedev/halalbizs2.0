{{-- One-time welcome tour (storefront home only) — Alpine-only, no server
     round-trips. localStorage 'hb_tour_done' gates it; Esc/skip/finish all
     mark it done. Focus moves into the dialog on open ("trapped-ish"),
     transitions are motion-safe (design §7/§9). --}}
<div x-data="{
        tourOpen: false,
        tourStep: 1,
        {{-- navigator.webdriver guard keeps browser-automation journeys deterministic; ?tour forces it for the tour's own journey. Kept in a method (not x-init) because Alpine wraps directive bodies in `return (...)`, so a bare `try {}` statement there is a syntax error. --}}
        startTour() {
            try {
                const force = new URLSearchParams(window.location.search).has('tour');
                if ((force || ! navigator.webdriver) && ! localStorage.getItem('hb_tour_done')) {
                    this.tourOpen = true;
                    this.$nextTick(() => this.$refs.tourPrimary?.focus());
                }
            } catch (e) {}
        },
        finishTour() {
            this.tourOpen = false;
            try { localStorage.setItem('hb_tour_done', '1') } catch (e) {}
        },
     }"
     x-init="startTour()"
     x-on:keydown.escape.window="if (tourOpen) finishTour()"
     x-show="tourOpen"
     x-cloak
     class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
     role="dialog"
     aria-modal="true"
     aria-label="{{ __('Welcome to HalalBizs') }}">

    <div class="absolute inset-0 bg-ink/50" x-on:click="finishTour()" aria-hidden="true"></div>

    {{-- Overlay surface — shadow permitted --}}
    <div x-show="tourOpen"
         x-transition:enter="motion-safe:transition motion-safe:duration-(--dur-standard) motion-safe:ease-out-soft"
         x-transition:enter-start="opacity-0 motion-safe:translate-y-4"
         x-transition:enter-end="opacity-100 motion-safe:translate-y-0"
         x-transition:leave="motion-safe:transition motion-safe:duration-200 motion-safe:ease-in-soft"
         x-transition:leave-end="opacity-0"
         class="relative w-full max-w-sm rounded-[var(--radius-card)] border border-line bg-surface p-6 shadow-pop">

        <p class="text-[11px] font-semibold uppercase tracking-[0.04em] text-ink-soft">
            {{ __('Welcome to HalalBizs') }} · <span x-text="tourStep" class="tnum"></span>/3
        </p>

        {{-- Step 1: search --}}
        <div x-show="tourStep === 1">
            <h2 class="mt-2 font-display text-xl font-bold">{{ __('Find anything fast') }}</h2>
            <p class="mt-2 text-sm leading-relaxed text-ink-soft">
                {{ __('Search products, stores and categories from the bar at the top — or press') }}
                <kbd class="rounded border border-line px-1.5 font-mono text-[11px] text-ink">/</kbd>
                {{ __('anywhere to open search.') }}
            </p>
        </div>

        {{-- Step 2: cart + checkout --}}
        <div x-show="tourStep === 2" x-cloak>
            <h2 class="mt-2 font-display text-xl font-bold">{{ __('One cart, every seller') }}</h2>
            <p class="mt-2 text-sm leading-relaxed text-ink-soft">
                {{ __('Add items from any store — your cart keeps them grouped by seller and you check out everything in one go.') }}
            </p>
        </div>

        {{-- Step 3: become a seller --}}
        <div x-show="tourStep === 3" x-cloak>
            <h2 class="mt-2 font-display text-xl font-bold">{{ __('Sell on HalalBizs') }}</h2>
            <p class="mt-2 text-sm leading-relaxed text-ink-soft">
                {{ __('Got products of your own? Apply for a free store via “Become a seller” in the footer.') }}
            </p>
        </div>

        {{-- Progress dots --}}
        <div class="mt-4 flex gap-1.5" aria-hidden="true">
            <template x-for="dot in 3" :key="dot">
                <span class="h-1.5 w-6 rounded-full" x-bind:class="dot <= tourStep ? 'bg-emerald' : 'bg-line'"></span>
            </template>
        </div>

        <div class="mt-5 flex items-center justify-between gap-2">
            <button type="button"
                    x-on:click="finishTour()"
                    class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-3 text-sm font-medium text-ink-soft transition-colors duration-150 hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">
                {{ __('Skip tour') }}
            </button>
            <div class="flex items-center gap-2">
                <button type="button"
                        x-show="tourStep > 1"
                        x-cloak
                        x-on:click="tourStep--"
                        class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] border border-ink px-4 text-sm font-semibold text-ink transition-colors duration-150 hover:bg-paper focus-visible:ring-2 focus-visible:ring-emerald">
                    {{ __('Back') }}
                </button>
                <button type="button"
                        x-ref="tourPrimary"
                        x-on:click="tourStep < 3 ? tourStep++ : finishTour()"
                        class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] bg-emerald px-5 text-sm font-semibold text-white transition-colors duration-150 hover:bg-emerald-deep active:bg-emerald-night focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-2">
                    <span x-show="tourStep < 3">{{ __('Next') }}</span>
                    <span x-show="tourStep === 3" x-cloak>{{ __('Start shopping') }}</span>
                </button>
            </div>
        </div>
    </div>
</div>
