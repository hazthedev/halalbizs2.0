<div>
    @if ($related->isNotEmpty())
        <section class="mt-10" aria-label="{{ __('Related products') }}">
            <x-ui.section-heading :title="__('Related products')" />
            <div class="mt-4 flex gap-3 overflow-x-auto pb-2">
                @foreach ($related as $item)
                    <div class="w-44 shrink-0 sm:w-48" wire:key="related-{{ $item->id }}">
                        <x-product-card :product="$item" :wishlisted="in_array($item->id, $wishlistedIds, true)" />
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
