{{-- Shared filter controls — included by the desktop sidebar and the mobile bottom sheet. --}}
{{-- $idPrefix keeps input ids unique across the two render sites. --}}

@if (! $isSearch && $children->isNotEmpty())
    <div>
        <p class="mb-2 text-[11px] font-medium uppercase tracking-[0.04em] text-ink-faint">{{ __('Category') }}</p>
        <ul class="space-y-1">
            <li>
                <button
                    type="button"
                    wire:click="$set('childCategory', '')"
                    class="flex min-h-11 w-full items-center rounded-[var(--radius-control)] px-3 text-left text-sm {{ $childCategory === '' ? 'bg-emerald-tint font-medium text-emerald' : 'text-ink-soft hover:text-ink' }}"
                >
                    {{ __('All in :name', ['name' => $rootCategory->getTranslation('name', app()->getLocale())]) }}
                </button>
            </li>
            @foreach ($children as $child)
                <li>
                    <button
                        type="button"
                        wire:click="$set('childCategory', '{{ $child->slug }}')"
                        class="flex min-h-11 w-full items-center rounded-[var(--radius-control)] px-3 text-left text-sm {{ $childCategory === $child->slug ? 'bg-emerald-tint font-medium text-emerald' : 'text-ink-soft hover:text-ink' }}"
                    >
                        {{ $child->getTranslation('name', app()->getLocale()) }}
                    </button>
                </li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Certifying body — pill toggles, as the reference has them. This is the
     filter that makes the catalogue a HALAL catalogue rather than a grocery
     one, so it sits directly under the department list.
     The authority is read from the certificate number's prefix; see
     Listing::certifierCodes(), which reads HalalCertificate::BODIES. --}}
<div>
    <p class="mb-3 border-b border-line pb-2 font-mono text-[length:var(--text-tiny)] uppercase tracking-[var(--tracking-label-xl)] text-ink-faint">{{ __('Certifying body') }}</p>
    <div class="flex flex-wrap gap-1.5">
        @foreach (\App\Livewire\Storefront\Listing::certifierCodes() as $body)
            @php $on = in_array($body, $certifiers, true); @endphp
            <button
                type="button"
                wire:click="toggleCertifier('{{ $body }}')"
                aria-pressed="{{ $on ? 'true' : 'false' }}"
                class="rounded-[var(--radius-pill)] border px-2.5 py-1 font-mono text-[length:var(--text-nano)] uppercase tracking-[var(--tracking-label)] transition-colors duration-(--dur-micro) {{ $on ? 'border-emerald bg-emerald-tint text-emerald' : 'border-line text-ink-faint hover:border-line-strong hover:text-ink' }}"
            >{{ $body }}</button>
        @endforeach
    </div>
</div>

{{-- Assurance — each box maps to a column on the certificate record, so a
     tick genuinely narrows the catalogue. AND across the group: every box a
     buyer ticks is another requirement, not another option. --}}
<div>
    <p class="mb-3 border-b border-line pb-2 font-mono text-[length:var(--text-tiny)] uppercase tracking-[var(--tracking-label-xl)] text-ink-faint">{{ __('Assurance') }}</p>
    <div class="space-y-2.5">
        @foreach ([
            'valid12' => __('Certificate valid 12 months+'),
            'dedicated' => __('Dedicated halal facility'),
            'export' => __('Export paperwork included'),
        ] as $key => $label)
            <label for="{{ $idPrefix }}-assure-{{ $key }}" class="flex cursor-pointer items-start gap-2.5 text-[length:var(--text-base)] text-ink">
                <input id="{{ $idPrefix }}-assure-{{ $key }}" type="checkbox"
                       wire:click="toggleAssurance('{{ $key }}')"
                       @checked(in_array($key, $assurances, true))
                       class="mt-0.5 size-4 shrink-0 rounded-[4px] border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                <span>{{ $label }}</span>
            </label>
        @endforeach
    </div>
</div>

<div>
    <p class="mb-2 text-[11px] font-medium uppercase tracking-[0.04em] text-ink-faint">{{ __('Price (RM)') }}</p>
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
    <p class="mb-2 text-[11px] font-medium uppercase tracking-[0.04em] text-ink-faint">{{ __('Rating') }}</p>
    <ul class="space-y-1">
        @foreach ([4, 3, 2, 1] as $stars)
            <li>
                <button
                    type="button"
                    wire:click="$set('rating', {{ $rating === $stars ? 'null' : $stars }})"
                    class="flex min-h-11 w-full items-center gap-1.5 rounded-[var(--radius-control)] px-3 text-left text-sm {{ $rating === $stars ? 'bg-emerald-tint font-medium text-emerald' : 'text-ink-soft hover:text-ink' }}"
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
    <label for="{{ $idPrefix }}-state" class="mb-2 block text-[11px] font-medium uppercase tracking-[0.04em] text-ink-faint">{{ __('Ships from') }}</label>
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
    <p class="mb-2 text-[11px] font-medium uppercase tracking-[0.04em] text-ink-faint">{{ __('Payment') }}</p>
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

{{-- Attribute facets (M1.3) --}}
@if (isset($facetAttributes))
    @foreach ($facetAttributes as $facetAttribute)
        @if ($facetAttribute->values->isNotEmpty())
            <div>
                <p class="mb-2 text-[11px] font-medium uppercase tracking-[0.04em] text-ink-faint">{{ $facetAttribute->getTranslation('name', app()->getLocale()) }}</p>
                <ul class="space-y-1">
                    @foreach ($facetAttribute->values as $facetValue)
                        <li>
                            <label class="flex min-h-11 cursor-pointer items-center gap-2.5 rounded-[var(--radius-control)] px-3 text-sm text-ink">
                                <input
                                    type="checkbox"
                                    wire:click="toggleAttr({{ $facetValue->id }})"
                                    @checked(in_array($facetValue->id, $selectedAttrs ?? [], true))
                                    class="size-5 rounded border-line-strong text-emerald focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald"
                                >
                                {{ $facetValue->getTranslation('value', app()->getLocale()) }}
                            </label>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endforeach
@endif
