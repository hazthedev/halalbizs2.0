@props([
    'title',
    'subtitle' => null,
    'href' => null,
    'linkLabel' => null,
    'mark' => true,
    'as' => 'h2',
])

{{-- Standard section header: Fraunces title with an optional khatam mark and a
     trailing "view all" link or custom actions slot. Keeps a real heading
     element so role/name selectors stay valid. --}}
<div {{ $attributes->merge(['class' => 'flex items-end justify-between gap-4']) }}>
    <div class="min-w-0">
        <{{ $as }} class="flex items-center gap-2.5 font-display text-xl font-semibold leading-tight text-ink sm:text-[26px]">
            @if ($mark)
                <x-ui.star-mark :size="18" class="text-brass" />
            @endif
            <span class="min-w-0 truncate">{{ $title }}</span>
        </{{ $as }}>
        @if ($subtitle)
            <p class="mt-1.5 text-sm text-ink-soft">{{ $subtitle }}</p>
        @endif
    </div>

    @if ($href)
        <a href="{{ $href }}" wire:navigate
           class="group inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-emerald transition-colors duration-[120ms] ease-out-soft hover:text-emerald-deep">
            {{ $linkLabel ?? __('View all') }}
            <span aria-hidden="true" class="transition-transform duration-[120ms] ease-out-soft group-hover:translate-x-0.5">&rarr;</span>
        </a>
    @elseif (isset($actions))
        <div class="shrink-0">{{ $actions }}</div>
    @endif
</div>
