@props(['slug' => null])

{{-- Red LIVE pill with pulsing dot (the old header Live link's look). Taps through to the room. --}}
<a href="{{ $slug ? route('live.room', $slug) : route('live.index') }}" wire:navigate {{ $attributes->class('inline-flex items-center gap-1.5 rounded-full bg-danger px-2.5 py-1 text-[11px] font-medium uppercase tracking-[0.06em] text-white') }}>
    <span class="size-1.5 animate-pulse rounded-full bg-white"></span>{{ __('Live') }}
</a>
