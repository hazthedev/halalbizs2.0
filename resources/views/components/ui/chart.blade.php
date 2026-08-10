@props([
    'id',
    'payload',      // ['type' => 'line'|'area'|'donut'|'bar', 'series' => [...], 'options' => [...], 'labels' => [...]]
    'refreshEvent' => null, // Livewire event name that carries a fresh payload
    'height' => 280,
])

{{-- wire:ignore keeps Livewire morphs from destroying the live chart; the
     Alpine hbChart driver owns updates via the refresh event. --}}
<div
    wire:ignore
    x-data="hbChart(@js($payload), @js($refreshEvent))"
    class="w-full"
    style="min-height: {{ $height }}px"
>
    {{-- role="group", NOT role="img" (axe `nested-interactive`, serious).
         The charting library renders a focusable svg[tabindex=0] in here, and
         donuts add a focusable legend item per slice. role="img" declares the
         subtree to be one flat graphic, so assistive tech is told to ignore
         exactly the things a keyboard user can still tab into — the label and
         the contents contradict each other.

         role="group" keeps the aria-label naming the region while permitting
         interactive descendants, so the keyboard path is preserved rather than
         removed. Fixing this by putting tabindex="-1" on the children would
         silence axe and take the data points away from keyboard users, which is
         the wrong direction. --}}
    <div x-ref="canvas" role="group" aria-label="{{ $attributes->get('aria-label', __('Chart')) }}"></div>
    <noscript>
        <div class="flex items-center justify-center rounded-[var(--radius-card)] border border-line bg-paper text-sm text-ink-faint" style="height: {{ $height }}px">
            {{ __('Charts need JavaScript enabled.') }}
        </div>
    </noscript>
</div>
