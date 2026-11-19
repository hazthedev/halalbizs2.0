@props(['placeholder'])
{{-- Standard list search box (same markup as the seller tables). Extra filters go in the slot. --}}
<x-ui.card class="p-3">
    <div class="flex flex-wrap items-center gap-2">
        <div class="relative min-w-48 flex-1">
            <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/></svg>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ $placeholder }}" aria-label="{{ $placeholder }}"
                   class="block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface py-2 pl-9 pr-3 text-[13px] text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
        </div>
        {{ $slot }}
    </div>
</x-ui.card>
