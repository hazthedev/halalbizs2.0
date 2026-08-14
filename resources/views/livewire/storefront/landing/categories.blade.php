{{-- Shop by department. Serif heading left, mono count right, then tiles whose
     image sits on the cream bed exactly as the product cards do. Counts are
     live, so a department with nothing in it says so instead of lying. --}}
<section class="bg-paper">
    <div class="mx-auto max-w-[1400px] px-4 py-16 lg:px-12">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h2 class="font-display text-[length:var(--text-h2)] text-ink-head">{{ __('Shop by department') }}</h2>
            <span class="font-mono text-[length:var(--text-tiny)] uppercase tracking-[var(--tracking-label-xl)] text-ink-faint">
                {{ trans_choice('{1} :count department|[2,*] :count departments', $categories->count(), ['count' => $categories->count()]) }}
            </span>
        </div>

        <div class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($categories as $category)
                {{-- M-21: these were four queries per iteration, resolved in
                     Landing::topCategories() now. Scalars in, no Eloquent here. --}}
                @php($sample = $category->tile_sample)
                @php($count = $category->tile_count)
                <a href="{{ route('category.show', $category->slug) }}" wire:navigate
                   class="group rounded-[var(--radius-card)] border border-line bg-surface transition-colors duration-(--dur-micro) hover:border-line-strong">
                    <div class="aspect-square overflow-hidden rounded-t-[var(--radius-card)] bg-cream">
                        @if ($sample)
                            <img src="{{ $sample->getFirstMediaUrl('images', 'thumb') }}" alt=""
                                 class="size-full object-contain p-4" loading="lazy">
                        @endif
                    </div>
                    <div class="p-4">
                        <p class="text-[length:var(--text-md)] text-ink-head">{{ $category->getTranslation('name', app()->getLocale()) }}</p>
                        <p class="mt-1 font-mono text-[length:var(--text-tiny)] text-ink-faint tnum">
                            {{ trans_choice('{0} No listings yet|{1} :count listing|[2,*] :count listings', $count, ['count' => number_format($count)]) }}
                        </p>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>
