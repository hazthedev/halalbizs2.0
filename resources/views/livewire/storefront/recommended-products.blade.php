<div @if ($needsHydration) x-data x-init="$wire.loadViewed(window.recentlyViewed?.all() ?? [])" @endif>
    @if ($products->isNotEmpty())
        <section class="mt-10" aria-label="{{ __('Recommended for you') }}">
            <h2 class="font-display text-xl font-bold">{{ __('Recommended for you') }}</h2>
            <div class="mt-4 flex gap-3 overflow-x-auto pb-2">
                @foreach ($products as $item)
                    <div class="w-44 shrink-0 sm:w-48" wire:key="rec-{{ $context }}-{{ $item->id }}">
                        <x-product-card :product="$item" :wishlisted="in_array($item->id, $wishlistedIds, true)" />
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
