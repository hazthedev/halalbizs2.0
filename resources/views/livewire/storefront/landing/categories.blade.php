{{-- ===== Category showcase — real top-level categories, capped at 8 =====
     Rendered as "lantern cards": name + a subtle brass glow border on hover
     (`.lantern-card`, CSS-only). A centered flex cluster instead of a fixed
     4-col grid: the live table currently holds 3 top-level categories and a
     grid renders that as a lopsided two-thirds-empty row, while flex-wrap
     centers any count (3 today, 8 when seeded — the max-w-3xl cap wraps 8
     into 4+4). Each card sits in a wrapper carrying alternating `data-plx`
     (0.92 / 1.06) for the JS drift pass — wrapper is the parallax target,
     the card keeps `data-motion` (separate elements, transforms never
     compose on one node). Falls back to a static, non-linking preview when
     the table is empty (fresh install, pre-seed) so the page never shows a
     hole. --}}
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
            <div class="mx-auto mt-8 flex max-w-3xl flex-wrap justify-center gap-4 sm:mt-10 sm:gap-5">
                @foreach ($categories as $index => $category)
                    @php $categoryName = $category->getTranslation('name', app()->getLocale()); @endphp
                    <div data-plx="{{ $index % 2 === 0 ? '0.92' : '1.06' }}" class="w-[calc(50%-0.5rem)] sm:w-44">
                        <a href="{{ route('category.show', $category->slug) }}" wire:navigate data-motion="item"
                           class="lantern-card group flex h-full flex-col items-center gap-2 rounded-[var(--radius-card)] border border-line bg-surface p-4 text-center shadow-soft">
                            <span class="flex size-11 items-center justify-center rounded-full bg-brass-tint text-brass">
                                <x-ui.star-mark :size="22" />
                            </span>
                            <span class="line-clamp-2 text-[13px] font-medium leading-snug text-ink transition-colors group-hover:text-emerald">{{ $categoryName }}</span>
                        </a>
                    </div>
                @endforeach
            </div>
        @else
            {{-- Graceful static fallback — representative categories, no dead links. --}}
            <div class="mx-auto mt-8 flex max-w-3xl flex-wrap justify-center gap-4 sm:mt-10 sm:gap-5">
                @foreach (collect([
                    __('Groceries & Pantry'), __('Fashion & Apparel'), __('Beauty & Personal Care'), __('Home & Living'),
                    __('Health & Wellness'), __('Baby & Kids'), __('Books & Stationery'), __('Electronics & Gadgets'),
                ]) as $index => $fallbackName)
                    <div data-plx="{{ $index % 2 === 0 ? '0.92' : '1.06' }}" class="w-[calc(50%-0.5rem)] sm:w-44">
                        <div data-motion="item" class="flex h-full flex-col items-center gap-2 rounded-[var(--radius-card)] border border-line bg-surface p-4 text-center shadow-soft">
                            <span class="flex size-11 items-center justify-center rounded-full bg-paper text-brass">
                                <x-ui.star-mark :size="22" />
                            </span>
                            <span class="line-clamp-2 text-[13px] font-medium leading-snug text-ink">{{ $fallbackName }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
