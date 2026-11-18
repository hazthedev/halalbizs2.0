@if ($data->isNotEmpty())
    <section class="mx-auto max-w-7xl px-4 pt-12 sm:pt-16">
        @if ($title)
            <x-ui.section-heading :title="$title" class="motion-reveal"
                                  x-data="{ shown: false }" x-intersect.once="shown = true" x-bind:class="shown && 'revealed'" />
        @endif
        <div class="no-scrollbar -mx-4 mt-4 flex snap-x gap-3 overflow-x-auto px-4 pb-2 sm:mt-6">
            @foreach ($data as $product)
                <div class="motion-reveal w-44 shrink-0 snap-start sm:w-48" wire:key="recent-{{ $product->id }}"
                     x-data="{ shown: false }" x-intersect.once="shown = true" x-bind:class="shown && 'revealed'"
                     style="animation-delay: {{ min($loop->index * 40, 320) }}ms">
                    <x-product-card :product="$product" :wishlisted="in_array($product->id, $wishlistedIds)" />
                </div>
            @endforeach
        </div>
    </section>
@endif
