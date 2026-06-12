@props([
    'variant' => 'primary',
    'type' => 'button',
    'href' => null,
])

@php
$base = 'inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold transition-colors duration-150 disabled:cursor-not-allowed disabled:opacity-50 min-h-11';

$classes = match ($variant) {
    'primary' => "$base bg-emerald text-white hover:bg-emerald-deep active:bg-emerald-night",
    'secondary' => "$base border border-ink text-ink hover:bg-paper",
    'ghost' => "$base text-ink-soft hover:text-ink",
    'danger' => "$base border border-danger text-danger hover:bg-danger-tint",
    'danger-fill' => "$base bg-danger text-white hover:opacity-90",
    'ink-outline' => "$base border border-paper/90 text-paper hover:bg-paper/10",
    default => $base,
};
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }} wire:navigate>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
