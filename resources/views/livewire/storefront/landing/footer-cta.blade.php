{{-- ===== Footer CTA strip — final conversion push, bookends the hero =====
     Same ink + girih treatment as the hero, so the page opens and closes on
     the same note. --}}
{{-- -mb-12 swallows the site footer's mt-12: dark band meets dark footer with no
     ivory gap (the footer's own brass border-t stays as the divider). --}}
<section data-land="footer-cta" class="surface-girih -mb-12 border-t border-brass/25 bg-ink px-4 py-14 text-center text-paper sm:py-16">
    <div data-motion="item" class="mx-auto max-w-2xl">
        <h2 class="font-display text-3xl font-bold leading-tight sm:text-4xl">
            {{ __('Masuk, tengok dulu.') }}
        </h2>
        <p class="mt-4 text-base leading-relaxed text-paper/80">
            {{ __('Come in, take a look first — the souk is open, day or night, and there is always something worth finding.') }}
        </p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <x-ui.button variant="brass" :href="route('home')">
                {{ __('Shop Now') }}
            </x-ui.button>
        </div>
    </div>
</section>
