{{-- ===== Category showcase — real top-level categories, capped at 8 =====
     Rendered as "lantern cards": name + a subtle brass glow border on hover
     (`.lantern-card`, CSS-only). Alternating COLUMNS carry a `data-plx`
     wrapper (0.9 / 1.05) for a later JS drift pass — the column div is the
     decorative/parallax target, never the cards or the section root. Falls
     back to a static, non-linking preview when the table is empty (fresh
     install, pre-seed) so the page never shows a hole. --}}
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
            <div class="mt-8 grid grid-cols-2 gap-4 sm:mt-10 sm:grid-cols-4 sm:gap-5">
                @foreach ($categories->chunk(2) as $columnIndex => $column)
                    <div data-plx="{{ $columnIndex % 2 === 0 ? '0.9' : '1.05' }}" class="flex flex-col gap-4">
                        @foreach ($column as $category)
                            @php $categoryName = $category->getTranslation('name', app()->getLocale()); @endphp
                            <a href="{{ route('category.show', $category->slug) }}" wire:navigate data-motion="item"
                               class="lantern-card group flex flex-col items-center gap-2 rounded-[var(--radius-card)] border border-line bg-surface p-4 text-center shadow-soft">
                                <span class="flex size-11 items-center justify-center rounded-full bg-brass-tint text-brass">
                                    <x-ui.star-mark :size="22" />
                                </span>
                                <span class="line-clamp-2 text-[13px] font-medium leading-snug text-ink transition-colors group-hover:text-emerald">{{ $categoryName }}</span>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @else
            {{-- Graceful static fallback — representative categories, no dead links. --}}
            <div class="mt-8 grid grid-cols-2 gap-4 sm:mt-10 sm:grid-cols-4 sm:gap-5">
                @foreach (collect([
                    __('Groceries & Pantry'), __('Fashion & Apparel'), __('Beauty & Personal Care'), __('Home & Living'),
                    __('Health & Wellness'), __('Baby & Kids'), __('Books & Stationery'), __('Electronics & Gadgets'),
                ])->chunk(2) as $columnIndex => $column)
                    <div data-plx="{{ $columnIndex % 2 === 0 ? '0.9' : '1.05' }}" class="flex flex-col gap-4">
                        @foreach ($column as $fallbackName)
                            <div data-motion="item" class="flex flex-col items-center gap-2 rounded-[var(--radius-card)] border border-line bg-surface p-4 text-center shadow-soft">
                                <span class="flex size-11 items-center justify-center rounded-full bg-paper text-brass">
                                    <x-ui.star-mark :size="22" />
                                </span>
                                <span class="line-clamp-2 text-[13px] font-medium leading-snug text-ink">{{ $fallbackName }}</span>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
