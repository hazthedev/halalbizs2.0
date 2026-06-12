<div class="space-y-4">

    {{-- Header --}}
    <h1 class="font-display text-2xl font-bold">{{ __('Reviews') }}</h1>

    @if ($reviews->isEmpty())
        <x-ui.card class="px-6 py-16 text-center">
            <h2 class="font-display text-xl font-semibold">{{ __('No reviews yet') }}</h2>
            <p class="mt-1 text-sm text-ink-soft">{{ __('Reviews appear here after buyers complete their orders and rate your products.') }}</p>
        </x-ui.card>
    @else
        <div class="space-y-3">
            @foreach ($reviews as $review)
                <x-ui.card class="p-4" wire:key="review-{{ $review->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-ink">
                                {{ $review->product?->getTranslation('name', app()->getLocale()) ?? $review->orderItem?->product_name }}
                            </p>
                            <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px] text-ink-soft">
                                <span class="flex gap-0.5 leading-none" aria-hidden="true">
                                    @foreach (range(1, 5) as $star)
                                        <span class="{{ $star <= $review->rating ? 'text-warn' : 'text-line' }}">★</span>
                                    @endforeach
                                </span>
                                <span class="sr-only">{{ __('Rated :rating out of 5', ['rating' => $review->rating]) }}</span>
                                <span>{{ $review->reviewerDisplayName() }}</span>
                                <span aria-hidden="true">·</span>
                                <span>{{ $review->created_at->diffForHumans() }}</span>
                                @if ($review->getMedia('photos')->isNotEmpty())
                                    <span aria-hidden="true">·</span>
                                    <span class="tnum">{{ trans_choice('{1}:count photo|[2,*]:count photos', $review->getMedia('photos')->count(), ['count' => $review->getMedia('photos')->count()]) }}</span>
                                @endif
                            </div>
                        </div>
                        @if ($review->is_hidden)
                            <x-ui.badge variant="danger">{{ __('Hidden') }}</x-ui.badge>
                        @endif
                    </div>

                    @if ($review->comment)
                        <p class="mt-2 max-w-prose text-sm leading-relaxed text-ink">{{ $review->comment }}</p>
                    @endif

                    {{-- Reply: one per review, editable for 24h after first posting --}}
                    <div class="mt-3 border-t border-line pt-3">
                        @if ($replyingId === $review->id)
                            <label for="reply-text-{{ $review->id }}" class="block text-[13px] font-medium text-ink">{{ __('Your reply') }}</label>
                            <textarea id="reply-text-{{ $review->id }}" rows="3" wire:model="replyText"
                                      placeholder="{{ __('Thank the buyer or address their feedback — everyone sees this on the product page.') }}"
                                      class="mt-1 block w-full rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('replyText') ? 'border-danger' : 'border-line-strong' }}"></textarea>
                            @error('replyText')<p class="mt-1 text-[13px] text-danger">{{ $message }}</p>@enderror
                            <p class="mt-1 text-[13px] text-ink-faint">{{ __('You can edit your reply for 24 hours after posting — then it locks.') }}</p>
                            <div class="mt-2 flex items-center gap-2">
                                <x-ui.button wire:click="saveReply" wire:loading.attr="disabled">{{ __('Post reply') }}</x-ui.button>
                                <x-ui.button variant="ghost" wire:click="cancelReply">{{ __('Cancel') }}</x-ui.button>
                            </div>
                        @elseif ($review->seller_reply !== null)
                            <div class="max-w-prose rounded-lg border-l-2 border-line-strong bg-paper px-3.5 py-2.5">
                                <p class="text-xs font-semibold text-ink">{{ __('Your reply') }} <span class="font-normal text-ink-faint">· {{ $review->seller_replied_at->diffForHumans() }}</span></p>
                                <p class="mt-1 text-[13px] leading-relaxed text-ink-soft">{{ $review->seller_reply }}</p>
                            </div>
                            @if ($review->replyLocked())
                                <p class="mt-2 flex items-center gap-1.5 text-[13px] text-ink-faint">
                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
                                    {{ __('Replies lock 24 hours after posting.') }}
                                </p>
                            @else
                                <x-ui.button variant="ghost" class="mt-1" wire:click="startReply({{ $review->id }})">{{ __('Edit reply') }}</x-ui.button>
                            @endif
                        @else
                            <x-ui.button variant="secondary" wire:click="startReply({{ $review->id }})">{{ __('Reply') }}</x-ui.button>
                        @endif
                    </div>
                </x-ui.card>
            @endforeach
        </div>

        <div>{{ $reviews->links() }}</div>
    @endif
</div>
