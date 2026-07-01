@props([
    'variant' => 'primary',
    'type' => 'button',
    'href' => null,
])

@php
$base = 'inline-flex items-center justify-center gap-2 rounded-[var(--radius-control)] px-4 py-2.5 text-sm font-semibold transition-all duration-150 ease-out-soft active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50 min-h-11';

$classes = match ($variant) {
    'primary' => "$base bg-emerald text-white shadow-soft hover:bg-emerald-deep hover:-translate-y-px active:translate-y-0 active:bg-emerald-night",
    'secondary' => "$base border border-line-strong bg-surface text-ink hover:border-ink hover:bg-paper",
    'brass' => "$base bg-brass text-white shadow-soft hover:bg-brass-deep hover:-translate-y-px active:translate-y-0",
    'ghost' => "$base text-ink-soft hover:bg-paper hover:text-ink",
    'danger' => "$base border border-danger text-danger hover:bg-danger-tint",
    'danger-fill' => "$base bg-danger text-white hover:opacity-90",
    'ink-outline' => "$base border border-paper/40 text-paper hover:bg-paper/10",
    default => $base,
};
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }} wire:navigate>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
