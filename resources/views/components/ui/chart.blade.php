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
    <div x-ref="canvas" role="img" aria-label="{{ $attributes->get('aria-label', __('Chart')) }}"></div>
    <noscript>
        <div class="flex items-center justify-center rounded-[10px] border border-line bg-paper text-sm text-ink-faint" style="height: {{ $height }}px">
            {{ __('Charts need JavaScript enabled.') }}
        </div>
    </noscript>
</div>
