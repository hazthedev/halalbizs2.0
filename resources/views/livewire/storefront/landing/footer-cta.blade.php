{{-- ===== Footer CTA strip — final conversion push, bookends the hero =====
     Same ink + girih treatment as the hero, so the page opens and closes on
     the same note. --}}
<section data-land="footer-cta" class="surface-girih border-t border-brass/25 bg-ink px-4 py-14 text-center text-paper sm:py-16">
    <div class="mx-auto max-w-2xl">
        <h2 class="font-display text-3xl font-bold leading-tight sm:text-4xl">
            {{ __('Ready to shop the halal-first way?') }}
        </h2>
        <p class="mt-4 text-base leading-relaxed text-paper/80">
            {{ __('Join buyers and sellers building Malaysia’s trusted multi-vendor marketplace, one verified store at a time.') }}
        </p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <x-ui.button variant="primary" :href="route('home')">
                {{ __('Shop Now') }}
            </x-ui.button>
            <x-ui.button variant="ink-outline" :href="route('seller.apply')">
                {{ __('Start Selling') }}
            </x-ui.button>
        </div>
    </div>
</section>
