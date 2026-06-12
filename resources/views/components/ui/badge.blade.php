@props(['variant' => 'sale'])

@php
$classes = match ($variant) {
    'sale' => 'bg-emerald-tint text-emerald',
    'free-shipping' => 'border border-emerald text-emerald',
    'cod' => 'border border-ink text-ink',
    'verified' => 'bg-emerald text-white',
    'out-of-stock' => 'bg-line text-ink-faint',
    'warn' => 'bg-warn-tint text-warn',
    'danger' => 'bg-danger-tint text-danger',
    'neutral' => 'bg-paper text-ink-soft border border-line',
    default => 'bg-emerald-tint text-emerald',
};
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[0.04em] $classes"]) }}>{{ $slot }}</span>
