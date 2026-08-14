@props(['ornament' => false, 'pattern' => false, 'scrollLabel' => null])

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

// 30 wide tables across admin and seller wrap themselves in exactly this
// component with overflow-x-auto. A scroll container holding no focusable
// cells is unreachable by keyboard — there is no way to see the columns past
// the viewport edge without a mouse. Detecting it here beats 30 identical
// edits, and every future wide table inherits the fix.
//
// The MOUSE affordance is already handled and is deliberate: app.css hides
// scrollbars only under .shopfront, so admin and seller surfaces keep a
// visible one. This adds the keyboard half, nothing else.
$scrolls = str_contains((string) $attributes->get('class'), 'overflow-x-auto');
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}
     @if ($scrolls) tabindex="0" role="group" @if ($scrollLabel) aria-label="{{ $scrollLabel }}" @endif @endif
>{{ $slot }}</div>
