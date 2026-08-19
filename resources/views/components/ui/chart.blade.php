@props([
    'id',
    'payload',      // ['type' => 'line'|'area'|'donut'|'bar', 'series' => [...], 'options' => [...], 'labels' => [...]]
    'refreshEvent' => null, // Livewire event name that carries a fresh payload
    'height' => 280,
    'emptyMessage' => null, // shown instead of the chart when there is no data
])

@php
// An EMPTY series makes the charting library draw nothing whatsoever — no
// axes, no legend, no message — so the card renders as a heading above a tall
// blank rectangle. Found on the seller dashboard's "Orders by status" donut:
// statusPayload() filters to statuses with a count > 0, so a store with no
// orders in the period produces series = [], and the card was ~500px of white.
//
// An ALL-ZERO series is a different thing and must still draw: a flat line at
// zero is the honest answer for a quiet period, and the revenue chart beside
// it renders exactly that. Only emptiness is the defect.
//
// This lives in the component rather than at each call site so every chart in
// admin and seller inherits it — there are three on this page alone.
// Emptiness lives at DIFFERENT depths per chart type, which is what made the
// first version of this check wrong: a donut's series is a flat list of
// numbers, but a line/area/bar's is a list of {name, data} — so an empty bar
// chart still arrives as [{'name': 'Units sold', 'data': []}], one element
// long. Counting the outer list said "has data" and the card stayed blank.
$chartSeries = $payload['series'] ?? [];
$chartHasData = collect(is_array($chartSeries) ? $chartSeries : [])
    ->contains(fn ($entry) => is_array($entry)
        ? count($entry['data'] ?? []) > 0   // wrapped: look one level down
        : true);                            // flat: any number is data
@endphp

@unless ($chartHasData)
    {{-- Deliberately OUTSIDE the wire:ignore below, so a Livewire re-render can
         swap it back to a real chart. The payloads are recomputed in render(),
         so changing the period (7d -> 90d) flips this correctly; if it sat
         inside the ignored subtree it would strand the placeholder forever. --}}
    <div class="flex items-center justify-center rounded-[var(--radius-card)] border border-dashed border-line bg-paper px-4 text-center text-[13px] text-ink-faint" style="height: {{ $height }}px">
        {{ $emptyMessage ?? __('No data for this period yet.') }}
    </div>
@else
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
@endunless
