@props(['tone' => 'line'])

@php
// tone: line (on paper) · brass (accented) · paper (on dark ink frames)
$lineClass = match ($tone) {
    'brass' => 'via-brass/40',
    'paper' => 'via-paper/25',
    default => 'via-line-strong',
};
$markClass = match ($tone) {
    'brass' => 'text-brass',
    'paper' => 'text-paper/50',
    default => 'text-line-strong',
};
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-3']) }} role="presentation" aria-hidden="true">
    <span class="h-px flex-1 bg-gradient-to-r from-transparent {{ $lineClass }} to-transparent"></span>
    <x-ui.star-mark :size="14" class="{{ $markClass }}" />
    <span class="h-px flex-1 bg-gradient-to-l from-transparent {{ $lineClass }} to-transparent"></span>
</div>
