@props(['field', 'sort', 'direction', 'default' => null])
@php
// HB-G165: the arrow shows only on the column the list is ordered by, pointing the real way.
// `default` ('asc'|'desc') marks the column the list falls back to while nothing is picked
// (the component's tableDefaultOrder), so first load tells the truth: orders = Placed ↓.
$active = $sort === $field || ($sort === '' && $default !== null);
$dir = $sort === $field ? $direction : $default;
@endphp
<th scope="col" aria-sort="{{ $active ? ($dir === 'desc' ? 'descending' : 'ascending') : 'none' }}" {{ $attributes->class(['px-3 py-6.5 font-medium']) }}>
    <button type="button" wire:click="sortBy('{{ $field }}')" @class(['group -my-2 inline-flex items-center gap-1 py-2 hover:text-emerald focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald', 'text-ink' => $active, 'text-ink-soft' => ! $active])>
        {{ $slot }}
        <svg aria-hidden="true" @class(['size-4 shrink-0', 'opacity-0 transition-opacity group-hover:opacity-50 group-focus-visible:opacity-50' => ! $active]) viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            @if (! $active)
                <path stroke-linecap="round" stroke-linejoin="round" d="m8 9 4-4 4 4m0 6-4 4-4-4" />
            @elseif ($dir === 'desc')
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-5-5 5 5 5-5" />
            @else
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m-5 5 5-5 5 5" />
            @endif
        </svg>
    </button>
</th>
