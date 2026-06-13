@props(['class' => ''])

<div {{ $attributes->merge(['class' => "shimmer rounded-[var(--radius-control)] $class"]) }}></div>
