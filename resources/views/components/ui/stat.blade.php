@props(['label', 'value'])
{{-- Admin stat tile — same look as the dashboard tiles: sentence label, mono number. --}}
<x-ui.card {{ $attributes->class(['p-4']) }}>
    <p class="text-[13px] font-medium text-ink-soft">{{ $label }}</p>
    <p class="mt-1 font-mono text-[20px] font-medium leading-tight tabular-nums whitespace-nowrap">{{ $value }}</p>
</x-ui.card>
