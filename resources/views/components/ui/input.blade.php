@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'error' => null,
    'hint' => null,
])

@php
    // Password fields get a reveal toggle. No field on the site would show what
    // you had typed, which is sign-up drop-off on any phone keyboard — and it
    // has to live HERE, in the shared control, or the four auth screens drift.
    $isPassword = $type === 'password';
@endphp

<div {{ $attributes->only('class') }}>
    @if ($label)
        <label @if($name) for="{{ $name }}" @endif class="mb-1.5 block text-[13px] font-medium text-ink">{{ $label }}</label>
    @endif

    <div @class(['relative' => $isPassword]) @if ($isPassword) x-data="{ shown: false }" @endif>
        <input
            {{-- With JS off the binding never runs and this stays `password`, which
                 is the safe direction to fail. --}}
            type="{{ $type }}"
            @if ($isPassword) x-bind:type="shown ? 'text' : 'password'" @endif
            {{-- The visible <label> above only reaches assistive tech when it can
                 point at an id, and `id` is only emitted when the caller passed a
                 `name`. 138 of the 144 call sites in this app do not, so those
                 fields rendered a label that was associated with nothing —
                 measured with axe on /admin/system/settings, 9 flagged inputs on
                 that page alone.

                 aria-label rather than a generated id on purpose: an id derived
                 from wire:model collides when two forms on one screen bind the
                 same property (the attributes and brands screens both do), and a
                 random one changes on every Livewire re-render. --}}
            @if($name)
                name="{{ $name }}" id="{{ $name }}"
            @elseif($label && ! $attributes->has('aria-label'))
                aria-label="{{ $label }}"
            @endif
            {{ $attributes->except('class')->merge([
                'class' => 'block w-full rounded-[var(--radius-control)] border bg-surface px-3.5 py-2.5 text-sm text-ink transition-[color,box-shadow,border-color] duration-[120ms] ease-out-soft placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald/60 focus-visible:border-emerald min-h-11 '
                    .($isPassword ? 'pr-12 ' : '')
                    .($error ? 'border-danger' : 'border-line-strong'),
            ]) }}
        >

        @if ($isPassword)
            <button type="button" x-cloak
                    x-on:click="shown = ! shown"
                    x-bind:aria-label="shown ? @js(__('Hide password')) : @js(__('Show password'))"
                    x-bind:aria-pressed="shown ? 'true' : 'false'"
                    class="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-r-[var(--radius-control)] text-ink-soft transition-colors duration-(--dur-micro) hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald/60">
                <svg x-show="! shown" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12S5.25 5.25 12 5.25 21.75 12 21.75 12 18.75 18.75 12 18.75 2.25 12 2.25 12Z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                <svg x-show="shown" x-cloak class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.22A10.5 10.5 0 0 0 2.25 12s3 6.75 9.75 6.75c1.34 0 2.55-.27 3.62-.72M6.53 6.53A10 10 0 0 1 12 5.25c6.75 0 9.75 6.75 9.75 6.75a13 13 0 0 1-2.53 3.47M3 3l18 18"/>
                </svg>
            </button>
        @endif
    </div>

    @if ($error)
        <p class="mt-1.5 text-[13px] text-danger">{{ $error }}</p>
    @elseif ($hint)
        <p class="mt-1.5 text-[13px] text-ink-faint">{{ $hint }}</p>
    @endif
</div>
