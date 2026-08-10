@props(['size' => 24])

{{-- Speech bubble with a sparkle — the concierge affordance.

     Replaces <x-ui.star-mark> in the two concierge launchers. The star-mark is
     the house BRAND glyph (Rub el Hizb), so a green circle carrying it reads as
     decoration, and the 2026-08-07 walkthrough recorded the launcher as "the
     unlabelled dark-green bubble on every page" despite both buttons having a
     correct aria-label and the desktop one revealing "Ask the concierge" on
     hover. The accessible name was never the problem — hover does not exist on
     touch, and nobody hovers a mystery circle. The glyph has to say it.

     Used in BOTH launchers (mobile header + desktop floating) on purpose: one
     component is what stops the two entry points drifting apart.

     Decorative — the accessible name lives on the button. --}}
<svg {{ $attributes->merge(['class' => 'shrink-0']) }}
     width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24"
     fill="none" stroke="currentColor" stroke-width="1.6"
     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="M20.25 11.4c0 3.9-3.7 7.06-8.25 7.06a9.4 9.4 0 0 1-2.2-.26L5.1 20.1a.45.45 0 0 1-.62-.5l.7-3.06C4.16 15.24 3.75 13.87 3.75 11.4c0-3.9 3.7-7.05 8.25-7.05s8.25 3.16 8.25 7.05Z"/>
    <path d="M12 8.2l.62 1.72 1.73.63-1.73.62L12 12.9l-.62-1.73-1.73-.62 1.73-.63L12 8.2Z"/>
</svg>
