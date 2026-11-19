{{-- Rows per page, top right of the table (HB-G165, Haze 2026-11-19: "rows filter should be at
     top right table"). Sits on the row directly above the table card, its right edge on the
     card's right edge; -mt-2/mb-2 pull it tight to the card so it reads as the table's own control,
     not a separate band (lists stack with space-y-4). The slot fills the left. --}}
<div class="-mt-2 mb-2 flex flex-wrap items-center justify-between gap-3">
    <div>{{ $slot }}</div>
    <label class="inline-flex items-center gap-2 text-[13px] text-ink-soft">
        {{ __('table.per_page') }}
        <select wire:model.live="perPage" class="min-h-8 rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-[13px] text-ink focus-visible:ring-2 focus-visible:ring-emerald">
            @foreach ([15, 20, 50, 100] as $size)
                <option value="{{ $size }}">{{ $size }}</option>
            @endforeach
        </select>
    </label>
</div>
