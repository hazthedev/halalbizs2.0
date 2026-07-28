{{-- ===== Category tiles — auto-fit grid, editorial tiles =====
     Was a centred flex cluster of small square cards, which left 3 live
     categories adrift in a wide empty band and read as unfinished.

     `auto-fit` + `minmax` is the fix for the real constraint: the count is
     DATA, not design. 3 categories fill the row as 3 wide tiles, 8 wrap to
     4+4, and no arrangement can leave an empty cell — the failure mode a
     fixed 4-col grid has with 3 items.

     Visual variation comes from the house zellij field on every third tile
     (the design hub allows a pattern in place of a photo). There is no real
     product photography in the app yet — every seeded image is a flat colour
     block with two letters on it — so photos here would look worse than
     ornament. Flagged in the redesign report as the one thing this page
     still wants.

     NO data-plx: alternating per-card drift read as broken row alignment
     with only 3 cards (Haze 2026-07-24). Tiles sit flush. --}}
<section data-land="categories" class="border-y border-line bg-surface/60 px-4 py-20 sm:py-24 lg:py-28">
    <div class="mx-auto max-w-7xl">
        <x-ui.section-heading
            as="h2"
            :title="__('Shop by category')"
            :subtitle="__('A snapshot of what Malaysian halal sellers are stocking right now.')"
            :href="$categories->isNotEmpty() ? route('search') : null"
            :link-label="__('Browse all')"
        />

        @php
            // One shape for both branches: real categories link, the pre-seed
            // fallback does not. Keeps the two paths from drifting apart.
            $tiles = $categories->isNotEmpty()
                ? $categories->map(fn ($category) => [
                    'name' => $category->getTranslation('name', app()->getLocale()),
                    'href' => route('category.show', $category->slug),
                ])
                : collect([
                    __('Groceries & Pantry'), __('Fashion & Apparel'), __('Beauty & Personal Care'), __('Home & Living'),
                    __('Health & Wellness'), __('Baby & Kids'), __('Books & Stationery'), __('Electronics & Gadgets'),
                ])->map(fn ($name) => ['name' => $name, 'href' => null]);
        @endphp

        <div class="mt-10 grid gap-4 sm:gap-5 [grid-template-columns:repeat(auto-fit,minmax(min(100%,220px),1fr))]">
            @foreach ($tiles as $tile)
                @php
                    $patterned = $loop->index % 3 === 2;
                    $tileClass = 'group relative flex min-h-40 flex-col justify-between overflow-hidden rounded-[var(--radius-card)] border p-5 shadow-soft sm:min-h-48 '
                        .($patterned
                            ? 'surface-zellij border-brass/25 bg-brass-tint/40'
                            : 'border-line bg-surface');
                @endphp

                @if ($tile['href'])
                    <a href="{{ $tile['href'] }}" wire:navigate data-motion="item" class="{{ $tileClass }} spot-card hb-lift">
                        <x-ui.star-mark :size="26" class="text-brass transition-transform duration-[var(--dur-standard)] ease-[var(--ease-out-soft)] group-hover:scale-110" />
                        <span class="mt-6 font-display text-lg font-semibold leading-snug text-ink transition-colors group-hover:text-emerald">
                            {{ $tile['name'] }}
                        </span>
                    </a>
                @else
                    <div data-motion="item" class="{{ $tileClass }}">
                        <x-ui.star-mark :size="26" class="text-brass" />
                        <span class="mt-6 font-display text-lg font-semibold leading-snug text-ink">{{ $tile['name'] }}</span>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</section>
