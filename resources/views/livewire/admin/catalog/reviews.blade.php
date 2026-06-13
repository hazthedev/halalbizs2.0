<div class="space-y-4">

    {{-- Header --}}
    <x-ui.section-heading :title="__('Reviews')" as="h1" />

    {{-- Filters --}}
    <div class="flex flex-wrap items-center gap-3">
        <label class="sr-only" for="review-search">{{ __('Search by store') }}</label>
        <input id="review-search" type="search" wire:model.live.debounce.300ms="search"
               placeholder="{{ __('Search by store name…') }}"
               class="w-full max-w-xs rounded-[var(--radius-control)] border border-line-strong bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">

        <label class="sr-only" for="review-rating">{{ __('Filter by rating') }}</label>
        <select id="review-rating" wire:model.live="rating"
                class="min-h-11 rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-sm text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
            <option value="">{{ __('Any rating') }}</option>
            @foreach ([5, 4, 3, 2, 1] as $star)
                <option value="{{ $star }}">{{ $star }} ★</option>
            @endforeach
        </select>

        <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-sm text-ink">
            <input type="checkbox" wire:model.live="hiddenOnly"
                   class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
            {{ __('Hidden only') }}
        </label>
    </div>

    {{-- Datagrid --}}
    <x-ui.card class="overflow-x-auto">
        @if ($reviews->isEmpty())
            <x-ui.empty-state :title="__('No reviews found')" :message="__('Buyer reviews appear here the moment they are posted.')" />
        @else
            <table class="w-full min-w-[960px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Product') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Store') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Buyer') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Rating') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Comment') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Status') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Posted') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($reviews as $review)
                        <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="review-{{ $review->id }}">
                            <td class="max-w-56 px-3 py-2">
                                <span class="line-clamp-2 font-medium text-ink">{{ $review->product?->getTranslation('name', 'en') ?? '—' }}</span>
                            </td>
                            <td class="px-3 py-2 text-ink-soft">{{ $review->store?->name ?? '—' }}</td>
                            <td class="px-3 py-2 text-ink-soft">{{ $review->user?->name ?? '—' }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span class="flex gap-0.5 leading-none" aria-hidden="true">
                                    @foreach (range(1, 5) as $star)
                                        <span class="{{ $star <= $review->rating ? 'text-warn' : 'text-line' }}">★</span>
                                    @endforeach
                                </span>
                                <span class="sr-only">{{ __('Rated :rating out of 5', ['rating' => $review->rating]) }}</span>
                            </td>
                            <td class="max-w-72 px-3 py-2 text-ink-soft">{{ $review->comment ? \Illuminate\Support\Str::limit($review->comment, 60) : '—' }}</td>
                            <td class="px-3 py-2">
                                @if ($review->is_hidden)
                                    <x-ui.badge variant="danger">{{ __('Hidden') }}</x-ui.badge>
                                @else
                                    <x-ui.badge variant="neutral">{{ __('Visible') }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-[12px] text-ink-soft">{{ $review->created_at->diffForHumans() }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center justify-end">
                                    <button type="button" wire:click="startModeration({{ $review->id }})"
                                            class="inline-flex min-h-11 items-center rounded-lg px-2 font-medium focus-visible:ring-2 focus-visible:ring-emerald {{ $review->is_hidden ? 'text-emerald hover:text-emerald-deep' : 'text-danger hover:bg-danger-tint' }}">
                                        {{ $review->is_hidden ? __('Unhide') : __('Hide') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    @if ($reviews->hasPages())
        <div>{{ $reviews->links() }}</div>
    @endif

    {{-- Hide/unhide reason modal --}}
    @if ($moderating !== null)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-ink/40 p-4 sm:p-8" wire:click.self="cancelModeration">
            <x-ui.card class="w-full max-w-lg shadow-pop">
                <form wire:submit="confirmModeration">
                    <div class="border-b border-line px-5 py-4">
                        <h2 class="font-display text-lg font-semibold">
                            {{ $moderating->is_hidden ? __('Unhide review') : __('Hide review') }}
                        </h2>
                        <p class="mt-0.5 text-[13px] text-ink-soft">{{ $moderating->product?->getTranslation('name', 'en') }}</p>
                    </div>
                    <div class="space-y-2 px-5 py-4">
                        <label for="moderation-reason" class="block text-[13px] font-medium text-ink">{{ __('Reason') }}</label>
                        <textarea id="moderation-reason" wire:model="moderationReason" rows="3"
                                  placeholder="{{ __('e.g. Offensive language — violates the review guidelines.') }}"
                                  class="block w-full rounded-[var(--radius-control)] border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('moderationReason') ? 'border-danger' : 'border-line-strong' }}"></textarea>
                        @error('moderationReason')<p class="text-[13px] text-danger">{{ $message }}</p>@enderror
                        <p class="text-[13px] text-ink-faint">
                            {{ $moderating->is_hidden
                                ? __('The review returns to the product page and counts toward ratings again. The reason is kept in the audit log.')
                                : __('The review disappears from the product page and stops counting toward ratings. The reason is kept in the audit log.') }}
                        </p>
                    </div>
                    <div class="flex items-center justify-end gap-2 border-t border-line px-5 py-4">
                        <button type="button" wire:click="cancelModeration"
                                class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-3 text-[13px] font-semibold text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">
                            {{ __('Cancel') }}
                        </button>
                        @if ($moderating->is_hidden)
                            <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('Unhide review') }}</x-ui.button>
                        @else
                            <button type="submit" wire:loading.attr="disabled"
                                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-[var(--radius-control)] bg-danger px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90 focus-visible:ring-2 focus-visible:ring-emerald disabled:cursor-not-allowed disabled:opacity-50">
                                {{ __('Hide review') }}
                            </button>
                        @endif
                    </div>
                </form>
            </x-ui.card>
        </div>
    @endif
</div>
