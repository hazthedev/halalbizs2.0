@props([
    'variant' => 'primary',
    'type' => 'button',
    'href' => null,
])

@php
$base = 'inline-flex items-center justify-center gap-2 rounded-[var(--radius-control)] px-4 py-2.5 text-sm font-medium transition-all duration-150 ease-out-soft active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50 min-h-11';

$classes = match ($variant) {
    'primary' => "$base bg-emerald text-white shadow-soft hover:bg-emerald-deep hover:-translate-y-px active:translate-y-0 active:bg-emerald-night",
    'secondary' => "$base border border-line-strong bg-surface text-ink hover:border-ink hover:bg-paper",
    // Brass FILL starts at brass-deep, not brass: white 14px label on plain
    // brass (#A8772E) measures 3.93:1, under AA's 4.5 (axe serious, caught on
    // /welcome 2026-07-28). brass-deep is 5.61:1 and brass-darker 7.70:1, so
    // the hover still darkens. Brass stays brass for ORNAMENT — this is only
    // the case where it carries white text. Same role-split lesson as
    // [[dark-accent-needs-a-fill-variant]].
    'brass' => "$base bg-brass-deep text-white shadow-soft hover:bg-brass-darker hover:-translate-y-px active:translate-y-0",
    'ghost' => "$base text-ink-soft hover:bg-paper hover:text-ink",
    'danger' => "$base border border-danger text-danger hover:bg-danger-tint",
    'danger-fill' => "$base bg-danger text-white hover:opacity-90",
    'ink-outline' => "$base border border-paper/40 text-on-dark hover:bg-paper/10",
    default => $base,
};
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }} wire:navigate>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
