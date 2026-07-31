@props(['ornament' => false, 'pattern' => false])

@php
// min-w-0: as a grid/flex item a card defaults to min-width:auto, so a wide
// table inside it stretches its own track instead of letting the table's
// overflow-x wrapper scroll. On the seller dashboard that pushed the page to
// 416px against a 390px viewport, and because body is overflow-x-clip the
// extra 26px was cut off rather than scrollable. Harmless in block flow,
// where min-width is already 0.
$classes = 'min-w-0 rounded-[var(--radius-card)] border bg-surface shadow-card';
$classes .= $ornament ? ' border-brass/30' : ' border-line';
$classes .= $pattern ? ' surface-zellij' : '';
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</div>
