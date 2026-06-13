@props(['ornament' => false, 'pattern' => false])

@php
$classes = 'rounded-[var(--radius-card)] border bg-surface shadow-card';
$classes .= $ornament ? ' border-brass/30' : ' border-line';
$classes .= $pattern ? ' surface-zellij' : '';
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</div>
