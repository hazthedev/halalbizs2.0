@props(['rows'])
<div class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-3 text-[13px] text-ink-soft">
        <p role="status">{{ __('table.showing', ['from' => $rows->firstItem() ?? 0, 'to' => $rows->lastItem() ?? 0, 'total' => $rows->total()]) }}</p>
        <label class="inline-flex items-center gap-2">
            {{ __('table.per_page') }}
            <select wire:model.live="perPage" class="min-h-11 rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-ink focus-visible:ring-2 focus-visible:ring-emerald">
                @foreach ([15, 20, 50, 100] as $size)
                    <option value="{{ $size }}">{{ $size }}</option>
                @endforeach
            </select>
        </label>
    </div>
    @if ($rows->hasPages())
        {{ $rows->links('pagination.table') }}
    @endif
</div>
