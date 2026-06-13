{{-- Shared filter controls — included by the desktop sidebar and the mobile bottom sheet. --}}
{{-- $idPrefix keeps input ids unique across the two render sites. --}}

@if (! $isSearch && $children->isNotEmpty())
    <div>
        <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.04em] text-ink-faint">{{ __('Category') }}</p>
        <ul class="space-y-1">
            <li>
                <button
                    type="button"
                    wire:click="$set('childCategory', '')"
                    class="flex min-h-11 w-full items-center rounded-[var(--radius-control)] px-3 text-left text-sm {{ $childCategory === '' ? 'bg-emerald-tint font-semibold text-emerald' : 'text-ink-soft hover:text-ink' }}"
                >
                    {{ __('All in :name', ['name' => $rootCategory->getTranslation('name', app()->getLocale())]) }}
                </button>
            </li>
            @foreach ($children as $child)
                <li>
                    <button
                        type="button"
                        wire:click="$set('childCategory', '{{ $child->slug }}')"
                        class="flex min-h-11 w-full items-center rounded-[var(--radius-control)] px-3 text-left text-sm {{ $childCategory === $child->slug ? 'bg-emerald-tint font-semibold text-emerald' : 'text-ink-soft hover:text-ink' }}"
                    >
                        {{ $child->getTranslation('name', app()->getLocale()) }}
                    </button>
                </li>
            @endforeach
        </ul>
    </div>
@endif

<div>
    <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.04em] text-ink-faint">{{ __('Price (RM)') }}</p>
    <div class="flex items-center gap-2">
        <label for="{{ $idPrefix }}-price-min" class="sr-only">{{ __('Minimum price in RM') }}</label>
        <input
            id="{{ $idPrefix }}-price-min"
            type="number"
            min="0"
            step="1"
            inputmode="numeric"
            placeholder="{{ __('Min') }}"
            wire:model.live.debounce.500ms="priceMin"
            class="block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-sm text-ink tnum placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald"
        >
        <span class="text-ink-faint" aria-hidden="true">–</span>
        <label for="{{ $idPrefix }}-price-max" class="sr-only">{{ __('Maximum price in RM') }}</label>
        <input
            id="{{ $idPrefix }}-price-max"
            type="number"
            min="0"
            step="1"
            inputmode="numeric"
            placeholder="{{ __('Max') }}"
            wire:model.live.debounce.500ms="priceMax"
            class="block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-sm text-ink tnum placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald"
        >
    </div>
</div>

<div>
    <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.04em] text-ink-faint">{{ __('Rating') }}</p>
    <ul class="space-y-1">
        @foreach ([4, 3, 2, 1] as $stars)
            <li>
                <button
                    type="button"
                    wire:click="$set('rating', {{ $rating === $stars ? 'null' : $stars }})"
                    class="flex min-h-11 w-full items-center gap-1.5 rounded-[var(--radius-control)] px-3 text-left text-sm {{ $rating === $stars ? 'bg-emerald-tint font-semibold text-emerald' : 'text-ink-soft hover:text-ink' }}"
                    @if ($rating === $stars) aria-pressed="true" @endif
                >
                    <span aria-hidden="true">{{ str_repeat('★', $stars) }}{{ str_repeat('☆', 5 - $stars) }}</span>
                    {{ __(':stars & up', ['stars' => $stars]) }}
                </button>
            </li>
        @endforeach
    </ul>
</div>

<div>
    <label for="{{ $idPrefix }}-state" class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.04em] text-ink-faint">{{ __('Ships from') }}</label>
    <select
        id="{{ $idPrefix }}-state"
        wire:model.live="state"
        class="block min-h-11 w-full cursor-pointer rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-sm text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald"
    >
        <option value="">{{ __('All states') }}</option>
        @foreach ($states as $stateOption)
            <option value="{{ $stateOption }}">{{ $stateOption }}</option>
        @endforeach
    </select>
</div>

<div>
    <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.04em] text-ink-faint">{{ __('Payment') }}</p>
    <label for="{{ $idPrefix }}-cod" class="flex min-h-11 cursor-pointer items-center gap-2.5 rounded-[var(--radius-control)] px-3 text-sm text-ink">
        <input
            id="{{ $idPrefix }}-cod"
            type="checkbox"
            wire:model.live="cod"
            class="size-5 rounded border-line-strong text-emerald focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald"
        >
        {{ __('Cash on delivery') }}
    </label>
</div>
