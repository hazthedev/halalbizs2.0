{{-- Newly verified — the freshness proof. Reuses the shared product card, so
     this row is guaranteed to match the catalogue rather than drifting from it. --}}
@if ($newlyVerified->isNotEmpty())
    <section class="bg-paper">
        <div class="mx-auto max-w-[1400px] px-4 pb-16 lg:px-12">
            <div class="flex flex-wrap items-end justify-between gap-3 border-t border-line pt-14">
                <div>
                    <h2 class="font-display text-[length:var(--text-h2)] text-ink-head">{{ __('Newly verified') }}</h2>
                    <p class="mt-2 text-[length:var(--text-base)] text-ink-soft">{{ __('The most recent listings to clear their certificate check.') }}</p>
                </div>
                <a href="{{ route('search') }}" wire:navigate class="text-[length:var(--text-base)] text-emerald transition-colors duration-(--dur-micro) hover:text-emerald-deep">
                    {{ __('View all :count', ['count' => number_format($stats['products'])]) }}
                </a>
            </div>

            <div class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($newlyVerified as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        </div>
    </section>
@endif
