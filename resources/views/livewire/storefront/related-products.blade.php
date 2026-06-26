<div>
    @if ($boughtTogether->isNotEmpty())
        <section class="mt-10" aria-label="{{ __('Frequently bought together') }}">
            <div class="flex items-center justify-between gap-3">
                <x-ui.section-heading :title="__('Frequently bought together')" />
                <button type="button" wire:click="addAllBoughtTogether"
                        wire:loading.attr="disabled" wire:target="addAllBoughtTogether"
                        class="inline-flex min-h-11 shrink-0 items-center rounded-[var(--radius-control)] border border-ink px-3 text-[13px] font-semibold text-ink hover:bg-paper focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                    {{ __('Add all to cart') }}
                </button>
            </div>
            <div class="mt-4 flex gap-3 overflow-x-auto pb-2">
                @foreach ($boughtTogether as $item)
                    <div class="w-44 shrink-0 sm:w-48" wire:key="fbt-{{ $item->id }}">
                        <x-product-card :product="$item" :wishlisted="in_array($item->id, $wishlistedIds, true)" />
                    </div>
                @endforeach
            </div>
        </section>
    @endif

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
