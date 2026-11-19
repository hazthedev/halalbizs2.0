{{-- Rows per page, top right of the table (HB-G165, Haze 2026-11-19: "rows filter should be at
     top right table"). Sits on the row directly above the table card; the slot fills the left. --}}
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>{{ $slot }}</div>
    <label class="inline-flex items-center gap-2 text-[13px] text-ink-soft">
        {{ __('table.per_page') }}
        <select wire:model.live="perPage" class="min-h-9 rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-ink focus-visible:ring-2 focus-visible:ring-emerald">
            @foreach ([15, 20, 50, 100] as $size)
                <option value="{{ $size }}">{{ $size }}</option>
            @endforeach
        </select>
    </label>
</div>
