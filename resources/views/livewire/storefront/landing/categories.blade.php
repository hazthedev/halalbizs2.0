{{-- ===== Category showcase — real top-level categories, capped at 8 =====
     Falls back to a static, non-linking preview when the table is empty
     (fresh install, pre-seed) so the page never shows a hole. --}}
<section data-land="categories" class="border-t border-line bg-surface/60 px-4 py-14 sm:py-20">
    <div class="mx-auto max-w-7xl">
        <x-ui.section-heading
            as="h2"
            :title="__('Shop by category')"
            :subtitle="__('A snapshot of what Malaysian halal sellers are stocking right now.')"
            :href="$categories->isNotEmpty() ? route('search') : null"
            :link-label="__('Browse all')"
        />

        @if ($categories->isNotEmpty())
            <div class="mt-8 grid grid-cols-2 gap-3 sm:mt-10 sm:grid-cols-4 sm:gap-4 lg:grid-cols-8">
                @foreach ($categories as $category)
                    @php $categoryName = $category->getTranslation('name', app()->getLocale()); @endphp
                    <a href="{{ route('category.show', $category->slug) }}" wire:navigate data-motion="item"
                       class="group flex flex-col items-center gap-2 rounded-[var(--radius-card)] border border-line bg-surface p-3 text-center shadow-soft hb-lift hover:border-brass/40">
                        <span class="block aspect-square w-full overflow-hidden rounded-lg bg-paper">
                            @if ($categoryImage = $category->getFirstMediaUrl('image', 'thumb'))
                                <img src="{{ $categoryImage }}" alt="{{ $categoryName }}" loading="lazy" class="size-full object-cover transition-transform duration-300 group-hover:scale-[1.05]">
                            @else
                                <span class="flex size-full items-center justify-center text-brass">
                                    <x-ui.star-mark :size="26" />
                                </span>
                            @endif
                        </span>
                        <span class="line-clamp-2 text-[13px] font-medium leading-snug text-ink transition-colors group-hover:text-emerald">{{ $categoryName }}</span>
                    </a>
                @endforeach
            </div>
        @else
            {{-- Graceful static fallback — representative categories, no dead links. --}}
            <div class="mt-8 grid grid-cols-2 gap-3 sm:mt-10 sm:grid-cols-4 sm:gap-4 lg:grid-cols-8">
                @foreach ([
                    __('Groceries & Pantry'), __('Fashion & Apparel'), __('Beauty & Personal Care'), __('Home & Living'),
                    __('Health & Wellness'), __('Baby & Kids'), __('Books & Stationery'), __('Electronics & Gadgets'),
                ] as $fallbackName)
                    <div data-motion="item" class="flex flex-col items-center gap-2 rounded-[var(--radius-card)] border border-line bg-surface p-3 text-center shadow-soft">
                        <span class="flex aspect-square w-full items-center justify-center rounded-lg bg-paper text-brass">
                            <x-ui.star-mark :size="26" />
                        </span>
                        <span class="line-clamp-2 text-[13px] font-medium leading-snug text-ink">{{ $fallbackName }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
