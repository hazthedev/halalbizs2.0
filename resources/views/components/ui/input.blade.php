@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'error' => null,
    'hint' => null,
])

<div {{ $attributes->only('class') }}>
    @if ($label)
        <label @if($name) for="{{ $name }}" @endif class="mb-1.5 block text-[13px] font-medium text-ink">{{ $label }}</label>
    @endif

    <input
        type="{{ $type }}"
        @if($name) name="{{ $name }}" id="{{ $name }}" @endif
        {{ $attributes->except('class')->merge([
            'class' => 'block w-full rounded-[var(--radius-control)] border bg-surface px-3.5 py-2.5 text-sm text-ink transition-colors placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald/60 focus-visible:border-emerald min-h-11 '
                .($error ? 'border-danger' : 'border-line-strong'),
        ]) }}
    >

    @if ($error)
        <p class="mt-1.5 text-[13px] text-danger">{{ $error }}</p>
    @elseif ($hint)
        <p class="mt-1.5 text-[13px] text-ink-faint">{{ $hint }}</p>
    @endif
</div>
