@php($visibleReviewTotal = array_sum($reviewDistribution))
<div>
    @if ($visibleReviewTotal === 0)
        <div class="py-5 text-center">
            <p class="font-display text-lg font-semibold">{{ __('No reviews yet') }}</p>
            <p class="mt-1 text-sm text-ink-soft">{{ __('Reviews appear here after buyers complete their orders.') }}</p>
        </div>
    @else
        {{-- Summary: big average + per-star distribution bars --}}
        <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:gap-10">
            <div class="shrink-0 text-center sm:text-left">
                <p class="font-display text-5xl font-bold text-ink tnum">{{ number_format((float) $product->rating_avg, 1) }}</p>
                <p class="mt-1 flex justify-center gap-0.5 text-lg leading-none sm:justify-start" aria-hidden="true">
                    @foreach (range(1, 5) as $star)
                        <span class="{{ $star <= (int) round((float) $product->rating_avg) ? 'text-warn' : 'text-line' }}">★</span>
                    @endforeach
                </p>
                <p class="sr-only">{{ __('Rated :avg out of 5', ['avg' => number_format((float) $product->rating_avg, 1)]) }}</p>
                <p class="mt-1 text-[13px] text-ink-soft tnum">{{ trans_choice('{1}:count review|[2,*]:count reviews', $visibleReviewTotal, ['count' => number_format($visibleReviewTotal)]) }}</p>
            </div>
            <div class="w-full max-w-sm space-y-1.5">
                @foreach ($reviewDistribution as $star => $count)
                    <div class="flex items-center gap-2 text-[13px]"
                         aria-label="{{ __(':stars-star reviews: :count', ['stars' => $star, 'count' => $count]) }}">
                        <span class="w-6 shrink-0 text-right text-ink-soft tnum" aria-hidden="true">{{ $star }}★</span>
                        <span class="h-2.5 flex-1 overflow-hidden rounded-full border border-line bg-surface" aria-hidden="true">
                            <span class="block h-full rounded-full bg-emerald-tint" style="width: {{ $visibleReviewTotal > 0 ? round($count / $visibleReviewTotal * 100) : 0 }}%"></span>
                        </span>
                        <span class="w-8 shrink-0 text-ink-soft tnum" aria-hidden="true">{{ number_format($count) }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Filters --}}
        @php($reviewFilters = ['all' => __('All'), 'photos' => __('With photos'), '5' => '5 ★', '4' => '4 ★', '3' => '3 ★', '2' => '2 ★', '1' => '1 ★'])
        <div class="mt-6 flex flex-wrap gap-2" role="group" aria-label="{{ __('Filter reviews') }}">
            @foreach ($reviewFilters as $key => $label)
                <button type="button"
                        wire:key="review-filter-{{ $key }}"
                        wire:click="setReviewFilter('{{ $key }}')"
                        aria-pressed="{{ $reviewFilter === $key ? 'true' : 'false' }}"
                        class="min-h-11 rounded-full border px-4 text-[13px] font-medium transition-colors duration-150 focus-visible:ring-2 focus-visible:ring-emerald {{ $reviewFilter === $key ? 'border-emerald bg-emerald-tint text-emerald' : 'border-line-strong text-ink hover:border-ink' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- List --}}
        <div class="mt-2 divide-y divide-line" wire:loading.class="opacity-60" wire:target="setReviewFilter, loadMoreReviews">
            @forelse ($reviews as $review)
                <article class="py-4" wire:key="review-{{ $review->id }}">
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px]">
                        <span class="font-medium text-ink">{{ $review->reviewerDisplayName() }}</span>
                        <span class="text-ink-faint" aria-hidden="true">·</span>
                        <span class="text-ink-soft">{{ $review->created_at->diffForHumans() }}</span>
                    </div>
                    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span class="flex gap-0.5 text-sm leading-none" aria-hidden="true">
                            @foreach (range(1, 5) as $star)
                                <span class="{{ $star <= $review->rating ? 'text-warn' : 'text-line' }}">★</span>
                            @endforeach
                        </span>
                        <span class="sr-only">{{ __('Rated :rating out of 5', ['rating' => $review->rating]) }}</span>
                        @if ($review->orderItem?->variant_label)
                            <span class="text-xs text-ink-faint">{{ $review->orderItem->variant_label }}</span>
                        @endif
                    </div>
                    @if ($review->comment)
                        <p class="mt-2 max-w-prose text-sm leading-relaxed text-ink">{{ $review->comment }}</p>
                    @endif
                    @php($reviewPhotos = $review->getMedia('photos'))
                    @if ($reviewPhotos->isNotEmpty())
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($reviewPhotos as $photo)
                                <button type="button"
                                        wire:key="review-photo-{{ $photo->id }}"
                                        x-on:click="lightbox = @js($photo->getUrl())"
                                        class="size-16 overflow-hidden rounded-[var(--radius-card)] border border-line bg-paper transition-colors duration-150 hover:border-ink focus-visible:ring-2 focus-visible:ring-emerald"
                                        aria-label="{{ __('View review photo :number', ['number' => $loop->iteration]) }}">
                                    <img src="{{ $photo->getUrl() }}" alt="{{ __('Review photo') }}" class="size-full object-cover" loading="lazy">
                                </button>
                            @endforeach
                        </div>
                    @endif
                    @if ($review->seller_reply)
                        <div class="ml-4 mt-3 max-w-prose rounded-[var(--radius-control)] border-l-2 border-line-strong bg-paper px-3.5 py-2.5">
                            <p class="text-xs font-semibold text-ink">{{ __('Seller response') }}</p>
                            <p class="mt-1 text-[13px] leading-relaxed text-ink-soft">{{ $review->seller_reply }}</p>
                        </div>
                    @endif
                </article>
            @empty
                <p class="py-8 text-center text-sm text-ink-soft">{{ __('No reviews match this filter yet.') }}</p>
            @endforelse
        </div>

        @if ($hasMoreReviews)
            <div class="mt-2 text-center">
                <x-ui.button variant="secondary" wire:click="loadMoreReviews" wire:loading.attr="disabled">
                    {{ __('Load more reviews') }}
                </x-ui.button>
            </div>
        @endif
    @endif
</div>
