{{-- ===== Stats band — real cached counts, plain numbers =====
     No JS here: `data-countup`/`data-target` are hooks for a later motion
     task. The number rendered as text IS the real value — nothing to wait on. --}}
<section data-land="stats" class="surface-zellij border-y border-line bg-brass-tint/30 px-4 py-12 sm:py-16">
    <div class="mx-auto grid max-w-7xl grid-cols-1 gap-8 text-center sm:grid-cols-3">
        <div>
            <p data-countup data-target="{{ $stats['stores'] }}" class="tnum font-display text-4xl font-bold text-ink sm:text-5xl">
                {{ number_format($stats['stores']) }}
            </p>
            <p class="mt-2 text-sm font-medium text-ink-soft">{{ __('Active local sellers') }}</p>
        </div>
        <div>
            <p data-countup data-target="{{ $stats['products'] }}" class="tnum font-display text-4xl font-bold text-ink sm:text-5xl">
                {{ number_format($stats['products']) }}
            </p>
            <p class="mt-2 text-sm font-medium text-ink-soft">{{ __('Products listed') }}</p>
        </div>
        <div>
            <p data-countup data-target="{{ $stats['categories'] }}" class="tnum font-display text-4xl font-bold text-ink sm:text-5xl">
                {{ number_format($stats['categories']) }}
            </p>
            <p class="mt-2 text-sm font-medium text-ink-soft">{{ __('Categories to explore') }}</p>
        </div>
    </div>
</section>
