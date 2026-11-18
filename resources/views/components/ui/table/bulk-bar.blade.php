@props(['selected'])
@if (count($selected) > 0)
    <div role="group" aria-label="{{ __('table.bulk_actions') }}" class="flex flex-wrap items-center gap-3 rounded-[var(--radius-card)] border border-line bg-surface px-4 py-2 shadow-soft">
        <span class="text-[13px] font-medium text-ink">{{ __('table.selected', ['count' => count($selected)]) }}</span>
        {{ $slot }}
    </div>
@endif
