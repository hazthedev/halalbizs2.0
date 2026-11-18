@props(['field', 'sort', 'direction'])
<th scope="col" aria-sort="{{ $sort === $field ? ($direction === 'desc' ? 'descending' : 'ascending') : 'none' }}" {{ $attributes->class(['px-3 py-2.5 font-medium']) }}>
    <button type="button" wire:click="sortBy('{{ $field }}')" class="inline-flex min-h-11 items-center gap-1 text-ink-soft hover:text-emerald focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
        {{ $slot }}
        <svg aria-hidden="true" class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            @if ($sort === $field && $direction === 'desc')
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-5-5 5 5 5-5" />
            @else
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m-5 5 5-5 5 5" />
            @endif
        </svg>
    </button>
</th>
