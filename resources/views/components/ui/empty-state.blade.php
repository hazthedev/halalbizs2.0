@props([
    'title' => null,
    'message' => null,
    'icon' => true,
])

{{-- Reusable empty state — khatam motif in a brass medallion, title, message,
     and an optional action area passed as the default slot. --}}
<div {{ $attributes->merge(['class' => 'mx-auto flex max-w-md flex-col items-center px-4 py-16 text-center']) }}>
    @if ($icon)
        <div class="surface-zellij mb-5 flex size-20 items-center justify-center rounded-full border border-brass/25 bg-brass-tint/50 text-brass">
            <x-ui.star-mark :size="36" stroke-width="1.25" />
        </div>
    @endif

    @if ($title)
        <h2 class="font-display text-[22px] font-semibold leading-tight text-ink">{{ $title }}</h2>
    @endif

    @if ($message)
        <p class="mt-2 text-sm leading-relaxed text-ink-soft">{{ $message }}</p>
    @endif

    @if (trim($slot ?? '') !== '')
        <div class="mt-6 flex flex-wrap items-center justify-center gap-3">{{ $slot }}</div>
    @endif
</div>
