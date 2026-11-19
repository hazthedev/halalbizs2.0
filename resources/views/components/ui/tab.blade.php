@props(['active' => false, 'count' => 0])
{{-- The one admin tab: underline style. A zero count is never shown. --}}
<button type="button" aria-current="{{ $active ? 'page' : 'false' }}"
        {{ $attributes->class([
            'inline-flex min-h-11 shrink-0 items-center gap-1.5 whitespace-nowrap border-b-2 px-3 text-sm font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald',
            'border-ink text-ink' => $active,
            'border-transparent text-ink-soft hover:text-ink' => ! $active,
        ]) }}>
    {{ $slot }}
    @if ($count > 0)
        <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-emerald-tint px-1.5 py-0.5 text-[11px] font-medium tabular-nums text-emerald">{{ $count }}</span>
    @endif
</button>
