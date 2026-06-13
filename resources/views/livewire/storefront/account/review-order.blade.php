<div class="border-t border-line">
    @if ($pendingCount === 0)
        <p class="flex items-center gap-1.5 px-4 py-3 text-[13px] text-ink-faint">
            <svg class="size-4 text-emerald" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
            {{ __('Reviewed ✓') }}
        </p>
    @else
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
            <p class="text-[13px] text-ink-soft">
                {{ trans_choice('{1}:count item is waiting for your rating.|[2,*]:count items are waiting for your rating.', $pendingCount, ['count' => $pendingCount]) }}
            </p>
            <x-ui.button variant="primary" wire:click="toggle" aria-expanded="{{ $open ? 'true' : 'false' }}">
                {{ $open ? __('Close') : __('Rate order') }}
            </x-ui.button>
        </div>

        @if ($open)
            <div class="space-y-3 border-t border-line bg-paper px-4 py-4">
                @foreach ($items as $item)
                    <div wire:key="review-item-{{ $item->id }}" class="rounded-[var(--radius-card)] border border-line bg-surface p-4">
                        <div class="flex items-start gap-3">
                            <span class="block size-12 shrink-0 overflow-hidden rounded-[var(--radius-control)] border border-line bg-paper">
                                @if ($item->product?->getFirstMediaUrl('images', 'thumb'))
                                    <img src="{{ $item->product->getFirstMediaUrl('images', 'thumb') }}"
                                         alt="{{ $item->product_name }}{{ $item->variant_label ? ' — '.$item->variant_label : '' }}"
                                         class="size-full object-cover" loading="lazy">
                                @endif
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="line-clamp-1 text-sm font-medium text-ink">{{ $item->product_name }}</p>
                                @if ($item->variant_label)
                                    <p class="mt-0.5 text-xs text-ink-soft">{{ $item->variant_label }}</p>
                                @endif
                            </div>
                            @if ($item->review !== null)
                                <span class="shrink-0 text-[13px] text-ink-faint">{{ __('Reviewed ✓') }}</span>
                            @endif
                        </div>

                        @if ($item->review === null)
                            {{-- Star rating: real radio buttons, rendered as stars (filled = warn, empty = line) --}}
                            <fieldset class="mt-3">
                                <legend class="text-[13px] font-medium text-ink">{{ __('Your rating') }}</legend>
                                <div class="-ml-2 mt-0.5 flex">
                                    @foreach (range(1, 5) as $star)
                                        <label wire:key="star-{{ $item->id }}-{{ $star }}"
                                               class="flex size-11 cursor-pointer items-center justify-center">
                                            <input type="radio" value="{{ $star }}"
                                                   name="rating-{{ $item->id }}"
                                                   wire:model.live="ratings.{{ $item->id }}"
                                                   class="peer sr-only">
                                            <span aria-hidden="true"
                                                  class="rounded text-[26px] leading-none transition-colors duration-150 peer-focus-visible:ring-2 peer-focus-visible:ring-emerald peer-focus-visible:ring-offset-2 {{ (int) ($ratings[$item->id] ?? 0) >= $star ? 'text-warn' : 'text-line' }}">★</span>
                                            <span class="sr-only">{{ trans_choice('{1}:count star|[2,*]:count stars', $star, ['count' => $star]) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('ratings.'.$item->id)<p class="mt-1 text-[13px] text-danger">{{ $message }}</p>@enderror
                            </fieldset>

                            {{-- Comment --}}
                            <div class="mt-3">
                                <label for="review-comment-{{ $item->id }}" class="block text-[13px] font-medium text-ink">
                                    {{ __('Your review') }} <span class="font-normal text-ink-faint">{{ __('(optional)') }}</span>
                                </label>
                                <textarea id="review-comment-{{ $item->id }}" rows="3"
                                          wire:model="comments.{{ $item->id }}"
                                          placeholder="{{ __('How was the quality? Would you buy it again?') }}"
                                          class="mt-1 block w-full rounded-[var(--radius-control)] border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('comments.'.$item->id) ? 'border-danger' : 'border-line-strong' }}"></textarea>
                                @error('comments.'.$item->id)<p class="mt-1 text-[13px] text-danger">{{ $message }}</p>@enderror
                            </div>

                            {{-- Photos --}}
                            <div class="mt-3">
                                <label for="review-photos-{{ $item->id }}" class="block text-[13px] font-medium text-ink">
                                    {{ __('Photos') }} <span class="font-normal text-ink-faint">{{ __('(up to 5, max 4MB each)') }}</span>
                                </label>
                                <input id="review-photos-{{ $item->id }}" type="file" multiple accept="image/*"
                                       wire:model="photos.{{ $item->id }}"
                                       class="mt-1 block w-full text-[13px] text-ink-soft file:mr-3 file:min-h-11 file:cursor-pointer file:rounded-[var(--radius-control)] file:border file:border-ink file:bg-surface file:px-3 file:text-[13px] file:font-semibold file:text-ink hover:file:bg-paper focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                <p class="mt-1 text-[13px] text-ink-faint" wire:loading wire:target="photos.{{ $item->id }}">{{ __('Uploading photos…') }}</p>
                                @error('photos.'.$item->id)<p class="mt-1 text-[13px] text-danger">{{ $message }}</p>@enderror
                                @error('photos.'.$item->id.'.*')<p class="mt-1 text-[13px] text-danger">{{ $message }}</p>@enderror

                                @if (! empty($photos[$item->id]))
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($photos[$item->id] as $index => $photo)
                                            <span wire:key="photo-preview-{{ $item->id }}-{{ $index }}"
                                                  class="block size-16 overflow-hidden rounded-[var(--radius-card)] border border-line bg-paper">
                                                <img src="{{ $photo->temporaryUrl() }}" alt="{{ __('Photo :number to upload', ['number' => $index + 1]) }}" class="size-full object-cover">
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div class="mt-4 flex justify-end">
                                <x-ui.button variant="secondary"
                                             wire:click="submit({{ $item->id }})"
                                             wire:loading.attr="disabled"
                                             wire:target="submit({{ $item->id }}), photos.{{ $item->id }}">
                                    {{ __('Post review') }}
                                </x-ui.button>
                            </div>
                        @endif
                    </div>
                @endforeach

                {{-- Seller SERVICE rating — once per sub-order, saved with the
                     first item review you post (never duplicated). --}}
                @if (! $sellerRated)
                    <div class="rounded-[var(--radius-card)] border border-line bg-surface p-4">
                        <fieldset>
                            <legend class="text-[13px] font-medium text-ink">
                                {{ __("Rate the seller's service") }} <span class="font-normal text-ink-faint">{{ __('(optional)') }}</span>
                            </legend>
                            <div class="-ml-2 mt-0.5 flex">
                                @foreach (range(1, 5) as $star)
                                    <label wire:key="seller-star-{{ $star }}" class="flex size-11 cursor-pointer items-center justify-center">
                                        <input type="radio" value="{{ $star }}"
                                               name="seller-rating-{{ $subOrder->id }}"
                                               wire:model.live="sellerRating"
                                               class="peer sr-only">
                                        <span aria-hidden="true"
                                              class="rounded text-[26px] leading-none transition-colors duration-150 peer-focus-visible:ring-2 peer-focus-visible:ring-emerald peer-focus-visible:ring-offset-2 {{ (int) ($sellerRating ?? 0) >= $star ? 'text-warn' : 'text-line' }}">★</span>
                                        <span class="sr-only">{{ trans_choice('{1}:count star|[2,*]:count stars', $star, ['count' => $star]) }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('sellerRating')<p class="mt-1 text-[13px] text-danger">{{ $message }}</p>@enderror
                        </fieldset>

                        <div class="mt-3">
                            <label for="seller-comment-{{ $subOrder->id }}" class="block text-[13px] font-medium text-ink">
                                {{ __('About the service') }} <span class="font-normal text-ink-faint">{{ __('(optional)') }}</span>
                            </label>
                            <input id="seller-comment-{{ $subOrder->id }}" type="text" maxlength="500"
                                   wire:model="sellerComment"
                                   placeholder="{{ __('Fast replies? Careful packing?') }}"
                                   class="mt-1 block min-h-11 w-full rounded-[var(--radius-control)] border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('sellerComment') ? 'border-danger' : 'border-line-strong' }}">
                            @error('sellerComment')<p class="mt-1 text-[13px] text-danger">{{ $message }}</p>@enderror
                            <p class="mt-1 text-[13px] text-ink-faint">{{ __('Saved together with the next item review you post.') }}</p>
                        </div>
                    </div>
                @endif

                <x-turnstile :error="$errors->first('turnstileToken')" />
            </div>
        @endif
    @endif
</div>
