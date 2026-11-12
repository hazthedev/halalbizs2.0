@props([
    'model',          // Livewire property path, e.g. body.en
    'id',             // matches the <label for>
    'label',
    'error' => null,
])

{{-- Admin content editor (Pages, Help articles): the seller description
     editor plus headings and links. Saves the same HTML the old raw box
     took; the server still cleans it (HtmlSanitizer::CMS_TAGS). --}}
@php
    $formats = [
        'h2' => __('Heading'),
        'h3' => __('Small heading'),
        'bold' => __('Bold'),
        'italic' => __('Italic'),
        'bulletList' => __('Bullet list'),
        'orderedList' => __('Numbered list'),
        'link' => __('Link'),
    ];
@endphp
<div>
    <label for="{{ $id }}" class="mb-1.5 block text-[13px] font-medium text-ink">{{ $label }}</label>
    <div wire:ignore data-rich-editor="{{ $model }}" data-link-prompt="{{ __('Link address (leave empty to remove the link)') }}"
         x-data="descriptionEditor($wire.entangle('{{ $model }}'), '{{ $id }}', @js($label), true)"
         @class(['overflow-hidden rounded-[var(--radius-control)] border bg-surface focus-within:ring-2 focus-within:ring-emerald', 'border-danger' => $error, 'border-line-strong' => ! $error])>
        <div class="flex flex-wrap gap-1 border-b border-line p-1" role="toolbar" aria-label="{{ __('Text formatting') }}">
            @foreach ($formats as $format => $formatLabel)
                <button type="button" x-on:click="toggle('{{ $format }}')"
                        x-bind:aria-pressed="!!active['{{ $format }}']" aria-label="{{ $formatLabel }}" title="{{ $formatLabel }}"
                        x-bind:class="active['{{ $format }}'] ? 'bg-emerald-tint text-emerald' : 'text-ink-soft'"
                        class="inline-flex size-11 items-center justify-center rounded-[var(--radius-control)] hover:bg-paper focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                    @switch($format)
                        @case('h2')<span aria-hidden="true" class="text-[15px] font-semibold">H1</span>@break
                        @case('h3')<span aria-hidden="true" class="text-[13px] font-semibold">H2</span>@break
                        @case('bold')<strong aria-hidden="true">B</strong>@break
                        @case('italic')<em aria-hidden="true">I</em>@break
                        @case('bulletList')
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 6h12M9 12h12M9 18h12M3 6h1M3 12h1M3 18h1"/></svg>@break
                        @case('orderedList')
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 6h11M10 12h11M10 18h11M3 3h1v6M2 14c0-3 5-3 5 0 0 2-5 2-5 6h5"/></svg>@break
                        @case('link')
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/></svg>@break
                    @endswitch
                </button>
            @endforeach
        </div>
        <div x-ref="editor" dir="auto"></div>
    </div>
    @if ($error)
        <p class="mt-1.5 text-[13px] text-danger">{{ $error }}</p>
    @else
        {{ $slot }}
    @endif
</div>
