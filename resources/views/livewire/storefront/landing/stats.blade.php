{{-- ===== Stats — slim inline strip =====
     Was a full 3-up band with the numbers at text-5xl. Two problems: it was
     the fourth consecutive 3-across section, and the scale worked against
     the content. The counts are real and currently small (10 sellers), and
     a small number set in huge type draws the eye straight to how small it
     is. Stated plainly at text-xl on one line it reads as a fact rather
     than a boast, and the band stops competing with the sections either
     side of it.

     `data-countup` / `data-target` are unchanged — landing.js still animates
     these to the exact server-rendered, number_format-matched value.
     JetBrains Mono + `.tnum` so the digits hold their width mid-count. --}}
<section data-land="stats" class="surface-zellij border-y border-line bg-brass-tint/25">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-center gap-x-8 gap-y-4 px-4 py-7 sm:gap-x-14 sm:px-6">
        @foreach ([
            ['value' => $stats['stores'], 'label' => __('Active local sellers')],
            ['value' => $stats['products'], 'label' => __('Products listed')],
            ['value' => $stats['categories'], 'label' => __('Categories to explore')],
        ] as $stat)
            <p class="flex items-baseline gap-2.5">
                <span data-countup data-target="{{ $stat['value'] }}" class="tnum font-mono text-xl font-bold text-ink sm:text-2xl">
                    {{ number_format($stat['value']) }}
                </span>
                <span class="text-sm font-medium text-ink-soft">{{ $stat['label'] }}</span>
            </p>
        @endforeach
    </div>
</section>
