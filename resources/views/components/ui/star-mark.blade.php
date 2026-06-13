@props(['size' => 24])

{{-- Rub el Hizb / khatam — two interlaced squares, the house brand glyph.
     Inherits colour via currentColor; decorative, so aria-hidden. --}}
<svg {{ $attributes->merge(['class' => 'shrink-0']) }}
     width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24"
     fill="none" stroke="currentColor" stroke-width="1.5"
     stroke-linejoin="round" aria-hidden="true">
    <rect x="5.4" y="5.4" width="13.2" height="13.2" rx="0.6"/>
    <rect x="5.4" y="5.4" width="13.2" height="13.2" rx="0.6" transform="rotate(45 12 12)"/>
    <circle cx="12" cy="12" r="2"/>
</svg>
